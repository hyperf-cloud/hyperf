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
namespace Hyperf\Validation\Rules;

use Closure;
use InvalidArgumentException;
use Stringable;

class ExcludeIf implements Stringable
{
    /**
     * Create a new exclude validation rule based on a condition.
     * @param bool|Closure $condition the condition that validates the attribute
     * @throws InvalidArgumentException
     */
    public function __construct(public Closure|bool $condition)
    {
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    public function __toString()
    {
        if (is_callable($this->condition)) {
            return call_user_func($this->condition) ? 'exclude' : '';
        }

        return $this->condition ? 'exclude' : '';
    }
}