<?php

declare(strict_types=1);

namespace Hyperf\WebSocketClient;

use Hyperf\WebSocketClient\Constant\Opcode;

interface ClientInterface
{
    public function connect(string $path = '/'): bool;
    
    public function recv(float $timeout = -1): Frame;
    
    public function push(string $data, int $opcode = Opcode::TEXT, ?int $flags = null): bool;
    
    public function close(): bool;
    
    public function getErrCode(): int;
    
    public function getErrMsg(): string;
    
    public function setHeaders(array $headers): static;
}