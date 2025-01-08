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

namespace Hyperf\Support;

use Closure;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use Throwable;

class SafeCaller
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @template TReturn
     * @template TDefault
     *
     * @param Closure(): TReturn $closure
     * @param null|(Closure(): TDefault) $default
     * @return ($default is Closure? TDefault : null)|TReturn
     */
    public function call(Closure $closure, ?Closure $default = null, string $level = LogLevel::CRITICAL): mixed
    {
        try {
            return $closure();
        } catch (Throwable $exception) {
            if ($this->container->has(StdoutLoggerInterface::class) && $logger = $this->container->get(StdoutLoggerInterface::class)) {
                $logger->log($level, (string) $exception);
            }
        }

        return value($default);
    }
}
