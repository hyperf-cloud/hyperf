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

namespace HyperfTest\Database\Stubs;

use Hyperf\Database\Model\Casts\Attribute;
use Hyperf\Database\Model\Concerns\HasAttributes;

class HasAttributesWithoutConstructor
{
    use HasAttributes;

    public function someAttribute(): Attribute
    {
        return new Attribute(function () {});
    }
}
