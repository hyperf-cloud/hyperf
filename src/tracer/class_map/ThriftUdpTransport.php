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
namespace Jaeger;

use Hyperf\Engine\Channel;
use Hyperf\Utils\Coroutine;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thrift\Exception\TTransportException;
use Thrift\Transport\TTransport;

class ThriftUdpTransport extends TTransport
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var Channel
     */
    private $chan;

    /**
     * ThriftUdpTransport constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(string $host, int $port, LoggerInterface $logger = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Whether this transport is open.
     *
     * @return bool true if open
     */
    public function isOpen()
    {
        return $this->socket !== null;
    }

    /**
     * Open the transport for reading/writing.
     *
     * @throws TTransportException if cannot open
     */
    public function open()
    {
        if (! Coroutine::inCoroutine()) {
            $this->doOpen();
            return;
        }

        if (! $this->chan) {
            $this->loop();
            return;
        }

        $this->chan->push(function () {
            $this->doOpen();
        });
    }

    /**
     * Close the transport.
     */
    public function close()
    {
        if (! Coroutine::inCoroutine()) {
            @socket_close($this->socket);
            $this->socket = null;
            return;
        }

        if (! $this->chan) {
            $this->loop();
        }

        $this->chan->push(function () {
            @socket_close($this->socket);
            $this->socket = null;
        });
    }

    /**
     * Read some data into the array.
     *
     * @todo
     *
     * @param int $len How much to read
     * @return string The data that has been read
     */
    public function read($len)
    {
    }

    /**
     * Writes the given data out.
     *
     * @param string $buf The data to write
     * @throws TTransportException if writing fails
     */
    public function write($buf)
    {
        if (! Coroutine::inCoroutine()) {
            $this->doWrite($buf);
        }

        if (! $this->chan) {
            $this->loop();
        }

        $this->chan->push(function () use ($buf) {
            $this->doWrite($buf);
        });
    }

    private function doOpen(): void
    {
        $this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $ok = @socket_connect($this->socket, $this->host, $this->port);
        if ($ok === false) {
            throw new TTransportException('socket_connect failed');
        }
    }

    private function doWrite(string $buf): void
    {
        if (! $this->isOpen()) {
            throw new TTransportException('transport is closed');
        }

        $ok = @socket_write($this->socket, $buf);
        if ($ok === false) {
            throw new TTransportException('socket_write failed');
        }
    }

    private function loop()
    {
        $this->chan = new Channel();
        Coroutine::create(function () {
            try {
                $this->doOpen();
                while (true) {
                    $closure = $this->chan->pop();
                    if (! $closure) {
                        break;
                    }
                    $closure->call($this);
                }
            } finally {
                @socket_close($this->socket);
                $this->chan = null;
                $this->socket = null;
            }
        });
    }
}
