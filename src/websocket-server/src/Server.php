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

namespace Hyperf\WebSocketServer;

use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\MiddlewareInitializerInterface;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnHandShakeInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Dispatcher\HttpDispatcher;
use Hyperf\Engine\Constant;
use Hyperf\Engine\Http\FdGetter;
use Hyperf\Engine\Http\Server as HttpServer;
use Hyperf\Engine\WebSocket\WebSocket;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpMessage\Base\Response;
use Hyperf\HttpMessage\Server\Request as Psr7Request;
use Hyperf\HttpMessage\Server\Response as Psr7Response;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\ResponseEmitter;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\Server\Event;
use Hyperf\Server\Server as AsyncStyleServer;
use Hyperf\Server\ServerManager;
use Hyperf\Support\SafeCaller;
use Hyperf\WebSocketServer\Collector\FdCollector;
use Hyperf\WebSocketServer\Context as WsContext;
use Hyperf\WebSocketServer\Exception\Handler\WebSocketExceptionHandler;
use Hyperf\WebSocketServer\Exception\WebSocketHandShakeException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Coroutine\Http\Server as SwCoServer;
use Swoole\Http\Request;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Server as WebSocketServer;
use Swow\Psr7\Server\ServerConnection as SwowServerConnection;
use Throwable;

use function Hyperf\Coroutine\defer;
use function Hyperf\Coroutine\wait;

class Server implements MiddlewareInitializerInterface, OnHandShakeInterface, OnCloseInterface, OnMessageInterface
{
    protected ?CoreMiddlewareInterface $coreMiddleware = null;

    protected array $exceptionHandlers = [];

    protected array $middlewares = [];

    protected string $serverName = 'websocket';

    /**
     * @var null|HttpServer|SwCoServer|WebSocketServer
     */
    protected mixed $server = null;

    public function __construct(protected ContainerInterface $container, protected HttpDispatcher $dispatcher, protected ExceptionHandlerDispatcher $exceptionHandlerDispatcher, protected ResponseEmitter $responseEmitter, protected StdoutLoggerInterface $logger)
    {
    }

    public function initCoreMiddleware(string $serverName): void
    {
        $this->serverName = $serverName;
        $this->coreMiddleware = new CoreMiddleware($this->container, $serverName);

        $config = $this->container->get(ConfigInterface::class);
        $this->middlewares = $config->get('middlewares.' . $serverName, []);
        $this->exceptionHandlers = $config->get('exceptions.handler.' . $serverName, [
            WebSocketExceptionHandler::class,
        ]);
    }

    public function getServer(): HttpServer|SwCoServer|WebSocketServer
    {
        if ($this->server) {
            return $this->server;
        }
        $config = $this->container->get(ConfigInterface::class);

        $type = $config->get('server.type', AsyncStyleServer::class);

        if ($type === AsyncStyleServer::class) {
            return $this->container->get(SwooleServer::class);
        }

        [, $server] = ServerManager::get($this->serverName);

        return $this->server = $server;
    }

    public function getSender(): Sender
    {
        return $this->container->get(Sender::class);
    }

    /**
     * @param Request|\Swow\Http\Server\Request $request
     * @param SwooleResponse|SwowServerConnection $response
     */
    public function onHandShake($request, $response): void
    {
        try {
            CoordinatorManager::until(Constants::WORKER_START)->yield();
            $fd = $this->getFd($response);
            Context::set(WsContext::FD, $fd);
            $security = $this->container->get(Security::class);

            $psr7Response = $this->initResponse();
            $psr7Request = $this->initRequest($request);

            $this->logger->debug(sprintf('WebSocket: fd[%d] start a handshake request.', $fd));

            $key = $psr7Request->getHeaderLine(Security::SEC_WEBSOCKET_KEY);
            if ($security->isInvalidSecurityKey($key)) {
                throw new WebSocketHandShakeException('sec-websocket-key is invalid!');
            }

            $psr7Request = $this->coreMiddleware->dispatch($psr7Request);
            $middlewares = $this->middlewares;
            /** @var Dispatched $dispatched */
            $dispatched = $psr7Request->getAttribute(Dispatched::class);
            if ($dispatched->isFound()) {
                $registeredMiddlewares = MiddlewareManager::get($this->serverName, $dispatched->handler->route, $psr7Request->getMethod());
                $middlewares = array_merge($middlewares, $registeredMiddlewares);
            }

            /** @var Response $psr7Response */
            $psr7Response = $this->dispatcher->dispatch($psr7Request, $middlewares, $this->coreMiddleware);

            $class = $psr7Response->getAttribute(CoreMiddleware::HANDLER_NAME);

            if (empty($class)) {
                $this->logger->warning('WebSocket handshake failed, because the class does not exists.');
                return;
            }

            FdCollector::set($fd, $class);
            $server = $this->getServer();
            if (Constant::isCoroutineServer($server)) {
                $upgrade = new WebSocket($response, $request, $this->logger);

                $this->getSender()->setResponse($fd, $response);
                $this->deferOnOpen($request, $class, $response, $fd);

                $upgrade->on(WebSocket::ON_MESSAGE, $this->getOnMessageCallback());
                $upgrade->on(WebSocket::ON_CLOSE, $this->getOnCloseCallback());
                $upgrade->start();
            } else {
                $this->deferOnOpen($request, $class, $server, $fd);
            }
        } catch (Throwable $throwable) {
            // Delegate the exception to exception handler.
            $psr7Response = $this->container->get(SafeCaller::class)->call(function () use ($throwable) {
                return $this->exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
            }, static function () {
                return (new Psr7Response())->withStatus(400);
            });

            isset($fd) && FdCollector::del($fd);
            isset($fd) && WsContext::release($fd);
        } finally {
            isset($fd) && $this->getSender()->setResponse($fd, null);
            // Send the Response to client.
            if (isset($psr7Response) && $psr7Response instanceof ResponseInterface) {
                $this->responseEmitter->emit($psr7Response, $response, true);
            }
        }
    }

