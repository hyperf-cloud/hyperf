<?php

declare(strict_types=1);

namespace Hyperf\WebSocketClient\Constant;

class Opcode
{
    public const CONTINUATION = 0x0; // 0b0000, 0
    public const TEXT = 0x1;       // 0b0001 , 1
    public const BINARY = 0x2 ;      // 0b0010 , 2
    public const CLOSE = 0x8 ;      // 0b1000 , 8
    public const PING =  0x9 ;       // 0b1001, 9
    public const PONG = 0xA;       // 0b1010 , 10

    public static function getOpcodeDefinition(int $opcode): string
    {
        $definitions = [
            self::CONTINUATION => 'Continuation',
            self::TEXT => 'Text',
            self::BINARY => 'Binary',
            self::CLOSE => 'Close',
            self::PING => 'Ping',
            self::PONG => 'Pong',
        ];
        return $definitions[$opcode] ?? 'Unknown Opcode';
    }
}
