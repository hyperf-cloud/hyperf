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
namespace Hyperf\Utils\Codec;

class Base62
{
    public const CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public const BASE = 62;

    public static function encode(int $number): string
    {
        $chars = [];
        while ($number > 0) {
            $remain = $number % self::BASE;
            $chars[] = self::CHARS[$remain];
            $number = ($number - $remain) / self::BASE;
        }
        return implode('', array_reverse($chars));
    }

    public static function decode(string $data): int
    {
        return array_reduce(array_map(fn($character) => strpos(self::CHARS, (string) $character), str_split($data)), fn($result, $remain) => $result * self::BASE + $remain);
    }
}
