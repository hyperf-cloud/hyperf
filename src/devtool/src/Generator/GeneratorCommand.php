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

namespace Hyperf\Devtool\Generator;

use Hyperf\CodeParser\Project;
use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Stringable\Str;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class GeneratorCommand extends Command
{
    protected ?InputInterface $input = null;

    protected ?OutputInterface $output = null;

    public function configure()
    {
        foreach ($this->getArguments() as $argument) {
            $this->addArgument(...$argument);
        }

        foreach ($this->getOptions() as $option) {
            $this->addOption(...$option);
        }
    }

    /**
     * Execute the console command.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $name = $this->qualifyClass($this->getNameInput());

        $path = $this->getPath($name);

        // First we will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if (($input->getOption('force') === false) && $this->alreadyExists($this->getNameInput())) {
            $output->writeln(sprintf('<fg=red>%s</>', $name . ' already exists!'));
            return 0;
        }

        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->makeDirectory($path);

        file_put_contents($path, $this->buildClass($name));

        $output->writeln(sprintf('<info>%s</info>', $name . ' created successfully.'));

        $this->openWithIde($path);

        return 0;
    }

    /**
     * Parse the class name and format according to the root namespace.
     */
    protected function qualifyClass(string $name): string
    {
        $name = ltrim($name, '\/');

        $name = str_replace('/', '\\', $name);

        $namespace = $this->input->getOption('namespace');
        if (empty($namespace)) {
            $namespace = $this->getDefaultNamespace();
        }

        return $namespace . '\\' . $name;
    }

    /**
     * Determine if the class already exists.
     */
    protected function alreadyExists(string $rawName): bool
    {
        return is_file($this->getPath($this->qualifyClass($rawName)));
    }

    /**
     * Get the destination class path.
     */
    protected function getPath(string $name): string
    {
        $project = new Project();
        return BASE_PATH . '/' . $project->path($name);
    }

    /**
     * Build the directory for the class if necessary.
     */
    protected function makeDirectory(string $path): string
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        return $path;
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass(string $name): string
    {
        $stub = file_get_contents($this->getStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Replace the namespace for the given stub.
     */
    protected function replaceNamespace(string &$stub, string $name): static
    {
        $stub = str_replace(
            ['%NAMESPACE%'],
            [$this->getNamespace($name)],
            $stub
        );

        return $this;
    }

    /**
     * Get the full namespace for a given class, without the class name.
     */
    protected function getNamespace(string $name): string
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace the class name for the given stub.
     */
    protected function replaceClass(string $stub, string $name): string
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        return str_replace('%CLASS%', $class, $stub);
    }

    /**
     * Get the desired class name from the input.
     */
    protected function getNameInput(): string
    {
        return trim($this->input->getArgument('name'));
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Whether force to rewrite.'],
            ['namespace', 'N', InputOption::VALUE_OPTIONAL, 'The namespace for class.', null],
        ];
    }

    /**
     * Get the custom config for generator.
     */
    protected function getConfig(): array
    {
        $class = Arr::last(explode('\\', static::class));
        $class = Str::replaceLast('Command', '', $class);
        $key = 'devtool.generator.' . Str::snake($class, '.');
        return $this->getContainer()->get(ConfigInterface::class)->get($key) ?? [];
    }

    protected function getContainer(): ContainerInterface
    {
        return ApplicationContext::getContainer();
    }

    /**
     * Get the stub file for the generator.
     */
    abstract protected function getStub(): string;

    /**
     * Get the default namespace for the class.
     */
    abstract protected function getDefaultNamespace(): string;

    /**
     * Get the editor file opener URL by its name.
     */
    protected function getEditorUrl(string $ide): string
    {
        switch ($ide) {
            case 'sublime':
                return 'subl://open?url=file://%s';
            case 'textmate':
                return 'txmt://open?url=file://%s';
            case 'cursor':
                return 'cursor://file/%s';
            case 'emacs':
                return 'emacs://open?url=file://%s';
            case 'macvim':
                return 'mvim://open/?url=file://%s';
            case 'phpstorm':
                return 'phpstorm://open?file=%s';
            case 'idea':
                return 'idea://open?file=%s';
            case 'vscode':
                return 'vscode://file/%s';
            case 'vscode-insiders':
                return 'vscode-insiders://file/%s';
            case 'vscode-remote':
                return 'vscode://vscode-remote/%s';
            case 'vscode-insiders-remote':
                return 'vscode-insiders://vscode-remote/%s';
            case 'atom':
                return 'atom://core/open/file?filename=%s';
            case 'nova':
                return 'nova://core/open/file?filename=%s';
            case 'netbeans':
                return 'netbeans://open/?f=%s';
            case 'xdebug':
                return 'xdebug://%s';
            default:
                return '';
        }
    }

    /**
     * Open resulted file path with the configured IDE.
     */
    protected function openWithIde(string $path): void
    {
        $ide = (string) $this->getContainer()->get(ConfigInterface::class)->get('devtool.ide');
        $openEditorUrl = $this->getEditorUrl($ide);

        if (! $openEditorUrl) {
            return;
        }

        $url = sprintf($openEditorUrl, $path);
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                exec('explorer ' . $url);
                break;
            case 'Linux':
                exec('xdg-open ' . $url);
                break;
            case 'Darwin':
                exec('open ' . $url);
                break;
        }
    }
}
