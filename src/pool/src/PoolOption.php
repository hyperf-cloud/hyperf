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
namespace Hyperf\Pool;

use Hyperf\Contract\PoolOptionInterface;

class PoolOption implements PoolOptionInterface
{
    public function __construct(
        /**
         * Min connections of pool.
         * This means the pool will create $minConnections connections when
         * pool initialization.
         */
        private int $minConnections = 1,
        /**
         * Max connections of pool.
         */
        private int $maxConnections = 10,
        /**
         * The timeout of connect the connection.
         * Default value is 10 seconds.
         */
        private float $connectTimeout = 10.0,
        /**
         * The timeout of pop a connection.
         * Default value is 3 seconds.
         */
        private float $waitTimeout = 3.0,
        /**
         * Heartbeat of connection.
         * If the value is 10, then means 10 seconds.
         * If the value is -1, then means does not need the heartbeat.
         * Default value is -1.
         */
        private float $heartbeat = -1,
        /**
         * The max idle time for connection.
         */
        private float $maxIdleTime = 60.0
    )
    {
    }

    public function getMaxConnections(): int
    {
        return $this->maxConnections;
    }

    public function setMaxConnections(int $maxConnections): static
    {
        $this->maxConnections = $maxConnections;
        return $this;
    }

    public function getMinConnections(): int
    {
        return $this->minConnections;
    }

    public function setMinConnections(int $minConnections): static
    {
        $this->minConnections = $minConnections;
        return $this;
    }

    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function setConnectTimeout(float $connectTimeout): static
    {
        $this->connectTimeout = $connectTimeout;
        return $this;
    }

    public function getHeartbeat(): float
    {
        return $this->heartbeat;
    }

    public function setHeartbeat(float $heartbeat): static
    {
        $this->heartbeat = $heartbeat;
        return $this;
    }

    public function getWaitTimeout(): float
    {
        return $this->waitTimeout;
    }

    public function setWaitTimeout(float $waitTimeout): static
    {
        $this->waitTimeout = $waitTimeout;
        return $this;
    }

    public function getMaxIdleTime(): float
    {
        return $this->maxIdleTime;
    }

    public function setMaxIdleTime(float $maxIdleTime): static
    {
        $this->maxIdleTime = $maxIdleTime;
        return $this;
    }
}
