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

namespace HyperfTest\Command\Command;

use Hyperf\Command\Command;
use Hyperf\Command\Concerns\Prohibitable;

class FooProhibitableCommand extends Command
{
    use Prohibitable;

    public function handle()
    {
        if ($this->isProhibited()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
