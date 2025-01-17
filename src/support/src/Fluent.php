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

namespace Hyperf\Support;

use ArrayAccess;
use Closure;
use Hyperf\Collection\Arr;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use JsonSerializable;

use function Hyperf\Collection\data_get;
use function Hyperf\Collection\data_set;

/**
 * Most of the methods in this file come from illuminate/support,
 * thanks Laravel Team provide such a useful class.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements \Hyperf\Contract\Arrayable<TKey, TValue>
 * @implements \ArrayAccess<TKey, TValue>
 */
class Fluent implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    use Traits\InteractsWithData;

    /**
     * All the attributes set on the fluent instance.
     *
     * @var array<TKey, TValue>
     */
    protected $attributes = [];

    /**
     * Create a new fluent instance.
     *
     * @param iterable<TKey, TValue> $attributes
     */
    public function __construct($attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Handle dynamic calls to the fluent instance to set attributes.
     *
     * @param TKey $method
     * @param array $parameters
     * @return $this
     */
    public function __call($method, $parameters)
    {
        $this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;

        return $this;
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param TKey $key
     * @return null|TValue
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Dynamically set the value of an attribute.
     *
     * @param TKey $key
     * @param TValue $value
     */
    public function __set($key, $value)
    {
        $this->offsetSet($key, $value);
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param TKey $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Dynamically unset an attribute.
     *
     * @param TKey $key
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Get an attribute from the fluent instance.
     *
     * @template TGetDefault
     *
     * @param TKey $key
     * @param (Closure(): TGetDefault)|TGetDefault $default
     * @return TGetDefault|TValue
     */
    public function get($key, $default = null)
    {
        return data_get($this->attributes, $key, $default);
    }

    /**
     * Get an attribute from the fluent instance.
     *
     * @param TKey $key
     * @param (Closure(): TGetDefault)|TGetDefault $default
     * @return TGetDefault|TValue
     */
    public function value($key, $default = null)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return value($default);
    }

    /**
     * Set an attribute on the fluent instance using "dot" notation.
     *
     * @param TKey $key
     * @param TValue $value
     * @return $this
     */
    public function set($key, $value)
    {
        data_set($this->attributes, $key, $value);

        return $this;
    }

    /**
     * Get the value of the given key as a new Fluent instance.
     *
     * @param string $key
     * @param mixed $default
     * @return static
     */
    public function scope($key, $default = null)
    {
        return new static(
            (array) $this->get($key, $default)
        );
    }

    /**
     * Get all of the attributes from the fluent instance.
     *
     * @param null|array|mixed $keys
     * @return array
     */
    public function all($keys = null)
    {
        $data = $this->data();
        if (! $keys) {
            return $data;
        }
        $results = [];
        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Arr::set($results, $key, Arr::get($data, $key));
        }
        return $results;
    }

    /**
     * Get the attributes from the fluent instance.
     *
     * @return array<TKey, TValue>
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Convert the fluent instance to an array.
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Convert the fluent instance to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param TKey $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get the value for a given offset.
     *
     * @param TKey $offset
     * @return null|TValue
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param TKey $offset
     * @param TValue $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Unset the value at the given offset.
     *
     * @param TKey $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get data from the fluent instance.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function data($key = null, $default = null)
    {
        return $this->get($key, $default);
    }
}
