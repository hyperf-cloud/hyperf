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

namespace Hyperf\Contract;

use Stringable;

interface Jsonable extends Stringable
{
    public function __toString(): string;

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson(int $options = 0): string;
}
