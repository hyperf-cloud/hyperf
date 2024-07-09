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

namespace Hyperf\Collection;

use ArrayIterator;
use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Exception;
use Generator;
use Hyperf\Collection\Traits\EnumeratesValues;
use Hyperf\Contract\Arrayable;
use Hyperf\Macroable\Macroable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use stdClass;
use Traversable;

/**
 * @template TKey of array-key
 *
 * @template TValue
 *
 * @implements Enumerable<TKey, TValue>
 * /
 */
class LazyCollection implements Enumerable
{
    /**
     * @use EnumeratesValues<TKey, TValue>
     */
    use EnumeratesValues;

    use Macroable;

    /**
     * The source from which to generate items.
     *
     * @var array<TKey, TValue>|(Closure(): Generator<TKey, TValue, mixed, void>)|static
     */
    public $source;

    /**
     * Create a new lazy collection instance.
     *
     * @param null|array<TKey, TValue>|Arrayable<TKey, TValue>|(Closure(): Generator<TKey, TValue, mixed, void>)|iterable<TKey, TValue>|self<TKey, TValue> $source
     */
    public function __construct($source = null)
    {
        if ($source instanceof Closure || $source instanceof self) {
            $this->source = $source;
        } elseif (is_null($source)) {
            $this->source = static::empty();
        } elseif ($source instanceof Generator) {
            throw new InvalidArgumentException(
                'Generators should not be passed directly to LazyCollection. Instead, pass a generator function.'
            );
        } else {
            $this->source = $this->getArrayableItems($source);
        }
    }

    /**
     * Create a new collection instance if the value isn't one already.
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     *
     * @param null|array<TMakeKey, TMakeValue>|Arrayable<TMakeKey, TMakeValue>|(Closure(): Generator<TMakeKey, TMakeValue, mixed, void>)|iterable<TMakeKey, TMakeValue>|self<TMakeKey, TMakeValue> $items
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make(mixed $items = [])
    {
        return new static($items);
    }

    /**
     * Create a collection with the given range.
     *
     * @return static<int, int>
     */
    public static function range(float|int|string $from, float|int|string $to)
    {
        return new static(function () use ($from, $to) {
            if ($from <= $to) {
                for (; $from <= $to; ++$from) {
                    yield $from;
                }
            } else {
                for (; $from >= $to; --$from) {
                    yield $from;
                }
            }
        });
    }

    /**
     * Get all items in the enumerable.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        if (is_array($this->source)) {
            return $this->source;
        }

        return iterator_to_array($this->getIterator());
    }

    /**
     * Eager load all items into a new lazy collection backed by an array.
     *
     * @return static
     */
    public function eager()
    {
        return new static($this->all());
    }

    /**
     * Cache values as they're enumerated.
     *
     * @return static
     */
    public function remember()
    {
        $iterator = $this->getIterator();

        $iteratorIndex = 0;

        $cache = [];

        return new static(function () use ($iterator, &$iteratorIndex, &$cache) {
            for ($index = 0; true; ++$index) {
                if (array_key_exists($index, $cache)) {
                    yield $cache[$index][0] => $cache[$index][1];

                    continue;
                }

                if ($iteratorIndex < $index) {
                    $iterator->next();

                    ++$iteratorIndex;
                }

                if (! $iterator->valid()) {
                    break;
                }

                $cache[$index] = [$iterator->key(), $iterator->current()];

                yield $cache[$index][0] => $cache[$index][1];
            }
        });
    }

    /**
     * Get the median of a given key.
     *
     * @param null|array<array-key, string>|string $key
     * @return null|float|int
     */
    public function median($key = null)
    {
        return $this->collect()->median($key);
    }

    /**
     * Get the mode of a given key.
     *
     * @param null|array<string>|string $key
     * @return null|array<int, float|int>
     */
    public function mode($key = null)
    {
        return $this->collect()->mode($key);
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static<int, mixed>
     */
    public function collapse()
    {
        return new static(function () {
            foreach ($this as $values) {
                if (is_array($values) || $values instanceof Enumerable) {
                    foreach ($values as $value) {
                        yield $value;
                    }
                }
            }
        });
    }

    /**
     * Determine if an item exists in the enumerable.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     * @param mixed $operator
     * @param mixed $value
     */
    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1 && $this->useAsCallable($key)) {
            $placeholder = new stdClass();

            /* @var callable $key */
            return $this->first($key, $placeholder) !== $placeholder;
        }

