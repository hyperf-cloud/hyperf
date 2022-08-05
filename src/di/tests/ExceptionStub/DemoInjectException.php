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
namespace HyperfTest\Di\ExceptionStub;

use Hyperf\Di\Annotation\Inject;

/**
 * Class DemoInject.
 */
class DemoInjectException
{
    #[Inject(required: true)]
    private ?\Demo1 $demo = null;

    public function getDemo()
    {
        return $this->demo;
    }
}
