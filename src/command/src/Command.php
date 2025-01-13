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

namespace Hyperf\Command;

use Hyperf\Coroutine\Coroutine;
use Psr\EventDispatcher\EventDispatcherInterface;
use Swoole\ExitException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function Hyperf\Collection\collect;
use function Hyperf\Coroutine\run;
use function Hyperf\Support\class_basename;
use function Hyperf\Support\class_uses_recursive;
use function Hyperf\Support\swoole_hook_flags;
use function Hyperf\Tappable\tap;

abstract class Command extends SymfonyCommand
{
    use Concerns\DisableEventDispatcher;
    use Concerns\HasParameters;
    use Concerns\InteractsWithIO;

    /**
     * The name of the command.
     */
    protected ?string $name = null;

    /**
     * The description of the command.
     */
    protected string $description = '';

    /**
     * Execution in a coroutine environment.
     */
    protected bool $coroutine = true;

    /**
     * The eventDispatcher.
     */
    protected ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * The hookFlags of the command.
     */
    protected int $hookFlags = -1;

    /**
     * The name and signature of the command.
     */
    protected ?string $signature = null;

    /**
     * The exit code of the command.
     */
    protected int $exitCode = self::SUCCESS;

    public function __construct(?string $name = null)
    {
        $this->name = $name ?? $this->name;

        if ($this->hookFlags < 0) {
            $this->hookFlags = swoole_hook_flags();
        }

        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } else {
            parent::__construct($this->name);
        }

        $this->addDisableDispatcherOption();

        if (! empty($this->description)) {
            $this->setDescription($this->description);
        }

        if (! isset($this->signature)) {
            $this->specifyParameters();
        }
    }

    /**
     * Run the console command.
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->output = new SymfonyStyle($input, $output);

        $this->setUpTraits($this->input = $input, $this->output);

        return parent::run($this->input, $this->output);
    }

    /**
     * Call another console command.
     */
    public function call(string $command, array $arguments = []): int
    {
        $arguments['command'] = $command;

        return $this->getApplication()->find($command)->run($this->createInputFromArguments($arguments), $this->output);
    }

    /**
     * Call another console command without output.
     */
    public function callSilently(string $command, array $arguments = []): int
    {
        $arguments['command'] = $command;

        return $this->getApplication()->find($command)->run($this->createInputFromArguments($arguments), new NullOutput());
    }

    /**
     * Create an input instance from the given arguments.
     */
    protected function createInputFromArguments(array $arguments): ArrayInput
    {
        return tap(new ArrayInput(array_merge($this->context(), $arguments)), function (InputInterface $input) {
            if ($input->hasParameterOption(['--no-interaction'], true)) {
                $input->setInteractive(false);
            }
        });
    }

    /**
     * Get all the context passed to the command.
     */
    protected function context(): array
    {
        return collect($this->input->getOptions())->only([
            'ansi',
            'no-ansi',
            'no-interaction',
            'quiet',
            'verbose',
        ])->filter()->mapWithKeys(function ($value, $key) {
            return ["--{$key}" => $value];
        })->all();
    }

    /**
     * Configure the console command using a fluent definition.
     */
    protected function configureUsingFluentDefinition()
    {
        [$name, $arguments, $options] = Parser::parse($this->signature);

        parent::__construct($this->name = $name);

        // After parsing the signature we will spin through the arguments and options
        // and set them on this command. These will already be changed into proper
        // instances of these "InputArgument" and "InputOption" Symfony classes.
        $this->getDefinition()->addArguments($arguments);
        $this->getDefinition()->addOptions($options);
    }

    protected function configure()
    {
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->disableDispatcher($input);
        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        $callback = function () use ($method): int {
            try {
                $this->eventDispatcher?->dispatch(new Event\BeforeHandle($this));
                $statusCode = $this->{$method}();
                if (is_int($statusCode)) {
                    $this->exitCode = $statusCode;
                }
                $this->eventDispatcher?->dispatch(new Event\AfterHandle($this));
            } catch (Throwable $exception) {
                if (class_exists(ExitException::class) && $exception instanceof ExitException) {
                    return $this->exitCode = (int) $exception->getStatus();
                }

                if (! $this->eventDispatcher) {
                    throw $exception;
                }

                $this->getApplication()?->renderThrowable($exception, $this->output);

                $this->exitCode = self::FAILURE;

                $this->eventDispatcher->dispatch(new Event\FailToHandle($this, $exception));
            } finally {
                $this->eventDispatcher?->dispatch(new Event\AfterExecute($this, $exception ?? null));
            }

            return $this->exitCode;
        };

        if ($this->coroutine && ! Coroutine::inCoroutine()) {
            run($callback, $this->hookFlags);
        } else {
            $callback();
        }

        return $this->exitCode >= 0 && $this->exitCode <= 255 ? $this->exitCode : self::INVALID;
    }

    /**
     * Setup traits of command.
     */
    protected function setUpTraits(InputInterface $input, OutputInterface $output): array
    {
        $uses = array_flip(class_uses_recursive(static::class));

        foreach ($uses as $trait) {
            if (method_exists($this, $method = 'setUp' . class_basename($trait))) {
                $this->{$method}($input, $output);
            }
        }

        return $uses;
    }
}
