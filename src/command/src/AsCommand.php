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

use Hyperf\Command\Concerns\InteractsWithIO;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\class_uses_recursive;

final class AsCommand extends Command
{
    private ParameterParser $parameterParser;

    public function __construct(
        private ContainerInterface $container,
        string $signature,
        private string $class,
        private string $method,
    ) {
        $this->signature = $signature;
        $this->parameterParser = $container->get(ParameterParser::class);

        parent::__construct();
    }

    public function handle()
    {
        $inputs = array_merge($this->input->getArguments(), $this->input->getOptions());
        $parameters = $this->parameterParser->parseMethodParameters($this->class, $this->method, $inputs);
        /** @var InteractsWithIO $instance */
        $instance = $this->container->get($this->class);

        if (in_array(InteractsWithIO::class, class_uses_recursive($this->class))) {
            $instance->setInput($this->input);
            $instance->setOutput($this->output);
        }

        return (fn ($method) => $this->{$method}(...$parameters))->call($instance, $this->method);
    }
}
