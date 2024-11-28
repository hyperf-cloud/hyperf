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

namespace Hyperf\Task;

use Closure;

class Task
{
    public Closure|array $callback;

    public function __construct(callable|array $callback, public array $arguments = [], public int $workerId = -1)
    {
        $this->callback = $callback;
    }
}
