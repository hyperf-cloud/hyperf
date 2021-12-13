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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Arr;
use Hyperf\Utils\CodeGen\Project;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class GeneratorCommand extends Command
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

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
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
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
     *
     * @param string $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        $name = str_replace('/', '\\', $name);

        $namespace = $this->input->getOption('namespace');
        if (empty($namespace)) {
            $namespace = $this->getDefaultNamespace();
        }

        return $namespace . '\\' . $name;
    }

    /**
     * Determine if the class already exists.
     *
     * @param string $rawName
     * @return bool
     */
    protected function alreadyExists($rawName)
    {
        return is_file($this->getPath($this->qualifyClass($rawName)));
    }

    /**
     * Get the destination class path.
     *
     * @param string $name
     * @return string
     */
    protected function getPath($name)
    {
        $project = new Project();
        return BASE_PATH . '/' . $project->path($name);
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param string $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        return $path;
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = file_get_contents($this->getStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
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
     *
     * @param string $name
     * @return string
     */
    protected function getNamespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        return str_replace('%CLASS%', $class, $stub);
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->input->getArgument('name'));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the class'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
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
     *
     * @param string $ide
     * @return string
     */
    protected function getEditorUrl($ide)
    {
        switch ($ide) {
            case 'sublime':
                return 'subl://open?url=file://%path';
            case 'textmate':
                return 'txmt://open?url=file://%path';
            case 'emacs':
                return 'emacs://open?url=file://%path';
            case 'macvim':
                return 'mvim://open/?url=file://%path';
            case 'phpstorm':
                return 'phpstorm://open?file=%path';
            case 'idea':
                return 'idea://open?file=%path';
            case 'vscode':
                return 'vscode://file/%path';
            case 'vscode-insiders':
                return 'vscode-insiders://file/%path';
            case 'vscode-remote':
                return 'vscode://vscode-remote/%path';
            case 'vscode-insiders-remote':
                return 'vscode-insiders://vscode-remote/%path';
            case 'atom':
                return 'atom://core/open/file?filename=%path';
            case 'nova':
                return 'nova://core/open/file?filename=%path';
            case 'netbeans':
                return 'netbeans://open/?f=%path';
            case 'xdebug':
                return 'xdebug://%path';
            default:
                return '';
        }
    }

    /**
     * Open resulted file path with the configured IDE.
     *
     * @param string $path
     * @return false|string|void
     */
    protected function openWithIde($path)
    {
        $ide = $this->getContainer()->get(ConfigInterface::class)->get('devtool.ide');
        $openEditorUrl = $this->getEditorUrl($ide);

        if (! $openEditorUrl) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return exec('explorer ' . str_replace('%path', $path, $openEditorUrl));
        }

        if (PHP_OS_FAMILY === 'Linux') {
            return exec('xdg-open ' . str_replace('%path', $path, $openEditorUrl));
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            return exec('open ' . str_replace('%path', $path, $openEditorUrl));
        }
    }
}
