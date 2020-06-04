<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Signal;

class SignalHandler implements SignalHandlerInterface
{
    public function listen(): array
    {
        return [];
    }

    public function handle(int $signal, string $process): void
    {
    }
}
