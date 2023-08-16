<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\SocketIOServer;

use Closure;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Engine\Channel;
use Hyperf\SocketIOServer\Collector\EventAnnotationCollector;
use Hyperf\SocketIOServer\Collector\SocketIORouter;
use Hyperf\SocketIOServer\Exception\RouteNotFoundException;
use Hyperf\SocketIOServer\Parser\Decoder;
use Hyperf\SocketIOServer\Parser\Encoder;
use Hyperf\SocketIOServer\Parser\Engine;
use Hyperf\SocketIOServer\Parser\Packet;
use Hyperf\SocketIOServer\Room\EphemeralInterface;
use Hyperf\SocketIOServer\SidProvider\SidProviderInterface;
use Hyperf\WebSocketServer\Constant\Opcode;
use Hyperf\WebSocketServer\Sender;
use Swoole\Atomic;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Timer;
use Swoole\WebSocket\Server;
use Throwable;

use function Hyperf\Support\make;

/**
 *  packet types
 *  0 open
 *  Sent from the server when new transport is opened (recheck)
 *  1 close
 *  Request the close of this transport but does not shut down the connection itself.
 *  2 ping
 *  Sent by the client. Server should answer with a pong packet containing the same data
 *  3 pong
 *  Sent by the server to respond to ping packets.
 *  4 message
 *  actual message, client and server should call their callbacks with the data.
 *  5 upgrade
 *  Before engine.io switches a transport, it tests, if server and client can communicate over this transport. If this *    test succeed, the client sends an upgrade packets which requests the server to flush its cache on the old transport *   and switch to the new transport.
 *  6 noop
 *  A noop packet. Used primarily to force a poll cycle when an incoming websocket connection is received.
 *  packet data types
 *  Packet#CONNECT (0)
 *  Packet#DISCONNECT (1)
 *  Packet#EVENT (2)
 *  Packet#ACK (3)
 *  Packet#ERROR (4)
 *  Packet#BINARY_EVENT (5)
 *  Packet#BINARY_ACK (6)
 *  basic format    => $socket->emit("message", "hello world");
 *                  => sprintf('%d%d%s', $packetType, $packetDataType, json_encode([$event, $data]))
 *                  => 42["message", "hello world"].
 * @mixin BaseNamespace
 */
