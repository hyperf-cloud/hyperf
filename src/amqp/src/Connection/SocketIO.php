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
namespace Hyperf\Amqp\Connection;

use Hyperf\Utils\Channel\Caller;
use InvalidArgumentException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Wire\AMQPWriter;
use PhpAmqpLib\Wire\IO\AbstractIO;
use Swoole\Coroutine\Client;

class SocketIO extends AbstractIO
{
    const READ_BUFFER_WAIT_INTERVAL = 100000;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var float
     */
    protected $connectionTimeout;

    /**
     * @var float
     */
    protected $readWriteTimeout;

    /**
     * @var resource
     */
    protected $context;

    /**
     * @var bool
     */
    protected $keepalive;

    /**
     * @var int
     */
    protected $heartbeat;

    /**
     * @var null|float
     */
    protected $lastRead;

    /**
     * @var null|float
     */
    protected $lastWrite;

    /**
     * @var array
     */
    protected $lastError;

    /**
     * @var bool
     */
    protected $tcpNodelay = false;

    /**
     * @var bool
     */
    protected $ssl = false;

    /** @var int */
    private $initialHeartbeat;

    /**
     * @var null|Client
     */
    private $sock;

    /**
     * @var null|Caller
     */
    private $caller;

    private $buffer = '';

    /**
     * SwooleIO constructor.
     *
     * @param null|mixed $context
     * @throws \InvalidArgumentException when readWriteTimeout argument does not 2x the heartbeat
     */
    public function __construct(
        string $host,
        int $port,
        float $connectionTimeout,
        float $readWriteTimeout,
        $context = null,
        bool $keepalive = false,
        int $heartbeat = 0
    ) {
        if ($heartbeat !== 0 && ($readWriteTimeout < ($heartbeat * 2))) {
            throw new InvalidArgumentException('Argument readWriteTimeout must be at least 2x the heartbeat.');
        }
        $this->host = $host;
        $this->port = $port;
        $this->connectionTimeout = $connectionTimeout;
        $this->readWriteTimeout = $readWriteTimeout;
        $this->context = $context;
        $this->keepalive = $keepalive;
        $this->heartbeat = $heartbeat;
        $this->initialHeartbeat = $heartbeat;
    }

    /**
     * Sets up the stream connection.
     *
     * @throws AMQPRuntimeException
     * @throws \Exception
     */
    public function connect()
    {
        if ($this->caller) {
            $this->caller->initInstance();
        } else {
            $host = $this->host;
            $port = $this->port;
            $connectionTimeout = $this->connectionTimeout;
            $this->caller = make(Caller::class, [
                'closure' => static function () use ($host, $port, $connectionTimeout) {
                    $sock = new Client(SWOOLE_SOCK_TCP);
                    if (! $sock->connect($host, $port, $connectionTimeout)) {
                        throw new AMQPRuntimeException(
                            sprintf('Error Connecting to server(%s): %s ', $sock->errCode, $sock->errMsg),
                            $sock->errCode
                        );
                    }
                    return $sock;
                },
            ]);
        }
    }

    /**
     * Reconnects the socket.
     */
    public function reconnect()
    {
        $this->close();
        $this->connect();
    }

    /**
     * @param int $len
     * @throws AMQPRuntimeException
     * @return mixed|string
     */
    public function read($len)
    {
        $this->check_heartbeat();
        return $this->caller->call(function (Client $socket) use ($len) {
            do {
                if ($len <= strlen($this->buffer)) {
                    $data = substr($this->buffer, 0, $len);
                    $this->buffer = substr($this->buffer, $len);
                    $this->lastRead = microtime(true);

                    return $data;
                }

                if (! $socket->connected) {
                    throw new AMQPRuntimeException('Broken pipe or closed connection');
                }

                $read_buffer = $socket->recv($this->readWriteTimeout ? $this->readWriteTimeout : -1);
                if ($read_buffer === false) {
                    throw new AMQPRuntimeException('Error receiving data, errno=' . $socket->errCode);
                }

                if ($read_buffer === '') {
                    throw new AMQPRuntimeException('Connection is closed.');
                }

                $this->buffer .= $read_buffer;
            } while (true);
        });
    }

    /**
     * @param string $data
     * @throws AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @return mixed|void
     */
    public function write($data)
    {
        $this->check_heartbeat();
        $this->caller->call(function (Client $socket) use ($data) {
            $buffer = $socket->send($data);

            if ($buffer === false) {
                throw new AMQPRuntimeException('Error sending data');
            }

            $this->lastWrite = microtime(true);
        });
    }

    /**
     * Heartbeat logic: check connection health here.
     */
    public function check_heartbeat()
    {
        // ignore unless heartbeat interval is set
        if ($this->heartbeat !== 0 && $this->lastRead && $this->lastWrite) {
            $t = microtime(true);
            $t_read = round($t - $this->lastRead);
            $t_write = round($t - $this->lastWrite);

            // server has gone away
            if (($this->heartbeat * 2) < $t_read) {
                $this->reconnect();
            }

            // time for client to send a heartbeat
            if (($this->heartbeat / 2) < $t_write) {
                $this->write_heartbeat();
            }
        }
    }

    public function close()
    {
        $this->lastRead = null;
        $this->lastWrite = null;
    }

    /**
     * @return null|Client|resource
     */
    public function get_socket()
    {
        return $this->sock;
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        throw new \RuntimeException('getSocket is invalid for SocketIO.');
    }

    /**
     * @param int $sec
     * @param int $usec
     * @return int|mixed
     */
    public function select($sec, $usec)
    {
        $this->check_heartbeat();

        return 1;
    }

    /**
     * @return $this
     */
    public function disableHeartbeat()
    {
        $this->heartbeat = 0;

        return $this;
    }

    /**
     * @return $this
     */
    public function reenableHeartbeat()
    {
        $this->heartbeat = $this->initialHeartbeat;

        return $this;
    }

    /**
     * Sends a heartbeat message.
     */
    protected function write_heartbeat()
    {
        $pkt = new AMQPWriter();
        $pkt->write_octet(8);
        $pkt->write_short(0);
        $pkt->write_long(0);
        $pkt->write_octet(0xCE);
        $this->write($pkt->getvalue());
    }

    protected function do_select($sec, $usec)
    {
        return 1;
    }
}
