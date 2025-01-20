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
use Hyperf\Database\Model\Model;

/**
 * @property string $cacheableProperty
 */
class HasCacheableAttributeWithAccessor extends Model
{
    public function cacheableProperty(): Attribute
    {
        return Attribute::make(
            get: fn () => 'foo'
        )->shouldCache();
    }

    public function cachedAttributeIsset($attribute): bool
    {
        return isset($this->attributeCastCache[$attribute]);
    }
}
