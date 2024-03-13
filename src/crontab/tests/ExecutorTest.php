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
namespace HyperfTest\Crontab;

use Carbon\Carbon;
use Hyperf\Command\Command;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ApplicationInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Listener\OnPipeMessageListener;
use Hyperf\Crontab\LoggerInterface;
use Hyperf\Crontab\PipeMessage;
use Hyperf\Crontab\Strategy\Executor;
use Hyperf\Engine\Channel;
use Hyperf\Framework\Event\OnPipeMessage;
use Mockery;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swoole\Http\Server;
use Symfony\Component\Console\Application;

/**
 * @internal
 * @coversNothing
 */
class ExecutorTest extends TestCase
{
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @group NonCoroutine
     */
    public function testExecute22()
    {
        $data = 'a:3:{s:4:"type";s:8:"callback";s:8:"callable";a:2:{i:0;s:32:"Hyperf\Crontab\Strategy\Executor";i:1;s:7:"execute";}s:4:"data";O:22:"Hyperf\Crontab\Crontab":6:{s:7:" * name";s:11:"echo-time-1";s:7:" * type";s:7:"command";s:7:" * rule";s:14:"*/11 * * * * *";s:10:" * command";s:12:"echo time();";s:7:" * memo";N;s:14:" * executeTime";O:13:"Carbon\Carbon":3:{s:4:"date";s:26:"2019-07-05 20:19:33.000000";s:13:"timezone_type";i:3;s:8:"timezone";s:13:"Asia/Shanghai";}}}';
        $server = Mockery::mock(Server::class);
        $event = new OnPipeMessage($server, 1, new PipeMessage(...unserialize($data)));
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')->with(LoggerInterface::class)->once()->andReturnFalse();
        $container->shouldReceive('has')->with(StdoutLoggerInterface::class)->twice()->andReturnFalse();
        $container->shouldReceive('has')->with(EventDispatcherInterface::class)->once()->andReturnFalse();
        $container->shouldReceive('get')->with(Executor::class)->once()->andReturn(new Executor($container));
        $listener = new OnPipeMessageListener($container);
        $listener->process($event);
    }

    public function getContainer()
    {
        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);
        $container->shouldReceive('has')->with(StdoutLoggerInterface::class)->andReturnFalse();
        $container->shouldReceive('has')->with(LoggerInterface::class)->andReturnFalse();
        $container->shouldReceive('has')->with(EventDispatcherInterface::class)->andReturnFalse();
        return $container;
    }

    public function testExecuteCommand()
    {
        $chan = new Channel(1);
        $command = new class($chan) extends Command {
            private $chan;

            public function __construct(Channel $chan)
            {
                parent::__construct('demo');
                $this->chan = $chan;
            }

            public function handle()
            {
                $this->chan->push('ok');
            }
        };

        $container = $this->getContainer();
        $app = new Application();
        $app->add($command);
        $container->shouldReceive('get')->with(ApplicationInterface::class)->andReturn($app);

        $crontab = Mockery::mock(Crontab::class);
        $crontab->shouldReceive('getExecuteTime')->andReturn(Carbon::now());
        $crontab->shouldReceive('getType')->andReturn('command');
        $crontab->shouldReceive('isSingleton')->andReturnFalse();
        $crontab->shouldReceive('isOnOneServer')->andReturnFalse();
        $crontab->shouldReceive('getCallback')->andReturn([
            'command' => 'demo',
        ]);
        (new Executor($container))->execute(
            $crontab
        );
        $this->assertEquals('ok', $chan->pop());
    }

    public function testExecuteClosure()
    {
        $container = $this->getContainer();

        $chan = new Channel(1);
        $crontab = Mockery::mock(Crontab::class);
        $crontab->shouldReceive('getExecuteTime')->andReturn(Carbon::now());
        $crontab->shouldReceive('getType')->andReturn('closure');
        $crontab->shouldReceive('isSingleton')->andReturnFalse();
        $crontab->shouldReceive('isOnOneServer')->andReturnFalse();
        $crontab->shouldReceive('getCallback')->andReturn(function () use ($chan) {
            $chan->push('closure');
        });
        (new Executor($container))->execute(
            $crontab
        );
        $this->assertEquals('closure', $chan->pop());
    }

    /**
     * @skipped
     * @throws ExpectationFailedException
     */
    public function testFloatDiffInRealSeconds()
    {
        $now = Carbon::now();
        $res = $now->clone()->addSeconds(30)->floatDiffInRealSeconds();
        $this->assertEquals(30, abs(round($res)));

        $now = Carbon::now();
        $res = $now->clone()->addSeconds(35)->floatDiffInRealSeconds(new Carbon());
        $this->assertEquals(35, abs(round($res)));

        $now = Carbon::now();
        $res = $now->clone()->addSeconds(-20)->floatDiffInRealSeconds(new Carbon());
        $this->assertLessThanOrEqual(20, abs(round($res)));

        $now = Carbon::now();
        $res = $now->clone()->addSeconds(-20)->floatDiffInRealSeconds(new Carbon(), false);
        $this->assertLessThanOrEqual(20, abs(round($res)));

        $now = Carbon::now();
        $res = $now->floatDiffInRealSeconds($now->clone()->addSeconds(30), false);
        $this->assertLessThanOrEqual(30, abs(round($res)));

        $now = Carbon::now();
        $res = $now->floatDiffInRealSeconds($now->clone()->addSeconds(-30), false);
        $this->assertLessThanOrEqual(30, abs(round($res)));
    }
}
