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
namespace Hyperf\Devtool\Generator;

use Hyperf\Command\Annotation\Command;

/**
 * @Command
 */
class ConstantsCommand extends GeneratorCommand
{
    public function __construct()
    {
        parent::__construct('gen:constants');
        $this->setDescription('Create a new constants class');
    }

    protected function getStub(): string
    {
        return $this->getConfig()['stub'] ?? __DIR__ . '/stubs/constants.stub';
    }

    protected function getDefaultNamespace(): string
    {
        return $this->getConfig()['namespace'] ?? 'App\\Constants';
    }
}