class SocketIO implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    public static $isMainWorker = false;

    public static string $serverId = '';

    public static ?Atomic $messageId = null;

    /**
     * @var Channel[]
     */
    protected array $clientCallbacks = [];

    /**
     * @var int[]
     */
    protected array $clientCallbackTimers = [];

    protected SocketIOConfig $config;

    public function __construct(
        protected StdoutLoggerInterface $stdoutLogger,
        protected Sender $sender,
        protected Decoder $decoder,
        protected Encoder $encoder,
        protected SidProviderInterface $sidProvider,
        ?SocketIOConfig $config = null
    ) {
        $this->config = $config ?? ApplicationContext::getContainer()->get(SocketIOConfig::class);
    }

    public function __call($method, $args)
    {
        return $this->of('/')->{$method}(...$args);
    }

    /**
     * @param Response|Server $server
     * @param mixed $frame
     */
    public function onMessage($server, $frame): void
    {
        if ($frame->opcode == Opcode::PING) {
            if ($server instanceof Response) {
                $server->push('', Opcode::PONG);
            } else {
                $server->push($frame->fd, '', Opcode::PONG);
            }
            return;
        }

        if ($frame->data[0] === Engine::PING) {
            $this->renewInAllNamespaces($frame->fd);
            if ($server instanceof Response) {
                $server->push(Engine::PONG); // sever pong
            } else {
                $server->push($frame->fd, Engine::PONG); // sever pong
            }
            return;
        }
        if ($frame->data[0] === Engine::CLOSE) {
            if ($server instanceof Response) {
                $server->close();
            } else {
                $server->disconnect($frame->fd);
            }
            return;
        }
        if ($frame->data[0] !== Engine::MESSAGE) {
            $this->stdoutLogger->error("EngineIO event type {$frame->data[0]} not supported");
            return;
        }
        // Check that the namespace is correct
        if (! str_contains($frame->data, ',') && ! str_contains($frame->data, '?')) {
            $this->stdoutLogger->error("The data format is incorrect: {$frame->data}");
            return;
        }
        $packet = $this->decoder->decode(substr($frame->data, 1));
        switch ($packet->type) {
            case Packet::OPEN: // client open
                $responsePacket = Packet::create([
                    'type' => Packet::OPEN,
                    'nsp' => $packet->nsp,
                ]);
                if ($server instanceof Response) {
                    $server->push(Engine::MESSAGE . $this->encoder->encode($responsePacket)); // sever open
                } else {
                    $server->push($frame->fd, Engine::MESSAGE . $this->encoder->encode($responsePacket)); // sever open
                }
                break;
            case Packet::CLOSE: // client disconnect
                if ($server instanceof Response) {
                    $server->close();
                } else {
                    $server->disconnect($frame->fd);
                }
                break;
            case Packet::EVENT: // client message with ack
                if ($packet->id !== '') {
                    $packet->data[] = function ($data) use ($frame, $packet) {
                        $responsePacket = Packet::create([
                            'id' => $packet->id,
                            'nsp' => $packet->nsp,
                            'type' => Packet::ACK,
                            'data' => $data,
                        ]);

                        if ($this->sender->check($frame->fd)) {
                            $this->sender->push($frame->fd, Engine::MESSAGE . $this->encoder->encode($responsePacket));
                        }
                    };
                }
                $this->dispatch($frame->fd, $packet->nsp, ...$packet->data);
                break;
            case Packet::ACK: // server ack
                $ackId = $packet->id;
                if (isset($this->clientCallbacks[$ackId]) && $this->clientCallbacks[$ackId] instanceof Channel) {
                    if (is_array($packet->data)) {
                        foreach ($packet->data as $piece) {
                            $this->clientCallbacks[$ackId]->push($piece);
                        }
                    } else {
                        $this->clientCallbacks[$ackId]->push($packet->data);
                    }
                    unset($this->clientCallbacks[$ackId]);
                    Timer::clear($this->clientCallbackTimers[$ackId]);
                }
                break;
            default:
                $this->stdoutLogger->error("SocketIO packet type {$packet->type} not supported");
        }
    }

    /**
     * @param Response|Server $server
     * @param Request $request
     */
    public function onOpen($server, $request): void
    {
        $data = [
            'sid' => $this->sidProvider->getSid($request->fd),
            'upgrades' => ['websocket'],
            'pingInterval' => $this->config->getPingInterval(),
            'pingTimeout' => $this->config->getPingTimeout(),
        ];
        if ($server instanceof Response) {
            $server->push(Engine::OPEN . json_encode($data)); // socket is open
            $server->push(Engine::MESSAGE . Packet::OPEN); // server open
        } else {
            $server->push($request->fd, Engine::OPEN . json_encode($data)); // socket is open
            $server->push($request->fd, Engine::MESSAGE . Packet::OPEN); // server open
        }

        $this->dispatchEventInAllNamespaces($request->fd, 'connect');
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        $this->dispatchEventInAllNamespaces($fd, 'disconnect');
    }

    /**
     * @return BaseNamespace|NamespaceInterface possibly a BaseNamespace, but allow user to use any NamespaceInterface implementation instead
     */
    public function of(string $nsp): NamespaceInterface
    {
        $class = SocketIORouter::getClassName($nsp);
        if (! $class) {
            throw new RouteNotFoundException("namespace {$nsp} is not registered.");
        }
        if (! ApplicationContext::getContainer()->has($class)) {
            throw new RouteNotFoundException("namespace {$nsp} cannot be instantiated.");
        }
        return ApplicationContext::getContainer()->get($class);
    }

    public function addCallback(string $ackId, Channel $channel, int $timeoutMs = null)
    {
        $this->clientCallbacks[$ackId] = $channel;
        // Clean up using timer to avoid memory leak.
        $timerId = Timer::after($timeoutMs ?? $this->config->getClientCallbackTimeout(), function () use ($ackId) {
            if (! isset($this->clientCallbacks[$ackId])) {
                return;
            }
            $this->clientCallbacks[$ackId]->close();
            unset($this->clientCallbacks[$ackId]);
        });
        $this->clientCallbackTimers[$ackId] = $timerId;
    }

    public function setClientCallbackTimeout(int $clientCallbackTimeout): static
    {
        $this->config->setClientCallbackTimeout($clientCallbackTimeout);
        return $this;
    }

    public function setPingInterval(int $pingInterval): static
    {
        $this->config->setPingInterval($pingInterval);
        return $this;
    }

    public function setPingTimeout(int $pingTimeout): static
    {
        $this->config->setPingTimeout($pingTimeout);
        return $this;
    }

    private function dispatch(int $fd, string $nsp, string $event, ...$payloads): void
    {
        $socket = $this->makeSocket($fd, $nsp);
        if (empty($socket)) {
            return;
        }
        $ack = null;

        // Check if ack is required
        $last = array_pop($payloads);
        if ($last instanceof Closure) {
            $ack = $last;
        } else {
            array_push($payloads, $last);
        }

        $handlers = $this->getEventHandlers($nsp, $event);
        foreach ($handlers as $handler) {
            $result = $handler($socket, ...$payloads);
            $ack && $ack([$result]);
        }
    }

    private function getEventHandlers(string $nsp, string $event): array
    {
        $class = SocketIORouter::getClassName($nsp);
        /** @var NamespaceInterface $instance */
        $instance = ApplicationContext::getContainer()->get($class);

        /** @var callable[] $output */
        $output = [];

        foreach (EventAnnotationCollector::get($class . '.' . $event, []) as [, $method]) {
            $output[] = [$instance, $method];
        }

        foreach ($instance->getEventHandlers() as $key => $callbacks) {
            if ($key === $event) {
                $output = array_merge($callbacks, $output);
            }
        }

        return $output;
    }

    private function makeSocket(int $fd, string $nsp = '/'): ?Socket
    {
        try {
            return make(Socket::class, [
                'adapter' => SocketIORouter::getAdapter($nsp),
                'sender' => $this->sender,
                'fd' => $fd,
                'nsp' => $nsp,
                'addCallback' => function (string $ackId, Channel $channel, ?int $timeout = null) {
                    $this->addCallback($ackId, $channel, $timeout);
                }, ]);
        } catch (Throwable $exception) {
            $this->stdoutLogger->error('Socket.io ' . $exception->getMessage());
            return null;
        }
    }

    private function dispatchEventInAllNamespaces(int $fd, string $event)
    {
        $all = SocketIORouter::list();
        if (! array_key_exists('forward', $all)) {
            return;
        }
        foreach (array_keys($all['forward']) as $nsp) {
            $this->dispatch($fd, $nsp, $event, null);
        }
    }

    private function renewInAllNamespaces(int $fd)
    {
        $all = SocketIORouter::list();
        if (! array_key_exists('forward', $all)) {
            return;
        }
        foreach (array_keys($all['forward']) as $nsp) {
            $adapter = $this->of($nsp)->getAdapter();
            if ($adapter instanceof EphemeralInterface) {
                $adapter->renew($this->sidProvider->getSid($fd));
            }
        }
    }
}
