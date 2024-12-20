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

use Closure;
use Hyperf\Context\ApplicationContext;
use RuntimeException;
use Swoole\Runtime;

/**
 * @param callable[] $callables
 * @param int $concurrent if $concurrent is equal to 0, that means unlimited
 */
function parallel(array $callables, int $concurrent = 0): array
{
    $parallel = new Parallel($concurrent);
    foreach ($callables as $key => $callable) {
        $parallel->add($callable, $key);
    }
    return $parallel->wait();
}

/**
 * @template TReturn
 *
 * @param Closure():TReturn $closure
 * @return TReturn
 */
function wait(Closure $closure, ?float $timeout = null)
{
    if (ApplicationContext::hasContainer()) {
        $waiter = ApplicationContext::getContainer()->get(Waiter::class);
        return $waiter->wait($closure, $timeout);
    }
    return (new Waiter())->wait($closure, $timeout);
}

function co(callable $callable): bool|int
{
    $id = Coroutine::create($callable);
    return $id > 0 ? $id : false;
}

function defer(callable $callable): void
{
    Coroutine::defer($callable);
}

function go(callable $callable): bool|int
{
    $id = Coroutine::create($callable);
    return $id > 0 ? $id : false;
}

/**
 * Run callable in non-coroutine environment, all hook functions by Swoole only available in the callable.
 *
 * @param array|callable $callbacks
 */
function run($callbacks, int $flags = SWOOLE_HOOK_ALL): bool
{
    if (Coroutine::inCoroutine()) {
        throw new RuntimeException('Function \'run\' only execute in non-coroutine environment.');
    }

    Runtime::enableCoroutine($flags);

    /* @phpstan-ignore-next-line */
    $result = \Swoole\Coroutine\run(...(array) $callbacks);

    Runtime::enableCoroutine(0);
    return $result;
}
