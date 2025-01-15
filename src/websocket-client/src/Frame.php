<?php
declare(strict_types=1);
namespace Hyperf\WebSocketClient;

use Stringable;

class Frame implements Stringable
{
    public function __construct(
        public bool $finish,
        public int $opcode,
        public string $data,
        public array $headers = []
    ) {}

    public function __toString(): string
    {
        return $this->data;
    }

    public function getOpcode(): int
    {
        return $this->opcode;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}