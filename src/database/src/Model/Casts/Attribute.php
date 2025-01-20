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

namespace Hyperf\Database\Model\Casts;

use Closure;

class Attribute
{
    /**
     * The attribute accessor.
     */
    public ?Closure $get = null;

    /**
     * The attribute mutator.
     */
    public ?Closure $set = null;

    /**
     * Indicates if caching is enabled for this attribute.
     */
    public bool $withCaching = false;

    /**
     * Indicates if caching of objects is enabled for this attribute.
     */
    public bool $withObjectCaching = true;

    /**
     * Create a new attribute accessor / mutator.
     */
    public function __construct(?callable $get = null, ?callable $set = null)
    {
        $this->get = $get;
        $this->set = $set;
    }

    /**
     * Create a new attribute accessor / mutator.
     */
    public static function make(?callable $get = null, ?callable $set = null): static
    {
        return new static($get, $set);
    }

    /**
     * Create a new attribute accessor.
     */
    public static function get(callable $get): static
    {
        return new static($get);
    }

    /**
     * Create a new attribute mutator.
     */
    public static function set(callable $set): static
    {
        return new static(null, $set);
    }

    /**
     * Disable object caching for the attribute.
     */
    public function withoutObjectCaching(): static
    {
        $this->withObjectCaching = false;

        return $this;
    }

    /**
     * Enable caching for the attribute.
     */
    public function shouldCache(): static
    {
        $this->withCaching = true;

        return $this;
    }
}
