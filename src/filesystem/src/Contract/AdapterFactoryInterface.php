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
namespace Hyperf\Filesystem\Contract;

use League\Flysystem\AdapterInterface;
use League\Flysystem\FilesystemAdapter;

interface AdapterFactoryInterface
{
    public function make(array $options): \AdapterInterface|\League\Flysystem\FilesystemAdapter;
}
