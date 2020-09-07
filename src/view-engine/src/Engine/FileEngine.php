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
namespace Hyperf\ViewEngine\Engine;

use Hyperf\ViewEngine\Contract\EngineInterface;

class FileEngine implements EngineInterface
{
    /**
     * Get the evaluated contents of the view.
     *
     * @return string
     */
    public function get(string $path, array $data = [])
    {
        return file_get_contents($path);
    }
}
