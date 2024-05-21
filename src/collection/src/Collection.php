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
use CachingIterator;
use Closure;
use Countable;
use Exception;
use Hyperf\Collection\Traits\EnumeratesValues;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\Macroable\Macroable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use RuntimeException;
use stdClass;
use Symfony\Component\VarDumper\VarDumper;
use Traversable;

/**
 * Most of the methods in this file come from illuminate/collections,
 * thanks Laravel Team provide such a useful class.
 *
 * @template TKey of array-key
 * @template TValue
 * @template TTimesValue
 * @implements ArrayAccess<TKey, TValue>
 * @implements Arrayable<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
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
class Collection implements ArrayAccess, Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
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
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @param null|(callable(int): TTimesValue) $callback
     * @return static<int, TTimesValue>
     */
    public static function times(int $number, ?callable $callback = null): self
    {
        if ($number < 1) {
            return new static();
        }
        if (is_null($callback)) {
            return new static(range(1, $number));
        }
        return (new static(range(1, $number)))->map($callback);
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
     * Get the average value of a given key.
     *
     * @param null|(callable(TValue): float|int)|string $callback
     */
    public function avg($callback = null)
    {
        $callback = $this->valueRetriever($callback);
        $items = $this->map(function ($value) use ($callback) {
            return $callback($value);
        })->filter(function ($value) {
            return ! is_null($value);
        });
        if ($count = $items->count()) {
            return $items->sum() / $count;
        }
        return null;
    }

    /**
     * Alias for the "avg" method.
     *
     * @param null|(callable(TValue): float|int)|string $callback
     * @return null|float|int
     */
    public function average($callback = null)
    {
        return $this->avg($callback);
    }

    /**
     * Get the median of a given key.
     *
     * @param null|array<array-key, string>|string $key
     */
    public function median($key = null)
    {
        $values = (isset($key) ? $this->pluck($key) : $this)->filter(function ($item) {
            return ! is_null($item);
        })->sort()->values();
        $count = $values->count();
        if ($count == 0) {
            return;
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
    public function collapse(): self
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
     */
    public function crossJoin(...$lists): self
    {
        return new static(Arr::crossJoin($this->items, ...array_map([$this, 'getArrayableItems'], $lists)));
    }

    /**
     * Dump the collection and end the script.
     */
    public function dd(...$args): void
    {
        call_user_func_array([$this, 'dump'], $args);
        exit(1);
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
    public function dot(): self
    {
        return new static(Arr::dot($this->all()));
    }

    /**
     * Dump the collection.
     */
    public function dump(): self
    {
        $params = (new static(func_get_args()));
        $params->push($this)->each(function ($item) {
            if (! class_exists(VarDumper::class)) {
                throw new RuntimeException('symfony/var-dumper package required, please require the package via "composer require symfony/var-dumper"');
            }
            VarDumper::dump($item);
        });
        return $this;
    }

    /**
     * Get the items in the collection that are not present in the given items.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @return static<TKey, TValue>
     */
    public function diff($items): self
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
    public function diffUsing($items, callable $callback): self
    {
        return new static(array_udiff($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys and values are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function diffAssoc($items): self
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
    public function diffAssocUsing($items, callable $callback): self
    {
        return new static(array_diff_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Get the items in the collection whose keys are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function diffKeys($items): self
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
    public function diffKeysUsing($items, callable $callback): self
    {
        return new static(array_diff_ukey($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Execute a callback over each item.
     * @param callable(TValue,TKey): mixed $callback
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /**
     * Execute a callback over each nested chunk of items.
     * @param  callable(...mixed): mixed  $callback
     * @return static<TKey, TValue>
     */
    public function eachSpread(callable $callback): self
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;
            return $callback(...$chunk);
        });
    }

    /**
     * Determine if all items in the collection pass the given test.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     * @param mixed $operator
     * @param mixed $value
     */
    public function every($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1) {
            $callback = $this->valueRetriever($key);
            foreach ($this->items as $k => $v) {
                if (! $callback($v, $k)) {
                    return false;
                }
            }
            return true;
        }
        return $this->every($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get all items except for those with the specified keys.
     *
     * @param null|array<array-key, TKey>|static<array-key, TKey> $keys
     * @return static<TKey, TValue>
     */
    public function except($keys): self
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
    public function filter(?callable $callback = null): self
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }
        return new static(array_filter($this->items));
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param mixed $operator
     * @param mixed $value
     * @return static<TKey, TValue>
     */
    public function where(string $key, $operator = null, $value = null): self
    {
        return $this->filter($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param mixed $value
     * @return static<TKey, TValue>
     */
    public function whereStrict(string $key, $value): self
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param Arrayable|iterable $values
     * @return static<TKey, TValue>
     */
    public function whereIn(string $key, $values, bool $strict = false): self
    {
        $values = $this->getArrayableItems($values);
        return $this->filter(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param Arrayable|iterable $values
     * @return static<TKey, TValue>
     */
    public function whereInStrict(string $key, $values): self
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param Arrayable|iterable $values
     * @return static<TKey, TValue>
     */
    public function whereNotIn(string $key, $values, bool $strict = false): self
    {
        $values = $this->getArrayableItems($values);
        return $this->reject(function ($item) use ($key, $values, $strict) {
            return in_array(data_get($item, $key), $values, $strict);
        });
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param Arrayable|iterable $values
     * @return static<TKey, TValue>
     */
    public function whereNotInStrict(string $key, $values): self
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Filter the items, removing any items that don't match the given type.
     *
     * @param class-string $type
     * @return static<TKey, TValue>
     */
    public function whereInstanceOf(string $type): self
    {
        return $this->filter(function ($value) use ($type) {
            return $value instanceof $type;
        });
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
     * @param mixed $operator
     * @param mixed $value
     * @return null|TValue
     */
    public function firstWhere(string $key, $operator, $value = null)
    {
        return $this->first($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get a flattened array of the items in the collection.
     *
     * @param float|int $depth
     * @return static<int, mixed>
     */
    public function flatten($depth = INF): self
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /**
     * Flip the items in the collection.
     *
     * @return static<TKey, TValue>
     */
    public function flip(): self
    {
        return new static(array_flip($this->items));
    }

    /**
     * Remove an item from the collection by key.
     *
     * @param \Hyperf\Contract\Arrayable<array-key, TValue>|iterable<array-key, TKey>|TKey $keys
     * @return $this
     */
    public function forget($keys): self
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
     * Group an associative array by a field or using a callback.
     * @param mixed $groupBy
     */
    public function groupBy($groupBy, bool $preserveKeys = false): self
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
    public function keyBy($keyBy): self
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
    public function implode(string $value, ?string $glue = null): string
    {
        $first = $this->first();
        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }
        return implode($value, $this->items);
    }

    /**
     * Intersect the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function intersect($items): self
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersect the collection with the given items with additional index check.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function intersectAssoc($items): self
    {
        return new static(array_intersect_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersect the collection with the given items by key.
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function intersectByKeys($items): self
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
     * Determine if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the keys of the collection items.
     * @return static<int, TKey>
     */
    public function keys(): self
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
    public function pluck($value, ?string $key = null): self
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
    public function map(callable $callback): self
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);
        return new static(array_combine($keys, $items));
    }

    /**
     * Run a map over each nested chunk of items.
     *
     * @template TMapSpreadValue
     *
     * @param callable(mixed): TMapSpreadValue $callback
     * @return static<TKey, TMapSpreadValue>
     */
    public function mapSpread(callable $callback): self
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;
            return $callback(...$chunk);
        });
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
    public function mapToDictionary(callable $callback): self
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
     * Run a grouping map over the items.
     * The callback should return an associative array with a single key/value pair.
     */
    public function mapToGroups(callable $callback): self
    {
        $groups = $this->mapToDictionary($callback);
        return $groups->map([$this, 'make']);
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
    public function mapWithKeys(callable $callback): self
    {
        return new static(Arr::mapWithKeys($this->items, $callback));
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @param callable(TValue, TKey): mixed $callback
     * @return static<int, mixed>
     */
    public function flatMap(callable $callback): self
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Map the values into a new class.
     *
     * @param class-string $class
     * @return static<TKey, mixed>
     */
    public function mapInto(string $class): self
    {
        return $this->map(function ($value, $key) use ($class) {
            return new $class($value, $key);
        });
    }

    /**
     * Get the max value of a given key.
     *
     * @param null|(callable(TValue):mixed)|string $callback
     * @return TValue
     */
    public function max($callback = null)
    {
        $callback = $this->valueRetriever($callback);
        return $this->filter(function ($value) {
            return ! is_null($value);
        })->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);
            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * Merge the collection with the given items.
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function merge($items): self
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Recursively merge the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function mergeRecursive($items): self
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
    public function combine($values): self
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /**
     * Union the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static<TKey, TValue>
     */
    public function union($items): self
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    /**
     * Get the min value of a given key.
     *
     * @param null|(callable(TValue):mixed)|string $callback
     * @return TValue
     */
    public function min($callback = null)
    {
        $callback = $this->valueRetriever($callback);
        return $this->map(function ($value) use ($callback) {
            return $callback($value);
        })->filter(function ($value) {
            return ! is_null($value);
        })->reduce(function ($result, $value) {
            return is_null($result) || $value < $result ? $value : $result;
        });
    }

    /**
     * Create a new collection consisting of every n-th element.
     *
     * @return static<TKey, TValue>
     */
    public function nth(int $step, int $offset = 0): self
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
    public function only($keys): self
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
     * "Paginate" the collection by slicing it into a smaller collection.
     */
    public function forPage(int $page, int $perPage): self
    {
        $offset = max(0, ($page - 1) * $perPage);
        return $this->slice($offset, $perPage);
    }

    /**
     * Partition the collection into two arrays using the given callback or key.
     *
     * @param  callable(TValue, TKey) bool)|TValue|string  $key
     * @param null|string|TValue $operator
     * @param null|TValue $value
     * @return static<int, static<TKey, TValue>>
     */
    public function partition($key, $operator = null, $value = null): self
    {
        $partitions = [new static(), new static()];
        $callback = func_num_args() === 1 ? $this->valueRetriever($key) : $this->operatorForWhere(...func_get_args());
        foreach ($this->items as $key => $item) {
            $partitions[(int) ! $callback($item, $key)][$key] = $item;
        }
        return new static($partitions);
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @template TPipeReturnType
     *
     * @param callable($this): TPipeReturnType $callback
     * @return TPipeReturnType
     */
    public function pipe(callable $callback)
    {
        return $callback($this);
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
    public function prepend($value, $key = null): self
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
    public function push($value): self
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
    public function concat($source): self
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
    public function put($key, $value): self
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
     * @param int $from
     * @param int $to
     * @return static<int, int>
     */
    public static function range($from, $to): self
    {
        return new static(range($from, $to));
    }

    /**
     * Reduce the collection to a single value.
     *
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param callable(TReduceInitial|TReduceReturnType, TValue): TReduceReturnType $callback
     * @param TReduceInitial $initial
     * @return TReduceInitial|TReduceReturnType
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param bool|callable(TValue, TKey): bool $callback
     * @return static<TKey, TValue>
     */
    public function reject($callback): self
    {
        if ($this->useAsCallable($callback)) {
            return $this->filter(function ($value, $key) use ($callback) {
                return ! $callback($value, $key);
            });
        }
        return $this->filter(function ($item) use ($callback) {
            return $item != $callback;
        });
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
    public function reverse(): self
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
    public function shuffle(?int $seed = null): self
    {
        return new static(Arr::shuffle($this->items, $seed));
    }

    /**
     * Skip the first {$count} items.
     *
     * @return static<TKey, TValue>
     */
    public function skip(int $count): self
    {
        return $this->slice($count);
    }

    /**
     * Slice the underlying collection array.
     *
     * @return static<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null): self
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Create chunks representing a "sliding window" view of the items in the collection.
     *
     * @return static<int, TTimesValue>
     */
    public function sliding(int $size = 2, int $step = 1): self
    {
        $chunks = (int) floor(($this->count() - $size) / $step) + 1;

        return static::times($chunks, fn ($number) => $this->slice(($number - 1) * $step, $size));
    }

    /**
     * Split a collection into a certain number of groups.
     *
     * @return static<int, static<TKey, TValue>>
     */
    public function split(int $numberOfGroups): self
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
    public function chunk(int $size): self
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
    public function sort(?callable $callback = null): self
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
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): self
    {
        if (is_array($callback) && ! is_callable($callback)) {
            return $this->sortByMany($callback);
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
     * @param (callable(TValue, TKey): mixed)|string $callback
     * @return static<TKey, TValue>
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR): self
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Sort items in descending order.
     *
     * @return static<TKey, TValue>
     */
    public function sortDesc(int $options = SORT_REGULAR): self
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
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): self
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
    public function sortKeysDesc(int $options = SORT_REGULAR): self
    {
        return $this->sortKeys($options, true);
    }

    /**
     * Sort the collection keys using a callback.
     *
     * @param callable(TKey, TKey): int $callback
     * @return static<TKey, TValue>
     */
    public function sortKeysUsing(callable $callback): self
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
    public function splice(int $offset, ?int $length = null, $replacement = []): self
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
     * Get the sum of the given values.
     *
     * @param null|(callable(TValue): mixed)|string $callback
     * @return mixed
     */
    public function sum($callback = null)
    {
        if (is_null($callback)) {
            return array_sum($this->items);
        }
        $callback = $this->valueRetriever($callback);
        return $this->reduce(function ($result, $item) use ($callback) {
            return $result + $callback($item);
        }, 0);
    }

    /**
     * Take the first or last {$limit} items.
     *
     * @return static<TKey, TValue>
     */
    public function take(int $limit): self
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }
        return $this->slice(0, $limit);
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param callable(static<TKey,TValue>): mixed $callback
     * @return $this
     */
    public function tap(callable $callback): self
    {
        $callback(new static($this->items));
        return $this;
    }

    /**
     * Transform each item in the collection using a callback.
     *
     * @param callable(TValue, TKey): TValue $callback
     * @return $this
     */
    public function transform(callable $callback): self
    {
        $this->items = $this->map($callback)->all();
        return $this;
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param null|(callable(TValue, TKey): mixed)|string $key
     * @return static<TKey, TValue>
     */
    public function unique($key = null, bool $strict = false): self
    {
        $callback = $this->valueRetriever($key);
        $exists = [];
        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }
            $exists[] = $id;
        });
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param null|(callable(TValue, TKey): mixed)|string $key
     * @return static<TKey, TValue>
     */
    public function uniqueStrict($key = null): self
    {
        return $this->unique($key, true);
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
    public function values(): self
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
    public function zip($items): self
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
    public function pad(int $size, $value): self
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
     * Get the collection of items as a plain array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof Arrayable ? $value->toArray() : $value;
        }, $this->items);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return array_map(function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            }
            if ($value instanceof Jsonable) {
                return json_decode($value->__toString(), true);
            }
            if ($value instanceof Arrayable) {
                return $value->toArray();
            }
            return $value;
        }, $this->items);
    }

    /**
     * Get the collection of items as JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
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
     * Get a CachingIterator instance.
     */
    public function getCachingIterator(int $flags = CachingIterator::CALL_TOSTRING): CachingIterator
    {
        /* @phpstan-ignore-next-line */
        return new CachingIterator($this->getIterator(), $flags);
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
     * Add a method to the list of proxied methods.
     */
    public static function proxy(string $method): void
    {
        static::$proxies[] = $method;
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
    protected function sortByMany(array $comparisons = [])
    {
        $items = $this->items;

        usort($items, function ($a, $b) use ($comparisons) {
            foreach ($comparisons as $comparison) {
                $comparison = Arr::wrap($comparison);

                $prop = $comparison[0];

                $ascending = Arr::get($comparison, 1, true) === true
                    || Arr::get($comparison, 1, true) === 'asc';

                $result = 0;

                if (! is_string($prop) && is_callable($prop)) {
                    $result = $prop($a, $b);
                } else {
                    $values = [data_get($a, $prop), data_get($b, $prop)];

                    if (! $ascending) {
                        $values = array_reverse($values);
                    }

                    $result = $values[0] <=> $values[1];
                }

                if ($result === 0) {
                    continue;
                }

                return $result;
            }
        });

        return new static($items);
    }

    /**
     * Get an operator checker callback.
     * @param mixed|string $operator
     * @param null|TValue $value
     */
    protected function operatorForWhere(string $key, $operator = null, $value = null): Closure
    {
        if (func_num_args() === 1) {
            $value = true;
            $operator = '=';
        }
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);
            $strings = array_filter([$retrieved, $value], function ($value) {
                return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
            });
            if (count($strings) < 2 && count(array_filter([$retrieved, $value], 'is_object')) == 1) {
                return in_array($operator, ['!=', '<>', '!==']);
            }
            switch ($operator) {
                default:
                case '=':
                case '==':
                    return $retrieved == $value;
                case '!=':
                case '<>':
                    return $retrieved != $value;
                case '<':
                    return $retrieved < $value;
                case '>':
                    return $retrieved > $value;
                case '<=':
                    return $retrieved <= $value;
                case '>=':
                    return $retrieved >= $value;
                case '===':
                    return $retrieved === $value;
                case '!==':
                    return $retrieved !== $value;
            }
        };
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
     * Determine if the given value is callable, but not a string.
     * @param mixed $value
     */
    protected function useAsCallable($value): bool
    {
        return ! is_string($value) && is_callable($value);
    }

    /**
     * Get a value retrieving callback.
     * @param mixed $value
     */
    protected function valueRetriever($value): callable
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }
        return function ($item) use ($value) {
            return data_get($item, $value);
        };
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
