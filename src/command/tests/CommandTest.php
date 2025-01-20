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

namespace HyperfTest\Command;

use Hyperf\Context\ApplicationContext;
use Hyperf\Support\Reflection\ClassInvoker;
use HyperfTest\Command\Command\DefaultSwooleFlagsCommand;
use HyperfTest\Command\Command\FooCommand;
use HyperfTest\Command\Command\FooExceptionCommand;
use HyperfTest\Command\Command\FooExitCommand;
use HyperfTest\Command\Command\FooProhibitableCommand;
use HyperfTest\Command\Command\FooTraitCommand;
use HyperfTest\Command\Command\SwooleFlagsCommand;
use HyperfTest\Command\Command\Traits\Foo;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class CommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testHookFlags()
    {
        $command = new DefaultSwooleFlagsCommand('test:demo');
        $this->assertSame(SWOOLE_HOOK_ALL, $command->getHookFlags());

        $command = new SwooleFlagsCommand('test:demo2');
        $this->assertSame(SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL, $command->getHookFlags());
    }

    #[Group('NonCoroutine')]
    public function testExitCodeWhenThrowException()
    {
        ApplicationContext::setContainer($container = Mockery::mock(ContainerInterface::class));
        $container->shouldReceive('has')->with(EventDispatcherInterface::class)->andReturnFalse();

        $output = Mockery::mock(OutputInterface::class);
        $application = Mockery::mock(ConsoleApplication::class);
        $application->shouldReceive('renderThrowable')
            ->with(Mockery::type(Throwable::class), $output)
            ->times(1);
        $application->shouldReceive('getHelperSet');

        /** @var FooExceptionCommand $command */
        $command = new ClassInvoker(new FooExceptionCommand('foo'));
        $command->setApplication($application);
        $command->setOutput($output);
        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->andReturnFalse();

        $exitCode = $command->execute($input, $output);
        $this->assertSame(1, $exitCode);

        /** @var FooExitCommand $command */
        $command = new ClassInvoker(new FooExitCommand());
        $command->setApplication($application);
        $command->setOutput($output);
        $exitCode = $command->execute($input, $output);
        $this->assertSame(11, $exitCode);

        /** @var FooCommand $command */
        $command = new ClassInvoker(new FooCommand());
        $command->setApplication($application);
        $command->setOutput($output);
        $exitCode = $command->execute($input, $output);
        $this->assertSame(0, $exitCode);

        $command = new FooTraitCommand();
        $command->setApplication($application);
        $command->setOutput($output);
        $this->assertArrayHasKey(Foo::class, (fn () => $this->setUpTraits($input, $output))->call($command));
        $this->assertSame('foo', (fn () => $this->propertyFoo)->call($command));
    }

    public function testExitCodeWhenThrowExceptionInCoroutine()
    {
        $this->testExitCodeWhenThrowException();
    }

    public function testProhibitableCommand()
    {
        $application = Mockery::mock(ConsoleApplication::class);
        $application->shouldReceive('getHelperSet');

        $output = Mockery::mock(OutputInterface::class);
        $command = new ClassInvoker(new FooProhibitableCommand());
        $command->setApplication($application);
        $command->setOutput($output);
        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->andReturnFalse();
        $result = $command->execute($input, $output);
        $this->assertSame(FooProhibitableCommand::SUCCESS, $result);

        FooProhibitableCommand::prohibit(true);

        $output = Mockery::mock(OutputInterface::class);
        $output->shouldReceive('warn')->andReturn();
        $command = new ClassInvoker(new FooProhibitableCommand());
        $command->setApplication($application);
        $command->setOutput($output);
        $input = Mockery::mock(InputInterface::class);
        $input->shouldReceive('getOption')->andReturnFalse();
        $result = $command->execute($input, $output);
        $this->assertSame(FooProhibitableCommand::FAILURE, $result);
    }
}
