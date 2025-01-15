<?php
declare(strict_types=1);
namespace Hyperf\WebSocketClient;

use Hyperf\HttpMessage\Uri\Uri;

abstract class Client implements ClientInterface
{
    protected array $headers = [];

    public function __construct(protected Uri $uri, array $headers = [])
    {
        $this->headers = $headers;
    }

    public function connect(string $path = '/'): bool
    {
        if ($query = $this->uri->getQuery()) {
            $path .= '?' . $query;
        }
        return $this->connectInternal($path);
    }

    public function recv(float $timeout = -1): Frame
    {
        return $this->recvInternal($timeout);
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    abstract protected function connectInternal(string $path): bool;
    abstract protected function recvInternal(float $timeout = -1): Frame;
}