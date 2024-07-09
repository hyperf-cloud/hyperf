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

use ArrayAccess;
use ArrayIterator;
use Closure;
use Hyperf\Collection\Traits\EnumeratesValues;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\Macroable\Macroable;
use Hyperf\Stringable\Stringable;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use Traversable;

/**
 * Most of the methods in this file come from illuminate/collections,
 * thanks Laravel Team provide such a useful class.
 *
 * @template TKey of array-key
 * @template TValue
 * @template TTimesValue
 * @implements ArrayAccess<TKey, TValue>
 * @implements Enumerable<TKey, TValue>
 *
 * @property HigherOrderCollectionProxy $average
 * @property HigherOrderCollectionProxy $avg
 * @property HigherOrderCollectionProxy $contains
 * @property HigherOrderCollectionProxy $each
 * @property HigherOrderCollectionProxy $every
 * @property HigherOrderCollectionProxy $filter
 * @property HigherOrderCollectionProxy $first
 * @property HigherOrderCollectionProxy $flatMap
 * @property HigherOrderCollectionProxy $groupBy
 * @property HigherOrderCollectionProxy $keyBy
 * @property HigherOrderCollectionProxy $map
 * @property HigherOrderCollectionProxy $max
 * @property HigherOrderCollectionProxy $min
 * @property HigherOrderCollectionProxy $partition
 * @property HigherOrderCollectionProxy $reject
 * @property HigherOrderCollectionProxy $sortBy
 * @property HigherOrderCollectionProxy $sortByDesc
 * @property HigherOrderCollectionProxy $sum
 * @property HigherOrderCollectionProxy $unique
 */
class Collection implements Enumerable, ArrayAccess
{
    use EnumeratesValues;
    use Macroable;

    /**
     * The items contained in the collection.
     *
     * @var array<TKey, TValue>
     */
    protected array $items = [];

