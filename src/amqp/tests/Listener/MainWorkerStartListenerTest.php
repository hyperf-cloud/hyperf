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

namespace HyperfTest\Amqp\Listener;

use Doctrine\Instantiator\Instantiator;
use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Listener\MainWorkerStartListener;
use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use HyperfTest\Amqp\Stub\DemoDisableAutoDeclareProducer;
use HyperfTest\Amqp\Stub\DemoProducer;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class MainWorkerStartListenerTest extends TestCase
{
    /**
     * Tear down the test case.
     */
    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testProcessWithDisabled()
    {
        $container = Mockery::mock(ContainerInterface::class);

        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturnUsing(function () {
            $logger = Mockery::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('debug')->andReturn(null);
            $logger->shouldReceive('log')->andReturn(null);
            return $logger;
        });

        $container->shouldReceive('has')->with(ConfigInterface::class)->andReturnTrue();
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturnUsing(function () {
            return new Config([
                'amqp' => [
                    'enable' => false,
                ],
            ]);
        });

        // If it is disabled, the Producer class will never be fetched.
        $container->shouldReceive('get')->with(\Hyperf\Amqp\Producer::class)->andReturn(null)->never();
        $container->shouldReceive('get')->with(Instantiator::class)->andReturnUsing(function () {
            $instantiator = Mockery::mock(new Instantiator());
            $instantiator->shouldReceive('instantiate')->andReturn(null);
            return $instantiator;
        });

        AnnotationCollector::collectClass(DemoProducer::class, Producer::class, new Producer(
            exchange: uniqid(),
            routingKey: uniqid(),
        ));

        $listener = new MainWorkerStartListener($container, $container->get(StdoutLoggerInterface::class));
        $listener->process(new stdClass());

        $this->assertTrue(true);
    }

    public function testProcessWithEnable()
    {
        $container = Mockery::mock(ContainerInterface::class);

        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturnUsing(function () {
            $logger = Mockery::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('debug')->andReturn(null);
            $logger->shouldReceive('log')->andReturn(null);
            return $logger;
        });

        $container->shouldReceive('has')->with(ConfigInterface::class)->andReturnTrue();
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturnUsing(function () {
            return new Config([
                'amqp' => [
                    'enable' => true,
                ],
            ]);
        });

        $container->shouldReceive('get')->with(Instantiator::class)->andReturn(new Instantiator());

        $producer = Mockery::mock(\Hyperf\Amqp\Producer::class);
        $producer->shouldReceive('declare')->withAnyArgs()->once();

        $container->shouldReceive('get')->with(\Hyperf\Amqp\Producer::class)->andReturn($producer);

        AnnotationCollector::collectClass(DemoProducer::class, Producer::class, new Producer(
            exchange: uniqid(),
            routingKey: uniqid(),
        ));

        $listener = new MainWorkerStartListener($container, $container->get(StdoutLoggerInterface::class));
        $listener->process(new stdClass());

        $this->assertTrue(true);
    }

    public function testProcessWithoutIsAutoDeclare()
    {
        $container = Mockery::mock(ContainerInterface::class);

        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturnUsing(function () {
            $logger = Mockery::mock(StdoutLoggerInterface::class);
            $logger->shouldReceive('debug')->andReturn(null);
            $logger->shouldReceive('log')->andReturn(null);
            return $logger;
        });

        $container->shouldReceive('has')->with(ConfigInterface::class)->andReturnTrue();
        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturnUsing(function () {
            return new Config([
                'amqp' => [
                    'enable' => true,
                ],
            ]);
        });

        $container->shouldReceive('get')->with(Instantiator::class)->andReturn(new Instantiator());

        $producer = Mockery::mock(\Hyperf\Amqp\Producer::class);
        $producer->shouldReceive('declare')->withAnyArgs()->once();

        $container->shouldReceive('get')->with(\Hyperf\Amqp\Producer::class)->andReturn($producer);

        AnnotationCollector::collectClass(DemoDisableAutoDeclareProducer::class, Producer::class, new Producer(
            exchange: uniqid(),
            routingKey: uniqid(),
        ));

        $listener = new MainWorkerStartListener($container, $container->get(StdoutLoggerInterface::class));
        $listener->process(new stdClass());

        $this->assertTrue(true);
    }
}