    public function onMessage($server, $frame): void
    {
        if ($server instanceof WebSocketServer) {
            $fd = $frame->fd;
        } else {
            $fd = $this->getFd($server);
        }
        Context::set(WsContext::FD, $fd);
        $fdObj = FdCollector::get($fd);
        if (! $fdObj) {
            $this->logger->warning(sprintf('WebSocket: fd[%d] does not exist.', $fd));
            return;
        }

        $instance = $this->container->get($fdObj->class);

        if (! $instance instanceof OnMessageInterface) {
            $this->logger->warning($instance::class . ' is not instanceof ' . OnMessageInterface::class);
            return;
        }

        try {
            $instance->onMessage($server, $frame);
        } catch (Throwable $exception) {
            $this->logger->error((string) $exception);
        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        $fdObj = FdCollector::get($fd);
        if (! $fdObj) {
            return;
        }

        $this->logger->debug(sprintf('WebSocket: fd[%d] closed.', $fd));

        Context::set(WsContext::FD, $fd);
        defer(function () use ($fd) {
            // Move those functions to defer, because onClose may throw exceptions
            FdCollector::del($fd);
            WsContext::release($fd);
        });

        $instance = $this->container->get($fdObj->class);
        if ($instance instanceof OnCloseInterface) {
            try {
                $instance->onClose($server, $fd, $reactorId);
            } catch (Throwable $exception) {
                $this->logger->error((string) $exception);
            }
        }
    }

    protected function getFd($response): int
    {
        return $this->container->get(FdGetter::class)->get($response);
    }

    /**
     * @param mixed $request
     * @param SwooleResponse|SwowServerConnection|WebSocketServer $server
     */
    protected function deferOnOpen($request, string $class, mixed $server, int $fd)
    {
        $instance = $this->container->get($class);
        if ($server instanceof WebSocketServer) {
            defer(static function () use ($request, $instance, $server) {
                if ($instance instanceof OnOpenInterface) {
                    $instance->onOpen($server, $request);
                }
            });
        } else {
            wait(static function () use ($request, $instance, $server, $fd) {
                Context::set(WsContext::FD, $fd);
                if ($instance instanceof OnOpenInterface) {
                    $instance->onOpen($server, $request);
                }
            });
        }
    }

    /**
     * Initialize PSR-7 Request.
     * @param mixed $request
     */
    protected function initRequest($request): ServerRequestInterface
    {
        if ($request instanceof ServerRequestInterface) {
            $psr7Request = $request;
        } else {
            $psr7Request = Psr7Request::loadFromSwooleRequest($request);
        }
        Context::set(ServerRequestInterface::class, $psr7Request);
        WsContext::set(ServerRequestInterface::class, $psr7Request);
        return $psr7Request;
    }

    /**
     * Initialize PSR-7 Response.
     */
    protected function initResponse(): ResponseInterface
    {
        Context::set(ResponseInterface::class, $psr7Response = new Psr7Response());
        return $psr7Response;
    }

    protected function getOnMessageCallback(): callable
    {
        [$instance, $method] = $this->getCallback(Event::ON_MESSAGE);

        return static function ($response, $frame) use ($instance, $method) {
            wait(static function () use ($instance, $method, $response, $frame) {
                $instance->{$method}($response, $frame);
            });
        };
    }

    protected function getOnCloseCallback(): callable
    {
        [$instance, $method] = $this->getCallback(Event::ON_CLOSE);

        return static function ($response, $fd) use ($instance, $method) {
            wait(static function () use ($instance, $method, $response, $fd) {
                $instance->{$method}($response, $fd, 0);
            });
        };
    }

    protected function getCallback(string $event): array
    {
        [, , $callbacks] = ServerManager::get($this->serverName);

        [$callback, $method] = $callbacks[$event];
        $instance = $this->container->get($callback);

        return [$instance, $method];
    }
}