    /**
     * Create a new collection.
     * @param null|iterable<TKey,TValue>|Jsonable|JsonSerializable $items
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * @param null|iterable<TKey,TValue>|Jsonable|JsonSerializable $items
     * @return static<TKey, TValue>
     */
    public function fill($items = [])
    {
        $this->items = $this->getArrayableItems($items);
        return $this;
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the median of a given key.
     *
     * @param null|array<array-key, string>|string $key
     * @return null|float|int
     */
    public function median($key = null)
    {
        $values = (isset($key) ? $this->pluck($key) : $this)->filter(function ($item) {
            return ! is_null($item);
        })->sort()->values();
        $count = $values->count();
        if ($count == 0) {
            return null;
        }
        $middle = (int) ($count / 2);
        if ($count % 2) {
            return $values->get($middle);
        }
        return (new static([
            $values->get($middle - 1),
            $values->get($middle),
        ]))->average();
    }

    /**
     * Get the mode of a given key.
     *
     * @param null|array<array-key, string>|string $key
     * @return null|array<int, float|int>
     */
    public function mode($key = null)
    {
        if ($this->count() == 0) {
            return null;
        }
        $collection = isset($key) ? $this->pluck($key) : $this;

        /**
         * @template TValue of array-key
         * @phpstan-ignore-next-line
         * @var static<TValue, int> $counts
         */
        $counts = new self();
        $collection->each(function ($value) use ($counts) {
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        });
        $sorted = $counts->sort();
        $highestValue = $sorted->last();
        return $sorted->filter(function ($value) use ($highestValue) {
            return $value == $highestValue;
        })->sort()->keys()->all();
    }

    /**
     * Collapse the collection of items into a single array.
     *
     * @return static<int, mixed>
     */
    public function collapse()
    {
        return new static(Arr::collapse($this->items));
    }

    /**
     * Determine if an item exists in the collection.
     *
     * @param null|mixed $operator
     * @param null|mixed $value
     * @param (callable(TValue): bool)|string|TValue $key
     */
    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new stdClass();
                return $this->first($key, $placeholder) !== $placeholder;
            }
            return in_array($key, $this->items);
        }
        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Determine if the collection contains a single item.
     */
    public function containsOneItem(): bool
    {
        return $this->count() === 1;
    }

    /**
     * Determine if an item exists in the collection using strict comparison.
     *
     * @param null|TValue $value
     * @param callable|TKey|TValue $key
     */
    public function containsStrict($key, $value = null): bool
    {
        if (func_num_args() === 2) {
            return $this->contains(function ($item) use ($key, $value) {
                return data_get($item, $key) === $value;
            });
        }
        if ($this->useAsCallable($key)) {
            return ! is_null($this->first($key));
        }
        return in_array($key, $this->items, true);
    }

    /**
     * Cross join with the given lists, returning all possible permutations.
     *
     * @template TCrossJoinKey
     * @template TCrossJoinValue
     *
     * @param \Hyperf\Contract\Arrayable<TCrossJoinKey, TCrossJoinValue>|iterable<TCrossJoinKey, TCrossJoinValue> ...$lists
     * @return static<int, array<int, TCrossJoinValue|TValue>>
     */
    public function crossJoin(...$lists)
    {
        return new static(Arr::crossJoin($this->items, ...array_map([$this, 'getArrayableItems'], $lists)));
    }

    /**
     * Determine if an item is not contained in the collection.
     *
     * @param null|mixed $operator
     * @param null|mixed $value
     * @param (callable(TValue): bool)|string|TValue $key
     */
    public function doesntContain($key, $operator = null, $value = null): bool
    {
        return ! $this->contains(...func_get_args());
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @return static<TKey, TValue>
     */
    public function dot()
    {
        return new static(Arr::dot($this->all()));
    }

    /**
     * Convert a flatten "dot" notation array into an expanded array.
     */
    public function undot()
    {
        return new static(Arr::undot($this->all()));
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @return static<TKey, TValue>
     */
    public function diff($items)
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue): int $callback
     * @return static<TKey, TValue>
     */
    public function diffUsing($items, callable $callback)
    {
        return new static(array_udiff($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function diffAssoc($items)
    {
        return new static(array_diff_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @param callable(TKey): int $callback
     * @return static<TKey, TValue>
     */
    public function diffAssocUsing($items, callable $callback)
    {
        return new static(array_diff_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function diffKeys($items)
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @param callable(TKey): int $callback
     * @return static<TKey, TValue>
     */
    public function diffKeysUsing($items, callable $callback)
    {
        return new static(array_diff_ukey($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param null|array<array-key, TKey>|static<array-key, TKey> $keys
     * @return static<TKey, TValue>
     */
    public function except($keys)
    {
        if (is_null($keys)) {
            return new static($this->items);
        }
        if ($keys instanceof self) {
            $keys = $keys->all();
        } elseif (! is_array($keys)) {
            $keys = func_get_args();
        }
        return new static(Arr::except($this->items, $keys));
    }

    /**
     * Run a filter over each of the items.
     *
     * @param null|(callable(TValue, TKey): bool) $callback
     * @return static<TKey, TValue>
     */
    public function filter(?callable $callback = null)
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }
        return new static(array_filter($this->items));
    }

    /**
     * Get the first item from the collection.
     *
     * @template TFirstDefault
     *
     * @param null|(callable(TValue, TKey): bool) $callback
     * @param (Closure(): TFirstDefault)|TFirstDefault $default
     * @return TFirstDefault|TValue
     */
    public function first(?callable $callback = null, $default = null)
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * Get the first item by the given key value pair.
     *
     * @return null|TValue
     */
    public function firstWhere(callable|string $key, mixed $operator = null, mixed $value = null): mixed
    {
        return $this->first($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param float|int $depth
     * @return static<int, mixed>
     */
    public function flatten($depth = INF)
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static<TKey, TValue>
     */
    public function flip()
    {
        return new static(array_flip($this->items));
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TKey>|TKey $keys
     * @return $this
     */
    public function forget($keys)
    {
        foreach ($this->getArrayableItems($keys) as $key) {
            $this->offsetUnset($key);
        }
        return $this;
    }

    /**
     * Get an item from the collection by key.
     *
     * @template TGetDefault
     *
     * @param TKey $key
     * @param (Closure(): TGetDefault)|TGetDefault $default
     * @return TGetDefault|TValue
     */
    public function get($key, $default = null)
    {
        if ($this->offsetExists($key)) {
            return $this->items[$key];
        }
        return value($default);
    }

    /**
     * Get an item from the collection by key or add it to collection if it does not exist.
     *
     * @template TGetOrPutValue
     *
     * @param (Closure(): TGetOrPutValue)|TGetOrPutValue $value
     * @return TGetOrPutValue|TValue
     */
    public function getOrPut(int|string $key, mixed $value): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $this->offsetSet($key, $value = value($value));

        return $value;
    }

    /**
     * Get an item from the collection by key or add it to collection if it does not exist.
     *
     * @template TGetOrPutValue
     *
     * @param (Closure(): TGetOrPutValue)|TGetOrPutValue $value
     * @return TGetOrPutValue|TValue
     */
    public function getOrSet(int|string $key, mixed $value): mixed
    {
        return $this->getOrPut($key, $value);
    }

    /**
     * Group an associative array by a field or using a callback.
     * @param mixed $groupBy
     */
    public function groupBy($groupBy, bool $preserveKeys = false)
    {
        if (is_array($groupBy)) {
            $nextGroups = $groupBy;
            $groupBy = array_shift($nextGroups);
        }
        $groupBy = $this->valueRetriever($groupBy);
        $results = [];
        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);
            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }
            foreach ($groupKeys as $groupKey) {
                $groupKey = is_bool($groupKey) ? (int) $groupKey : $groupKey;
                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static();
                }
                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }
        $result = new static($results);
        if (! empty($nextGroups)) {
            return $result->map->groupBy($nextGroups, $preserveKeys);
        }
        return $result;
    }

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param array|(callable(TValue, TKey): array-key)|string $keyBy
     * @return static<TKey, TValue>
     */
    public function keyBy($keyBy)
    {
        $keyBy = $this->valueRetriever($keyBy);
        $results = [];
        foreach ($this->items as $key => $item) {
            $resolvedKey = $keyBy($item, $key);
            if (is_object($resolvedKey)) {
                $resolvedKey = (string) $resolvedKey;
            }
            $results[$resolvedKey] = $item;
        }
        return new static($results);
    }

    /**
     * Determine if an item exists in the collection by key.
     * @param array<array-key, TKey>|TKey $key
     */
    public function has($key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();
        foreach ($keys as $value) {
            if (! $this->offsetExists($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine if any of the keys exist in the collection.
     *
     * @param array<array-key, TKey>|TKey $key
     */
    public function hasAny($key): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->has($value)) {
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
        if ($this->useAsCallable($value)) {
            return implode($glue ?? '', $this->map($value)->all());
        }

        $first = $this->first();

        if (is_array($first) || (is_object($first) && ! $first instanceof Stringable)) {
            return implode($glue ?? '', $this->pluck($value)->all());
        }

        return implode($value ?: '', $this->items);
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function intersect(mixed $items)
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersect the collection with the given items with additional index check.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function intersectAssoc($items)
    {
        return new static(array_intersect_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersect the collection with the given items by key.
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function intersectByKeys($items)
    {
        return new static(array_intersect_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Determine if the collection is empty or not.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get the keys of the collection items.
     * @return static<int, TKey>
     */
    public function keys()
    {
        return new static(array_keys($this->items));
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
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * Get the values of a given key.
     *
     * @param array<array-key, string>|string $value
     * @return static<int, mixed>
     */
    public function pluck(array|string $value, ?string $key = null)
    {
        return new static(Arr::pluck($this->items, $value, $key));
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
        $result = [];
        foreach ($this->items as $key => $value) {
            $result[$key] = $callback($value, $key);
        }

        return new static($result);
    }

    /**
     * Run a dictionary map over the items.
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
        $dictionary = [];
        foreach ($this->items as $key => $item) {
            $pair = $callback($item, $key);
            $key = key($pair);
            $value = reset($pair);
            if (! isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $value;
        }
        return new static($dictionary);
    }

    /**
     * Run an associative map over each of the items.
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
        return new static(Arr::mapWithKeys($this->items, $callback));
    }

    /**
     * Merge the collection with the given items.
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Recursively merge the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function mergeRecursive($items)
    {
        return new static(array_merge_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @template TCombineValue
     *
     * @param Arrayable<array-key, TCombineValue>|iterable<array-key, TCombineValue> $values
     * @return static<TKey, TCombineValue>
     */
    public function combine($values)
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /**
     * Union the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function union($items)
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @return static<TKey, TValue>
     */
    public function nth(int $step, int $offset = 0)
    {
        $new = [];
        $position = 0;
        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }
            ++$position;
        }
        return new static($new);
    }

    /**
     * Get the items with the specified keys.
     *
     * @param null|array<array-key, TKey>|static<array-key, TKey>|string $keys
     * @return static<TKey, TValue>
     */
    public function only($keys)
    {
        if (is_null($keys)) {
            return new static($this->items);
        }
        if ($keys instanceof self) {
            $keys = $keys->all();
        }
        $keys = is_array($keys) ? $keys : func_get_args();
        return new static(Arr::only($this->items, $keys));
    }

    /**
     * Get and remove the last item from the collection.
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Push an item onto the beginning of the collection.
     *
     * @param TValue $value
     * @param null|TKey $key
     * @return $this
     */
    public function prepend($value, $key = null)
    {
        $this->items = Arr::prepend($this->items, $value, $key);
        return $this;
    }

    /**
     * Push an item onto the end of the collection.
     *
     * @param TValue $value
     * @return $this
     */
    public function push($value)
    {
        $this->offsetSet(null, $value);
        return $this;
    }

    /**
     * Push all of the given items onto the collection.
     *
     * @param iterable<array-key, TValue> $source
     * @return static<TKey, TValue>
     */
    public function concat($source)
    {
        $result = new static($this);
        foreach ($source as $item) {
            $result->push($item);
        }
        return $result;
    }

    /**
     * Get and remove an item from the collection.
     *
     * @template TPullDefault
     *
     * @param TKey $key
     * @param (Closure(): TPullDefault)|TPullDefault $default
     * @return TPullDefault|TValue
     */
    public function pull($key, $default = null)
    {
        return Arr::pull($this->items, $key, $default);
    }

    /**
     * Put an item in the collection by key.
     *
     * @param TKey $key
     * @param TValue $value
     * @return $this
     */
    public function put($key, $value)
    {
        $this->offsetSet($key, $value);
        return $this;
    }

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @return static<int, TValue>|TValue
     * @throws InvalidArgumentException
     */
    public function random(?int $number = null)
    {
        if (is_null($number)) {
            return Arr::random($this->items);
        }
        return new static(Arr::random($this->items, $number));
    }

    /**
     * Create a collection with the given range.
     *
     * @return static<int, int>
     */
    public static function range(float|int|string $from, float|int|string $to)
    {
        return new static(range($from, $to));
    }

    /**
     * Replace the collection items with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function replace($items)
    {
        return new static(array_replace($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Recursively replace the collection items with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function replaceRecursive($items)
    {
        return new static(array_replace_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Reverse items order.
     *
     * @return static<TKey, TValue>
     */
    public function reverse()
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     * @return bool|TKey
     */
    public function search($value, bool $strict = false)
    {
        if (! $this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }
        foreach ($this->items as $key => $item) {
            if (call_user_func($value, $item, $key)) {
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
        $key = $this->search($value, $strict);

        if ($key === false) {
            return null;
        }

        $position = $this->keys()->search($key);

        if ($position === 0) {
            return null;
        }

        return $this->get($this->keys()->get($position - 1));
    }

    /**
     * Get the item after the given item.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     * @return null|TValue
     */
    public function after(mixed $value, bool $strict = false): mixed
    {
        $key = $this->search($value, $strict);

        if ($key === false) {
            return null;
        }

        $position = $this->keys()->search($key);

        if ($position === $this->keys()->count() - 1) {
            return null;
        }

        return $this->get($this->keys()->get($position + 1));
    }

    /**
     * Get and remove the first item from the collection.
     *
     * @return null|TValue
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Shuffle the items in the collection.
     *
     * @return static<TKey, TValue>
     */
    public function shuffle(?int $seed = null)
    {
        return new static(Arr::shuffle($this->items, $seed));
    }

    /**
     * Skip the first {$count} items.
     *
     * @return static<TKey, TValue>
     */
    public function skip(int $count)
    {
        return $this->slice($count);
    }

    /**
     * Slice the underlying collection array.
     *
     * @return static<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null)
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Create chunks representing a "sliding window" view of the items in the collection.
     *
     * @return static<int, TTimesValue>
     */
    public function sliding(int $size = 2, int $step = 1)
    {
        $chunks = (int) floor(($this->count() - $size) / $step) + 1;

        return static::times($chunks, fn ($number) => $this->slice(($number - 1) * $step, $size));
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @return static<int, static<TKey, TValue>>
     */
    public function split(int $numberOfGroups)
    {
        if ($this->isEmpty()) {
            return new static();
        }
        $groups = new static();
        $groupSize = (int) floor($this->count() / $numberOfGroups);
        $remain = $this->count() % $numberOfGroups;
        $start = 0;
        for ($i = 0; $i < $numberOfGroups; ++$i) {
            $size = $groupSize;
            if ($i < $remain) {
                ++$size;
            }
            if ($size) {
                $groups->push(new static(array_slice($this->items, $start, $size)));
                $start += $size;
            }
        }
        return $groups;
    }

    /**
     * Chunk the underlying collection array.
     *
     * @return static<int, static<TKey, TValue>>
     */
    public function chunk(int $size)
    {
        if ($size <= 0) {
            return new static();
        }
        $chunks = [];
        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }
        return new static($chunks);
    }

    /**
     * Sort through each item with a callback.
     *
     * @param callable(TValue, TValue): int $callback
     * @return static<TKey, TValue>
     */
    public function sort(?callable $callback = null)
    {
        $items = $this->items;
        $callback ? uasort($items, $callback) : asort($items);
        return new static($items);
    }

    /**
     * Sort the collection using the given callback.
     *
     * @param array|(callable(TValue, TKey): mixed)|string $callback
     * @return static<TKey, TValue>
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false)
    {
        if (is_array($callback) && ! is_callable($callback)) {
            return $this->sortByMany($callback, $options);
        }

        $results = [];
        $callback = $this->valueRetriever($callback);
        // First we will loop through the items and get the comparator from a callback
        // function which we were given. Then, we will sort the returned values and
        // and grab the corresponding values for the sorted keys from this array.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }
        $descending ? arsort($results, $options) : asort($results, $options);
        // Once we have sorted all of the keys in the array, we will loop through them
        // and grab the corresponding model so we can set the underlying items list
        // to the sorted version. Then we'll just return the collection instance.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }
        return new static($results);
    }

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param array|(callable(TValue, TKey): mixed)|string $callback
     * @return static<TKey, TValue>
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR)
    {
        if (is_array($callback) && ! is_callable($callback)) {
            foreach ($callback as $index => $key) {
                $comparison = Arr::wrap($key);

                $comparison[1] = 'desc';

                $callback[$index] = $comparison;
            }
        }

        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort items in descending order.
     *
     * @return static<TKey, TValue>
     */
    public function sortDesc(int $options = SORT_REGULAR)
    {
        $items = $this->items;

        arsort($items, $options);

        return new static($items);
    }

    /**
     * Sort the collection keys.
     *
     * @return static<TKey, TValue>
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false)
    {
        $items = $this->items;
        $descending ? krsort($items, $options) : ksort($items, $options);
        return new static($items);
    }

    /**
     * Sort the collection keys in descending order.
     *
     * @return static<TKey, TValue>
     */
    public function sortKeysDesc(int $options = SORT_REGULAR)
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Sort the collection keys using a callback.
     *
     * @param callable(TKey, TKey): int $callback
     * @return static<TKey, TValue>
     */
    public function sortKeysUsing(callable $callback)
    {
        $items = $this->items;

        uksort($items, $callback);

        return new static($items);
    }

    /**
     * Splice a portion of the underlying collection array.
     *
     * @param array<array-key, TValue> $replacement
     * @return static<TKey, TValue>
     */
    public function splice(int $offset, ?int $length = null, $replacement = [])
    {
        if (func_num_args() === 1) {
            return new static(array_splice($this->items, $offset));
        }
        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * Split a collection into a certain number of groups, and fill the first groups completely.
     *
     * @return static<int, static<TKey, TValue>>
     */
    public function splitIn(int $numberOfGroups)
    {
        return $this->chunk((int) ceil($this->count() / $numberOfGroups));
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @return static<TKey, TValue>
     */
    public function take(int $limit)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }
        return $this->slice(0, $limit);
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param callable(TValue, TKey): TValue $callback
     * @return $this
     */
    public function transform(callable $callback)
    {
        $this->items = $this->map($callback)->all();
        return $this;
    }

    /**
     * Prepend one or more items to the beginning of the collection.
     *
     * @param TValue ...$values
     * @return $this
     */
    public function unshift(...$values)
    {
        array_unshift($this->items, ...$values);

        return $this;
    }

    /**
     * Reset the keys on the underlying array.
     *
     * @return static<TKey, TValue>
     */
    public function values()
    {
        return new static(array_values($this->items));
    }

    /**
     * Zip the collection together with one or more arrays.
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]].
     *
     * @template TZipValue
     *
     * @param Arrayable<array-key, TZipValue>|iterable<array-key, TZipValue> ...$items
     * @return static<int, static<int, TValue|TZipValue>>
     */
    public function zip($items)
    {
        $arrayableItems = array_map(function ($items) {
            return $this->getArrayableItems($items);
        }, func_get_args());
        $params = array_merge([
            function () {
                return new static(func_get_args());
            },
            $this->items,
        ], $arrayableItems);
        return new static(call_user_func_array('array_map', $params));
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
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Retrieve duplicate items from the collection.
     *
     * @param null|(callable(TValue): bool)|string $callback
     * @param bool $strict
     * @return static
     */
    public function duplicates($callback = null, $strict = false)
    {
        $items = $this->map($this->valueRetriever($callback));

        $uniqueItems = $items->unique(null, $strict);

        $compare = $this->duplicateComparator($strict);

        $duplicates = new static();

        foreach ($items as $key => $value) {
            if ($uniqueItems->isNotEmpty() && $compare($value, $uniqueItems->first())) {
                $uniqueItems->shift();
            } else {
                $duplicates[$key] = $value;
            }
        }

        return $duplicates;
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

        $items = $this->unless($filter == null)->filter($filter);

        $count = $items->count();

        if ($count === 0) {
            throw new ItemNotFoundException();
        }

        if ($count > 1) {
            throw new MultipleItemsFoundException($count);
        }

        return $items->first();
    }

    /**
     * Get an iterator for the items.
     *
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get a base Support collection instance from this collection.
     *
     * @return Collection<TKey, TValue>
     */
    public function toBase()
    {
        return new self($this);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param TKey $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Get an item at a given offset.
     *
     * @param TKey $offset
     * @return TValue
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * Set the item at a given offset.
     *
     * @param null|TKey $offset
     * @param TValue $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param TKey $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
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

        $placeholder = new stdClass();

        $item = $this->first($filter, $placeholder);

        if ($item === $placeholder) {
            throw new ItemNotFoundException();
        }

        return $item;
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
        if ($finalGlue === '') {
            return $this->implode($glue);
        }

        $count = $this->count();

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $this->last();
        }

        $collection = new static($this->items);

        $finalItem = $collection->pop();

        return $collection->implode($glue) . $finalGlue . $finalItem;
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
        return new static(array_intersect_uassoc($this->items, $this->getArrayableItems($items), $callback));
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
        return new static(array_uintersect($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Retrieve duplicate items from the collection using strict comparison.
     *
     * @param null|(callable(TValue): bool)|string $callback
     * @return static
     */
    public function duplicatesStrict($callback = null)
    {
        return $this->duplicates($callback, true);
    }

    /**
     * Get a lazy collection for the items in this collection.
     *
     * @return LazyCollection<TKey, TValue>
     */
    public function lazy()
    {
        return new LazyCollection($this->items);
    }

    /**
     * Skip items in the collection until the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function skipUntil($value)
    {
        return new static($this->lazy()->skipUntil($value)->all());
    }

    /**
     * Skip items in the collection while the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function skipWhile($value)
    {
        return new static($this->lazy()->skipWhile($value)->all());
    }

    /**
     * Chunk the collection into chunks with a callback.
     *
     * @param callable(TValue, TKey, static<int, TValue>): bool $callback
     * @return static<int, static<int, TValue>>
     */
    public function chunkWhile(callable $callback)
    {
        return new static(
            $this->lazy()->chunkWhile($callback)->mapInto(static::class)
        );
    }

    /**
     * Take items in the collection until the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function takeUntil($value)
    {
        return new static($this->lazy()->takeUntil($value)->all());
    }

    /**
     * Take items in the collection while the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function takeWhile($value)
    {
        return new static($this->lazy()->takeWhile($value)->all());
    }

    /**
     * Count the number of items in the collection by a field or using a callback.
     *
     * @param null|(callable(TValue, TKey): array-key)|string $countBy
     * @return static<array-key, int>
     */
    public function countBy($countBy = null)
    {
        return new static($this->lazy()->countBy($countBy)->all());
    }

    /**
     * Sort the collection using multiple comparisons.
     *
     * @return static
     */
    protected function sortByMany(array $comparisons = [], int $options = SORT_REGULAR)
    {
        $items = $this->items;

        uasort($items, function ($a, $b) use ($comparisons, $options) {
            foreach ($comparisons as $comparison) {
                $comparison = Arr::wrap($comparison);

                $prop = $comparison[0];

                $ascending = Arr::get($comparison, 1, true) === true
                    || Arr::get($comparison, 1, true) === 'asc';

                if (! is_string($prop) && is_callable($prop)) {
                    $result = $prop($a, $b);
                } else {
                    $values = [data_get($a, $prop), data_get($b, $prop)];

                    if (! $ascending) {
                        $values = array_reverse($values);
                    }

                    if (($options & SORT_FLAG_CASE) === SORT_FLAG_CASE) {
                        if (($options & SORT_NATURAL) === SORT_NATURAL) {
                            $result = strnatcasecmp($values[0], $values[1]);
                        } else {
                            $result = strcasecmp($values[0], $values[1]);
                        }
                    } else {
                        $result = match ($options) {
                            SORT_NUMERIC => intval($values[0]) <=> intval($values[1]),
                            SORT_STRING => strcmp($values[0], $values[1]),
                            SORT_NATURAL => strnatcmp($values[0], $values[1]),
                            SORT_LOCALE_STRING => strcoll($values[0], $values[1]),
                            default => $values[0] <=> $values[1],
                        };
                    }
                }

                if ($result === 0) {
                    continue;
                }

                return $result;
            }
        });

        // TODO: The code will be removed in v3.2
        if (array_is_list($this->items)) {
            $items = array_values($items);
        }

        return new static($items);
    }

    /**
     * Get the comparison function to detect duplicates.
     *
     * @param bool $strict
     * @return callable(TValue, TValue): bool
     */
    protected function duplicateComparator($strict)
    {
        if ($strict) {
            return fn ($a, $b) => $a === $b;
        }

        return fn ($a, $b) => $a == $b;
    }

    /**
     * Results array of items from Collection or Arrayable.
     * @param null|Arrayable<TKey,TValue>|iterable<TKey,TValue>|Jsonable|JsonSerializable|static<TKey,TValue> $items
     * @return array<TKey,TValue>
     */
    protected function getArrayableItems($items): array
    {
        return match (true) {
            is_array($items) => $items,
            $items instanceof self => $items->all(),
            $items instanceof Arrayable => $items->toArray(),
            $items instanceof Jsonable => json_decode($items->__toString(), true),
            $items instanceof JsonSerializable => $items->jsonSerialize(),
            $items instanceof Traversable => iterator_to_array($items),
            default => (array) $items,
        };
    }
}
