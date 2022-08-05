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
namespace HyperfTest\Nsq\Stub;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Container;
use Hyperf\Nsq\MessageBuilder;
use Hyperf\Nsq\Nsq;
use Hyperf\Nsq\Pool\NsqPoolFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Waiter;
use Mockery;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ContainerStub
{
    /**
     * @return ContainerInterface
     */
    public static function getContainer()
    {
        $container = Mockery::mock(Container::class);
        ApplicationContext::setContainer($container);

        $container->shouldReceive('make')->with(Waiter::class, Mockery::any())->andReturnUsing(fn($_, $args) => new Waiter(...$args));
        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturnUsing(function () {
            $logger = Mockery::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('debug')->andReturn(null);
            return $logger;
        });
        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturnUsing(fn() => Mockery::mock(EventDispatcherInterface::class));
        $container->shouldReceive('has')->andReturnUsing(fn($class) => true);

        $container->shouldReceive('get')->with(NsqPoolFactory::class)->andReturnUsing(fn() => new NsqPoolFactory($container));

        $container->shouldReceive('get')->with(MessageBuilder::class)->andReturnUsing(fn() => new MessageBuilder());

        $container->shouldReceive('make')->with(DemoConsumer::class, Mockery::any())->andReturnUsing(fn() => new DemoConsumer($container));

        $container->shouldReceive('make')->with(DisabledDemoConsumer::class, Mockery::any())->andReturnUsing(fn() => new DisabledDemoConsumer($container));

        $container->shouldReceive('make')->with(Nsq::class, Mockery::any())->andReturn(Mockery::mock(Nsq::class));

        return $container;
    }
}
