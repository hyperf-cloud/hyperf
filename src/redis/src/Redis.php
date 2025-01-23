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

namespace Hyperf\Redis;

use Hyperf\Context\Context;
use Hyperf\Redis\Exception\InvalidRedisConnectionException;
use Hyperf\Redis\Pool\PoolFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

use function Hyperf\Coroutine\defer;

/**
 * @mixin \Redis
 */
class Redis
{
    use Traits\ScanCaller;
    use Traits\MultiExec;

    protected string $poolName = 'default';

    protected ?EventDispatcherInterface $eventDispatcher = null;

    public function __construct(protected PoolFactory $factory)
    {
    }

    public function __call($name, $arguments)
    {
        // Get a connection from coroutine context or connection pool.
        $hasContextConnection = Context::has($this->getContextKey());
        $connection = $this->getConnection($hasContextConnection);
        // Record the start time of the command.
        $start = (float) microtime(true);

        try {
            $connection = $connection->getConnection();
            // Execute the command with the arguments.
            $result = $connection->{$name}(...$arguments);
            $time = round((microtime(true) - $start) * 1000, 2);
            // Dispatch the command executed event.
            $this->eventDispatcher?->dispatch(new Event\CommandExecuted($name, $arguments, $time, $connection, $this->poolName));
        } finally {
            // Release connection.
            if (! $hasContextConnection) {
                if ($this->shouldUseSameConnection($name)) {
                    if ($name === 'select' && $db = $arguments[0]) {
                        $connection->setDatabase((int) $db);
                    }
                    // Should storage the connection to coroutine context, then use defer() to release the connection.
                    Context::set($this->getContextKey(), $connection);
                    defer(function () use ($connection) {
                        Context::set($this->getContextKey(), null);
                        $connection->release();
                    });
                } else {
                    // Release the connection after command executed.
                    $connection->release();
                }
            }
        }

        return $result;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Define the commands that need same connection to execute.
     * When these commands executed, the connection will storage to coroutine context.
     */
    private function shouldUseSameConnection(string $methodName): bool
    {
        return in_array($methodName, [
            'multi',
            'pipeline',
            'select',
        ]);
    }

    /**
     * Get a connection from coroutine context, or from redis connection pool.
     * @param mixed $hasContextConnection
     */
    private function getConnection($hasContextConnection): RedisConnection
    {
        $connection = null;
        if ($hasContextConnection) {
            $connection = Context::get($this->getContextKey());
        }
        if (! $connection instanceof RedisConnection) {
            $pool = $this->factory->getPool($this->poolName);
            $connection = $pool->get();
        }
        if (! $connection instanceof RedisConnection) {
            throw new InvalidRedisConnectionException('The connection is not a valid RedisConnection.');
        }
        return $connection;
    }

    /**
     * The key to identify the connection object in coroutine context.
     */
    private function getContextKey(): string
    {
        return sprintf('redis.connection.%s', $this->poolName);
    }
}