        if (func_num_args() === 1) {
            $needle = $key;

            foreach ($this as $value) {
                if ($value == $needle) {
                    return true;
                }
            }

            return false;
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Determine if an item exists, using strict comparison.
     *
     * @param array-key|(callable(TValue): bool)|TValue $key
     * @param null|TValue $value
     */
    public function containsStrict($key, $value = null): bool
    {
        if (func_num_args() === 2) {
            return $this->contains(fn ($item) => data_get($item, $key) === $value);
        }

        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }

        foreach ($this as $item) {
            if ($item === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if an item is not contained in the enumerable.
     *
     * @param mixed $key
     * @param mixed $operator
     * @param mixed $value
     */
    public function doesntContain($key, $operator = null, $value = null): bool
    {
        return ! $this->contains(...func_get_args());
    }

    /**
     * Cross join the given iterables, returning all possible permutations.
     *
     * @template TCrossJoinKey
     * @template TCrossJoinValue
     *
     * @param Arrayable<TCrossJoinKey, TCrossJoinValue>|iterable<TCrossJoinKey, TCrossJoinValue> ...$arrays
     * @return static<int, array<int, TCrossJoinValue|TValue>>
     */
    public function crossJoin(...$arrays)
    {
        return $this->passthru('crossJoin', func_get_args());
    }

    /**
     * Count the number of items in the collection by a field or using a callback.
     *
     * @param null|(callable(TValue, TKey): array-key)|string $countBy
     * @return static<array-key, int>
     */
    public function countBy($countBy = null)
    {
        $countBy = is_null($countBy)
            ? $this->identity()
            : $this->valueRetriever($countBy);

        return new static(function () use ($countBy) {
            $counts = [];

            foreach ($this as $key => $value) {
                $group = $countBy($value, $key);

                if (empty($counts[$group])) {
                    $counts[$group] = 0;
                }

                ++$counts[$group];
            }

            yield from $counts;
        });
    }

    /**
     * Get the items that are not present in the given items.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     */
    public function diff($items)
    {
        return $this->passthru('diff', func_get_args());
    }

    /**
     * Get the items that are not present in the given items, using the callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int $callback
     * @return static
     */
    public function diffUsing($items, callable $callback)
    {
        return $this->passthru('diffUsing', func_get_args());
    }

    /**
     * Get the items whose keys and values are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function diffAssoc($items)
    {
        return $this->passthru('diffAssoc', func_get_args());
    }

    /**
     * Get the items whose keys and values are not present in the given items, using the callback.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @param callable(TKey, TKey): int $callback
     * @return static
     */
    public function diffAssocUsing($items, callable $callback)
    {
        return $this->passthru('diffAssocUsing', func_get_args());
    }

    /**
     * Get the items whose keys are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function diffKeys($items)
    {
        return $this->passthru('diffKeys', func_get_args());
    }

    /**
     * Get the items whose keys are not present in the given items, using the callback.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @param callable(TKey, TKey): int $callback
     * @return static
     */
    public function diffKeysUsing($items, callable $callback)
    {
        return $this->passthru('diffKeysUsing', func_get_args());
    }

    /**
     * Retrieve duplicate items.
     *
     * @param null|(callable(TValue): bool)|string $callback
     * @param bool $strict
     * @return static
     */
    public function duplicates($callback = null, $strict = false)
    {
        return $this->passthru('duplicates', func_get_args());
    }

    /**
     * Retrieve duplicate items using strict comparison.
     *
     * @param null|(callable(TValue): bool)|string $callback
     * @return static
     */
    public function duplicatesStrict($callback = null)
    {
        return $this->passthru('duplicatesStrict', func_get_args());
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param array<array-key, TKey>|Enumerable<array-key, TKey> $keys
     */
    public function except($keys)
    {
        return $this->passthru('except', func_get_args());
    }

    /**
     * Run a filter over each of the items.
     *
     * @param null|(callable(TValue, TKey): bool) $callback
     */
    public function filter(?callable $callback = null)
    {
        if (is_null($callback)) {
            $callback = fn ($value) => (bool) $value;
        }

        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Get the first item from the enumerable passing the given truth test.
     *
     * @template TFirstDefault
     *
     * @param null|(callable(TValue,TKey): bool) $callback
     * @param (Closure(): TFirstDefault)|TFirstDefault $default
     * @return TFirstDefault|TValue
     */
    public function first(?callable $callback = null, $default = null)
    {
        /** @var Iterator $iterator */
        $iterator = $this->getIterator();

        if (is_null($callback)) {
            if (! $iterator->valid()) {
                return value($default);
            }

            return $iterator->current();
        }

        foreach ($iterator as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return value($default);
    }

    /**
     * Get a flattened list of the items in the collection.
     *
     * @return static<int, mixed>
     */
    public function flatten(float|int $depth = INF)
    {
        $instance = new static(function () use ($depth) {
            foreach ($this as $item) {
                if (! is_array($item) && ! $item instanceof Enumerable) {
                    yield $item;
                } elseif ($depth <= 1) {
                    yield from $item;
                } else {
                    yield from (new static($item))->flatten($depth - 1);
                }
            }
        });

        return $instance->values();
    }

    /**
     * Flip the items in the collection.
     *
     * @return static<TValue, TKey>
     */
    public function flip()
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $value => $key;
            }
        });
    }

    /**
     * Get an item by key.
     *
     * @template TGetDefault
     *
     * @param null|TKey $key
     * @param (Closure(): TGetDefault)|TGetDefault $default
     * @return TGetDefault|TValue
     */
    public function get($key, $default = null)
    {
        if (is_null($key)) {
            return;
        }

        foreach ($this as $outerKey => $outerValue) {
            if ($outerKey == $key) {
                return $outerValue;
            }
        }

        return value($default);
    }

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param array|(callable(TValue, TKey): array-key)|string $groupBy
     * @return static<array-key, static<array-key, TValue>>
     */
    public function groupBy($groupBy, bool $preserveKeys = false)
    {
        return $this->passthru('groupBy', func_get_args());
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param array|(callable(TValue, TKey): array-key)|string $keyBy
     * @return static<array-key, TValue>
     */
    public function keyBy($keyBy)
    {
        return new static(function () use ($keyBy) {
            $keyBy = $this->valueRetriever($keyBy);

            foreach ($this as $key => $item) {
                $resolvedKey = $keyBy($item, $key);

                if (is_object($resolvedKey)) {
                    $resolvedKey = (string) $resolvedKey;
                }

                yield $resolvedKey => $item;
            }
        });
    }

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param mixed $key
     */
    public function has($key): bool
    {
        $keys = array_flip(is_array($key) ? $key : func_get_args());
        $count = count($keys);

        foreach ($this as $key => $value) {
            if (array_key_exists($key, $keys) && --$count == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if any of the keys exist in the collection.
     *
     * @param mixed $key
     */
    public function hasAny($key): bool
    {
        $keys = array_flip(is_array($key) ? $key : func_get_args());

        foreach ($this as $key => $value) {
            if (array_key_exists($key, $keys)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Concatenate values of a given key as a string.
     */
    public function implode(array|callable|string $value, ?string $glue = null): string
    {
        return $this->collect()->implode(...func_get_args());
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     */
    public function intersect(mixed $items)
    {
        return $this->passthru('intersect', func_get_args());
    }

    /**
     * Intersect the collection with the given items, using the callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int $callback
     * @return static
     */
    public function intersectUsing($items, callable $callback)
    {
        return $this->passthru('intersectUsing', func_get_args());
    }

    /**
     * Intersect the collection with the given items with additional index check.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function intersectAssoc($items)
    {
        return $this->passthru('intersectAssoc', func_get_args());
    }

    /**
     * Intersect the collection with the given items with additional index check, using the callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int $callback
     * @return static
     */
    public function intersectAssocUsing($items, callable $callback)
    {
        return $this->passthru('intersectAssocUsing', func_get_args());
    }

    /**
     * Intersect the collection with the given items by key.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function intersectByKeys($items)
    {
        return $this->passthru('intersectByKeys', func_get_args());
    }

    /**
     * Determine if the items are empty or not.
     */
    public function isEmpty(): bool
    {
        return ! $this->getIterator()->valid();
    }

    /**
     * Determine if the collection contains a single item.
     */
    public function containsOneItem(): bool
    {
        return $this->take(2)->count() === 1;
    }

    /**
     * Join all items from the collection using a string. The final items can use a separate glue string.
     *
     * @param string $glue
     * @param string $finalGlue
     * @return string
     */
    public function join($glue, $finalGlue = '')
    {
        return $this->collect()->join(...func_get_args());
    }

    /**
     * Get the keys of the collection items.
     *
     * @return static<int, TKey>
     */
    public function keys()
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $key;
            }
        });
    }

    /**
     * Get the last item from the collection.
     *
     * @template TLastDefault
     *
     * @param null|(callable(TValue, TKey): bool) $callback
     * @param (Closure(): TLastDefault)|TLastDefault $default
     * @return TLastDefault|TValue
     */
    public function last(?callable $callback = null, $default = null)
    {
        $needle = $placeholder = new stdClass();

        foreach ($this as $key => $value) {
            if (is_null($callback) || $callback($value, $key)) {
                $needle = $value;
            }
        }

        return $needle === $placeholder ? value($default) : $needle;
    }

    /**
     * Get the values of a given key.
     *
     * @param array<array-key, string>|string $value
     * @return static<int, mixed>
     */
    public function pluck(array|string $value, ?string $key = null)
    {
        return new static(function () use ($value, $key) {
            [$value, $key] = $this->explodePluckParameters($value, $key);

            foreach ($this as $item) {
                $itemValue = data_get($item, $value);

                if (is_null($key)) {
                    yield $itemValue;
                } else {
                    $itemKey = data_get($item, $key);

                    if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                        $itemKey = (string) $itemKey;
                    }

                    yield $itemKey => $itemValue;
                }
            }
        });
    }

    /**
     * Run a map over each of the items.
     *
     * @template TMapValue
     *
     * @param callable(TValue, TKey): TMapValue $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback)
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Run a dictionary map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @template TMapToDictionaryKey of array-key
     * @template TMapToDictionaryValue
     *
     * @param callable(TValue, TKey): array<TMapToDictionaryKey, TMapToDictionaryValue> $callback
     * @return static<TMapToDictionaryKey, array<int, TMapToDictionaryValue>>
     */
    public function mapToDictionary(callable $callback)
    {
        return $this->passthru('mapToDictionary', func_get_args());
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @template TMapWithKeysKey of array-key
     * @template TMapWithKeysValue
     *
     * @param callable(TValue, TKey): array<TMapWithKeysKey, TMapWithKeysValue> $callback
     * @return static<TMapWithKeysKey, TMapWithKeysValue>
     */
    public function mapWithKeys(callable $callback)
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield from $callback($value, $key);
            }
        });
    }

    /**
     * Merge the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function merge($items)
    {
        return $this->passthru('merge', func_get_args());
    }

    /**
     * Recursively merge the collection with the given items.
     *
     * @template TMergeRecursiveValue
     *
     * @param Arrayable<TKey, TMergeRecursiveValue>|iterable<TKey, TMergeRecursiveValue> $items
     * @return static<TKey, TMergeRecursiveValue|TValue>
     */
    public function mergeRecursive($items)
    {
        return $this->passthru('mergeRecursive', func_get_args());
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @template TCombineValue
     *
     * @param array<array-key, TCombineValue>|(callable(): Generator<array-key, TCombineValue>)|IteratorAggregate<array-key, TCombineValue> $values
     * @return static<TValue, TCombineValue>
     */
    public function combine($values)
    {
        return new static(function () use ($values) {
            $values = $this->makeIterator($values);

            $errorMessage = 'Both parameters should have an equal number of elements';

            foreach ($this as $key) {
                if (! $values->valid()) {
                    trigger_error($errorMessage, E_USER_WARNING);

                    break;
                }

                yield $key => $values->current();

                $values->next();
            }

            if ($values->valid()) {
                trigger_error($errorMessage, E_USER_WARNING);
            }
        });
    }

    /**
     * Union the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function union($items)
    {
        return $this->passthru('union', func_get_args());
    }

    /**
     * Create a new collection consisting of every n-th element.
     */
    public function nth(int $step, int $offset = 0)
    {
        return new static(function () use ($step, $offset) {
            $position = 0;

            foreach ($this->slice($offset) as $item) {
                if ($position % $step === 0) {
                    yield $item;
                }

                ++$position;
            }
        });
    }

    /**
     * Get the items with the specified keys.
     *
     * @param null|array<array-key, TKey>|Enumerable<array-key, TKey>|string $keys
     */
    public function only($keys)
    {
        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        } elseif (! is_null($keys)) {
            $keys = is_array($keys) ? $keys : func_get_args();
        }

        return new static(function () use ($keys) {
            if (is_null($keys)) {
                yield from $this;
            } else {
                $keys = array_flip($keys);

                foreach ($this as $key => $value) {
                    if (array_key_exists($key, $keys)) {
                        yield $key => $value;

                        unset($keys[$key]);

                        if (empty($keys)) {
                            break;
                        }
                    }
                }
            }
        });
    }

    /**
     * Select specific values from the items within the collection.
     *
     * @param null|array<array-key, TKey>|Enumerable<array-key, TKey>|string $keys
     * @return static
     */
    public function select($keys)
    {
        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        } elseif (! is_null($keys)) {
            $keys = is_array($keys) ? $keys : func_get_args();
        }

        return new static(function () use ($keys) {
            if (is_null($keys)) {
                yield from $this;
            } else {
                foreach ($this as $item) {
                    $result = [];

                    foreach ($keys as $key) {
                        if (Arr::accessible($item) && Arr::exists($item, $key)) {
                            $result[$key] = $item[$key];
                        } elseif (is_object($item) && isset($item->{$key})) {
                            $result[$key] = $item->{$key};
                        }
                    }

                    yield $result;
                }
            }
        });
    }

    /**
     * Push all of the given items onto the collection.
     *
     * @template TConcatKey of array-key
     * @template TConcatValue
     *
     * @param iterable<TConcatKey, TConcatValue> $source
     * @return static<TConcatKey|TKey, TConcatValue|TValue>
     */
    public function concat($source)
    {
        return (new static(function () use ($source) {
            yield from $this;
            yield from $source;
        }))->values();
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @return static<int, TValue>|TValue
     *
     * @throws InvalidArgumentException
     */
    public function random(?int $number = null)
    {
        $result = $this->collect()->random(...func_get_args());

        return is_null($number) ? $result : new static($result);
    }

    /**
     * Replace the collection items with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function replace($items)
    {
        return new static(function () use ($items) {
            $items = $this->getArrayableItems($items);

            foreach ($this as $key => $value) {
                if (array_key_exists($key, $items)) {
                    yield $key => $items[$key];

                    unset($items[$key]);
                } else {
                    yield $key => $value;
                }
            }

            foreach ($items as $key => $value) {
                yield $key => $value;
            }
        });
    }

    /**
     * Recursively replace the collection items with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function replaceRecursive($items)
    {
        return $this->passthru('replaceRecursive', func_get_args());
    }

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse()
    {
        return $this->passthru('reverse', func_get_args());
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     * @return false|TKey
     */
    public function search($value, bool $strict = false)
    {
        /** @var (callable(TValue,TKey): bool) $predicate */
        $predicate = $this->useAsCallable($value)
            ? $value
            : function ($item) use ($value, $strict) {
                return $strict ? $item === $value : $item == $value;
            };

        foreach ($this as $key => $item) {
            if ($predicate($item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get the item before the given item.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     * @return null|TValue
     */
    public function before(mixed $value, bool $strict = false): mixed
    {
        $previous = null;

        /** @var (callable(TValue,TKey): bool) $predicate */
        $predicate = $this->useAsCallable($value)
            ? $value
            : function ($item) use ($value, $strict) {
                return $strict ? $item === $value : $item == $value;
            };

        foreach ($this as $key => $item) {
            if ($predicate($item, $key)) {
                return $previous;
            }

            $previous = $item;
        }

        return null;
    }

    /**
     * Get the item after the given item.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     * @return null|TValue
     */
    public function after(mixed $value, bool $strict = false): mixed
    {
        $found = false;

        /** @var (callable(TValue,TKey): bool) $predicate */
        $predicate = $this->useAsCallable($value)
            ? $value
            : function ($item) use ($value, $strict) {
                return $strict ? $item === $value : $item == $value;
            };

        foreach ($this as $key => $item) {
            if ($found) {
                return $item;
            }

            if ($predicate($item, $key)) {
                $found = true;
            }
        }

        return null;
    }

    /**
     * Shuffle the items in the collection.
     *
     * @return Collection
     */
    public function shuffle(?int $seed = null)
    {
        return $this->passthru('shuffle', []);
    }

    /**
     * Create chunks representing a "sliding window" view of the items in the collection.
     *
     * @return static<int, static>
     */
    public function sliding(int $size = 2, int $step = 1)
    {
        return new static(function () use ($size, $step) {
            $iterator = $this->getIterator();

            $chunk = [];

            while ($iterator->valid()) {
                $chunk[$iterator->key()] = $iterator->current();

                if (count($chunk) == $size) {
                    yield (new static($chunk))->tap(function () use (&$chunk, $step) {
                        $chunk = array_slice($chunk, $step, null, true);
                    });

                    // If the $step between chunks is bigger than each chunk's $size
                    // we will skip the extra items (which should never be in any
                    // chunk) before we continue to the next chunk in the loop.
                    if ($step > $size) {
                        $skip = $step - $size;

                        for ($i = 0; $i < $skip && $iterator->valid(); ++$i) {
                            $iterator->next();
                        }
                    }
                }

                $iterator->next();
            }
        });
    }

    /**
     * Skip the first {$count} items.
     */
    public function skip(int $count)
    {
        return new static(function () use ($count) {
            $iterator = $this->getIterator();

            while ($iterator->valid() && $count--) {
                $iterator->next();
            }

            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    /**
     * Skip items in the collection until the given condition is met.
     *
     * @param Closure(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function skipUntil($value)
    {
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return $this->skipWhile($this->negate($callback));
    }

    /**
     * Skip items in the collection while the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function skipWhile($value)
    {
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return new static(function () use ($callback) {
            $iterator = $this->getIterator();

            while ($iterator->valid() && $callback($iterator->current(), $iterator->key())) {
                $iterator->next();
            }

            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    /**
     * Get a slice of items from the enumerable.
     */
    public function slice(int $offset, ?int $length = null)
    {
        if ($offset < 0 || $length < 0) {
            return $this->passthru('slice', func_get_args());
        }

        $instance = $this->skip($offset);

        return is_null($length) ? $instance : $instance->take($length);
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @return static<int, static>
     */
    public function split(int $numberOfGroups)
    {
        return $this->passthru('split', func_get_args());
    }

    /**
     * Get the first item in the collection, but only if exactly one item exists. Otherwise, throw an exception.
     *
     * @param (callable(TValue, TKey): bool)|string $key
     * @param mixed $operator
     * @param mixed $value
     * @return TValue
     *
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public function sole($key = null, $operator = null, $value = null)
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        return $this
            ->unless($filter == null)
            ->filter($filter)
            ->take(2)
            ->collect()
            ->sole();
    }

    /**
     * Get the first item in the collection but throw an exception if no matching items exist.
     *
     * @param (callable(TValue, TKey): bool)|string $key
     * @param mixed $operator
     * @param mixed $value
     * @return TValue
     *
     * @throws ItemNotFoundException
     */
    public function firstOrFail($key = null, $operator = null, $value = null)
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        return $this
            ->unless($filter == null)
            ->filter($filter)
            ->take(1)
            ->collect()
            ->firstOrFail();
    }

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @return static<int, static>
     */
    public function chunk(int $size)
    {
        if ($size <= 0) {
            return static::empty();
        }

        return new static(function () use ($size) {
            $iterator = $this->getIterator();

            while ($iterator->valid()) {
                $chunk = [];

                while (true) {
                    $chunk[$iterator->key()] = $iterator->current();

                    if (count($chunk) < $size) {
                        $iterator->next();

                        if (! $iterator->valid()) {
                            break;
                        }
                    } else {
                        break;
                    }
                }

                yield new static($chunk);

                $iterator->next();
            }
        });
    }

    /**
     * Split a collection into a certain number of groups, and fill the first groups completely.
     *
     * @return static<int, static>
     */
    public function splitIn(int $numberOfGroups)
    {
        return $this->chunk((int) ceil($this->count() / $numberOfGroups));
    }

    /**
     * Chunk the collection into chunks with a callback.
     *
     * @param callable(TValue, TKey, Collection<TKey, TValue>): bool $callback
     * @return static<int, static<int, TValue>>
     */
    public function chunkWhile(callable $callback)
    {
        return new static(function () use ($callback) {
            $iterator = $this->getIterator();

            $chunk = new Collection();

            if ($iterator->valid()) {
                $chunk[$iterator->key()] = $iterator->current();

                $iterator->next();
            }

            while ($iterator->valid()) {
                if (! $callback($iterator->current(), $iterator->key(), $chunk)) {
                    yield new static($chunk);

                    $chunk = new Collection();
                }

                $chunk[$iterator->key()] = $iterator->current();

                $iterator->next();
            }

            if ($chunk->isNotEmpty()) {
                yield new static($chunk);
            }
        });
    }

    /**
     * Sort through each item with a callback.
     *
     * @param null|(callable(TValue, TValue): int) $callback
     */
    public function sort(?callable $callback = null)
    {
        return $this->passthru('sort', func_get_args());
    }

    /**
     * Sort items in descending order.
     */
    public function sortDesc(int $options = SORT_REGULAR)
    {
        return $this->passthru('sortDesc', func_get_args());
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param array<array-key, array{string, string}|(callable(TValue, TKey): mixed)|(callable(TValue, TValue): mixed)|string>|(callable(TValue, TKey): mixed)|string $callback
     * @param bool $descending
     */
    public function sortBy($callback, int $options = SORT_REGULAR, $descending = false)
    {
        return $this->passthru('sortBy', func_get_args());
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param array<array-key, array{string, string}|(callable(TValue, TKey): mixed)|(callable(TValue, TValue): mixed)|string>|(callable(TValue, TKey): mixed)|string $callback
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR)
    {
        return $this->passthru('sortByDesc', func_get_args());
    }

    /**
     * Sort the collection keys.
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false)
    {
        return $this->passthru('sortKeys', func_get_args());
    }

    /**
     * Sort the collection keys in descending order.
     */
    public function sortKeysDesc(int $options = SORT_REGULAR)
    {
        return $this->passthru('sortKeysDesc', func_get_args());
    }

    /**
     * Sort the collection keys using a callback.
     *
     * @param callable(TKey, TKey): int $callback
     */
    public function sortKeysUsing(callable $callback)
    {
        return $this->passthru('sortKeysUsing', func_get_args());
    }

    /**
     * Take the first or last {$limit} items.
     */
    public function take(int $limit)
    {
        if ($limit < 0) {
            return new static(function () use ($limit) {
                $limit = abs($limit);
                $ringBuffer = [];
                $position = 0;

                foreach ($this as $key => $value) {
                    $ringBuffer[$position] = [$key, $value];
                    $position = ($position + 1) % $limit;
                }

                for ($i = 0, $end = min($limit, count($ringBuffer)); $i < $end; ++$i) {
                    $pointer = ($position + $i) % $limit;
                    yield $ringBuffer[$pointer][0] => $ringBuffer[$pointer][1];
                }
            });
        }

        return new static(function () use ($limit) {
            $iterator = $this->getIterator();

            while ($limit--) {
                if (! $iterator->valid()) {
                    break;
                }

                yield $iterator->key() => $iterator->current();

                if ($limit) {
                    $iterator->next();
                }
            }
        });
    }

    /**
     * Take items in the collection until the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function takeUntil($value)
    {
        /** @var callable(TValue, TKey): bool $callback */
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return new static(function () use ($callback) {
            foreach ($this as $key => $item) {
                if ($callback($item, $key)) {
                    break;
                }

                yield $key => $item;
            }
        });
    }

    /**
     * Take items in the collection until a given point in time.
     *
     * @return static
     */
    public function takeUntilTimeout(DateTimeInterface $timeout)
    {
        $timeout = $timeout->getTimestamp();

        return new static(function () use ($timeout) {
            if ($this->now() >= $timeout) {
                return;
            }

            foreach ($this as $key => $value) {
                yield $key => $value;

                if ($this->now() >= $timeout) {
                    break;
                }
            }
        });
    }

    /**
     * Take items in the collection while the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function takeWhile($value)
    {
        /** @var callable(TValue, TKey): bool $callback */
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return $this->takeUntil(fn ($item, $key) => ! $callback($item, $key));
    }

    /**
     * Pass each item in the collection to the given callback, lazily.
     *
     * @param callable(TValue, TKey): mixed $callback
     * @return static
     */
    public function tapEach(callable $callback)
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                $callback($value, $key);

                yield $key => $value;
            }
        });
    }

    /**
     * Throttle the values, releasing them at most once per the given seconds.
     *
     * @return static<TKey, TValue>
     */
    public function throttle(float $seconds)
    {
        return new static(function () use ($seconds) {
            $microseconds = $seconds * 1_000_000;

            foreach ($this as $key => $value) {
                $fetchedAt = $this->preciseNow();

                yield $key => $value;

                $sleep = $microseconds - ($this->preciseNow() - $fetchedAt);

                $this->usleep((int) $sleep);
            }
        });
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @return static
     */
    public function dot()
    {
        return $this->passthru('dot', []);
    }

    /**
     * Convert a flatten "dot" notation array into an expanded array.
     */
    public function undot()
    {
        return $this->passthru('undot', []);
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param null|(callable(TValue, TKey): mixed)|string $key
     */
    public function unique(mixed $key = null, bool $strict = false)
    {
        $callback = $this->valueRetriever($key);

        return new static(function () use ($callback, $strict) {
            $exists = [];

            foreach ($this as $key => $item) {
                if (! in_array($id = $callback($item, $key), $exists, $strict)) {
                    yield $key => $item;

                    $exists[] = $id;
                }
            }
        });
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static<int, TValue>
     */
    public function values()
    {
        return new static(function () {
            foreach ($this as $item) {
                yield $item;
            }
        });
    }

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new LazyCollection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @template TZipValue
     *
     * @param Arrayable<array-key, TZipValue>|iterable<array-key, TZipValue> ...$items
     * @return static<int, static<int, TValue|TZipValue>>
     */
    public function zip($items)
    {
        $iterables = func_get_args();

        return new static(function () use ($iterables) {
            $iterators = Collection::make($iterables)->map(function ($iterable) {
                return $this->makeIterator($iterable);
            })->prepend($this->getIterator());

            while ($iterators->contains->valid()) {
                yield new static($iterators->map->current());

                $iterators->each->next();
            }
        });
    }

    /**
     * Pad collection to the specified length with a value.
     *
     * @template TPadValue
     *
     * @param TPadValue $value
     * @return static<int, TPadValue|TValue>
     */
    public function pad(int $size, $value)
    {
        if ($size < 0) {
            return $this->passthru('pad', func_get_args());
        }

        return new static(function () use ($size, $value) {
            $yielded = 0;

            foreach ($this as $index => $item) {
                yield $index => $item;

                ++$yielded;
            }

            while ($yielded++ < $size) {
                yield $value;
            }
        });
    }

    /**
     * Get the values iterator.
     * @return Iterator
     */
    public function getIterator(): Traversable
    {
        return $this->makeIterator($this->source);
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        if (is_array($this->source)) {
            return count($this->source);
        }

        return iterator_count($this->getIterator());
    }

    /**
     * Make an iterator from the given source.
     *
     * @template TIteratorKey of array-key
     * @template TIteratorValue
     *
     * @param array<TIteratorKey, TIteratorValue>|(callable(): mixed|Generator<TIteratorKey, TIteratorValue>)|IteratorAggregate<TIteratorKey, TIteratorValue> $source
     * @return Iterator
     * @throws Exception
     */
    protected function makeIterator($source)
    {
        if ($source instanceof IteratorAggregate) {
            return $source->getIterator();
        }

        if (is_array($source)) {
            return new ArrayIterator($source);
        }

        if (is_callable($source)) {
            $maybeTraversable = $source();

            return $maybeTraversable instanceof Traversable
                ? $maybeTraversable
                : new ArrayIterator(Arr::wrap($maybeTraversable));
        }

        return new ArrayIterator((array) $source);
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param string|string[] $value
     * @param null|string|string[] $key
     * @return array{string[],null|string[]}
     */
    protected function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode('.', $key);

        return [$value, $key];
    }

    /**
     * Pass this lazy collection through a method on the collection class.
     *
     * @param string $method
     * @param array<mixed> $params
     */
    protected function passthru($method, array $params)
    {
        return new static(function () use ($method, $params) {
            yield from $this->collect()->{$method}(...$params);
        });
    }

    /**
     * Get the current time.
     *
     * @return int
     */
    protected function now()
    {
        return class_exists(Carbon::class)
            ? Carbon::now()->timestamp
            : time();
    }

    /**
     * Get the precise current time.
     *
     * @return float
     */
    protected function preciseNow()
    {
        return class_exists(Carbon::class)
            ? Carbon::now()->getPreciseTimestamp()
            : microtime(true) * 1_000_000;
    }

    /**
     * Sleep for the given amount of microseconds.
     */
    protected function usleep(int $microseconds)
    {
        if ($microseconds <= 0) {
            return;
        }
        usleep($microseconds);
    }
}
