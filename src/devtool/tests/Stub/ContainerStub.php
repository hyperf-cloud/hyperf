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
namespace HyperfTest\Devtool\Stub;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Utils\ApplicationContext;
use Mockery;

class ContainerStub
{
    public static function getContainer()
    {
        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);

        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturnUsing(function () {
            return new Config([
                'middlewares' => [
                    'http' => [
                    ],
                ],
            ]);
        });
        return $container;
    }
}
