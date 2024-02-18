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

namespace Hyperf\AsyncQueue;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Context\ApplicationContext;

function dispatch(JobInterface $job, ?int $delay = null, ?int $maxAttempts = null, ?string $queue = null): bool
{
    if (is_int($maxAttempts)) {
        $job->setMaxAttempts($maxAttempts);
    }

    $queue = (fn ($queue) => $this->queue ?? $queue ?? 'default')->call($job, $queue);

    return ApplicationContext::getContainer()
        ->get(DriverFactory::class)
        ->get($queue)
        ->push($job, $delay ?? 0);
}
