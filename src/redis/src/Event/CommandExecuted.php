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

namespace Hyperf\Redis\Event;

use Hyperf\Redis\RedisConnection;

class CommandExecuted
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $command,
        public array $parameters,
        public ?float $time,
        public RedisConnection $connection,
        public string $connectionName
    ) {
    }
}
