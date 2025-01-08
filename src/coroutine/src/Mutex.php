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

namespace Hyperf\Coroutine;

use Hyperf\Engine\Channel;

class Mutex
{
    /**
     * @var Channel[]
     */
    protected static array $channels = [];

    public static function lock(string $key, float $timeout = -1): bool
    {
        if (! isset(static::$channels[$key])) {
            static::$channels[$key] = new Channel(1);
        }

        $channel = static::$channels[$key];
        $channel->push(1, $timeout);
        if ($channel->isTimeout() || $channel->isClosing()) {
            return false;
        }

        return true;
    }

    public static function unlock(string $key, float $timeout = 5): bool
    {
        if (isset(static::$channels[$key])) {
            $channel = static::$channels[$key];
            $channel->pop($timeout);
            if ($channel->isTimeout()) {
                // unlock more than once
                return false;
            }
        }

        return true;
    }

    public static function clear(string $key): void
    {
        if (isset(static::$channels[$key])) {
            $channel = static::$channels[$key];
            static::$channels[$key] = null;
            $channel->close();
        }
    }
}
