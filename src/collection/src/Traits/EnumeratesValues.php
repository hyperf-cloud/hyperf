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

namespace Hyperf\Collection\Traits;

use BackedEnum;
use CachingIterator;
use Closure;
use Exception;
use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Collection\Enumerable;
use Hyperf\Collection\HigherOrderCollectionProxy;
use Hyperf\Conditionable\Conditionable;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use JsonSerializable;
use RuntimeException;
use Traversable;
use UnexpectedValueException;
use UnitEnum;

use function Hyperf\Collection\data_get;
use function Hyperf\Support\class_basename;
use function Hyperf\Support\value;

/**
 * @template TKey of array-key
 * @template TTimesValue
 *
 * @template TValue
 * @property HigherOrderCollectionProxy $average
 * @property HigherOrderCollectionProxy $avg
 * @property HigherOrderCollectionProxy $contains
 * @property HigherOrderCollectionProxy $doesntContain
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
 * @property HigherOrderCollectionProxy $percentage
 * @property HigherOrderCollectionProxy $reject
 * @property HigherOrderCollectionProxy $skipUntil
 * @property HigherOrderCollectionProxy $skipWhile
 * @property HigherOrderCollectionProxy $some
 * @property HigherOrderCollectionProxy $sortBy
 * @property HigherOrderCollectionProxy $sortByDesc
 * @property HigherOrderCollectionProxy $sum
 * @property HigherOrderCollectionProxy $takeUntil
 * @property HigherOrderCollectionProxy $takeWhile
 * @property HigherOrderCollectionProxy $unique
 * @property HigherOrderCollectionProxy $unless
 * @property HigherOrderCollectionProxy $until
 * @property HigherOrderCollectionProxy $when
 */
trait EnumeratesValues
{
    use Conditionable;

    /**
     * Indicates that the object's string representation should be escaped when __toString is invoked.
     *
     * @var bool
     */
    protected $escapeWhenCastingToString = false;

    /**
     * The methods that can be proxied.
     *
     * @var array<int, string>
     */
    protected static $proxies = [
        'average',
        'avg',
        'contains',
        'doesntContain',
        'each',
        'every',
        'filter',
        'first',
        'flatMap',
        'groupBy',
        'keyBy',
        'map',
        'max',
        'min',
        'partition',
        'percentage',
        'reject',
        'skipUntil',
        'skipWhile',
        'some',
        'sortBy',
        'sortByDesc',
        'sum',
        'takeUntil',
        'takeWhile',
        'unique',
        'unless',
        'until',
        'when',
    ];

    /**
     * Convert the collection to its string representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Dynamically access collection proxies.
     *
     * @param string $key
     * @return mixed
     *
     * @throws Exception
     */
    public function __get($key)
    {
        if (! in_array($key, static::$proxies)) {
            throw new Exception("Property [{$key}] does not exist on this collection instance.");
        }

        return new HigherOrderCollectionProxy($this, $key);
    }

    /**
     * Create a new collection instance if the value isn't one already.
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     *
     * @param null|Arrayable<TMakeKey, TMakeValue>|iterable<TMakeKey, TMakeValue> $items
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make(mixed $items = [])
    {
        return new static($items);
    }

    /**
     * Wrap the given value in a collection if applicable.
     *
     * @template TWrapValue
     *
     * @param iterable<array-key, TWrapValue>|TWrapValue $value
     * @return static<array-key, TWrapValue>
     */
    public static function wrap(mixed $value)
    {
        return $value instanceof Enumerable
            ? new static($value)
            : new static(Arr::wrap($value));
    }

    /**
     * Get the underlying items from the given collection if applicable.
     *
     * @template TUnwrapKey of array-key
     * @template TUnwrapValue
     *
     * @param array<TUnwrapKey, TUnwrapValue>|static<TUnwrapKey, TUnwrapValue> $value
     * @return array<TUnwrapKey, TUnwrapValue>
     */
    public static function unwrap(mixed $value): array
    {
        return $value instanceof Enumerable ? $value->all() : $value;
    }

    /**
     * Create a new instance with no items.
     * @return static
     */
    public static function empty()
    {
        return new static([]);
    }

    /**
     * Create a new collection by invoking the callback a given amount of times.
     *
     * @param null|(callable(int): TTimesValue) $callback
     * @return static<int, TTimesValue>
     */
    public static function times(int $number, ?callable $callback = null)
    {
        if ($number < 1) {
            return new static();
        }

        return static::range(1, $number)
            ->unless($callback == null)
            ->map($callback);
    }

    /**
     * Get the average value of a given key.
     *
     * @param null|(callable(TValue): float|int)|string $callback
     */
    public function avg(mixed $callback = null): null|float|int
    {
        $callback = $this->valueRetriever($callback);

        $reduced = $this->reduce(static function (&$reduce, $value) use ($callback) {
            if (! is_null($resolved = $callback($value))) {
                $reduce[0] += $resolved;
                ++$reduce[1];
            }

            return $reduce;
        }, [0, 0]);

        return $reduced[1] ? $reduced[0] / $reduced[1] : null;
    }

    /**
     * Alias for the "avg" method.
     *
     * @param null|(callable(TValue): float|int)|string $callback
     */
    public function average(mixed $callback = null): null|float|int
    {
        return $this->avg($callback);
    }

    /**
     * Alias for the "contains" method.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function some($key, $operator = null, $value = null)
    {
        return $this->contains(...func_get_args());
    }

    /**
     * Dump the given arguments and terminate execution.
     *
     * @param mixed ...$args
     * @return never
     */
    public function dd(...$args)
    {
        $this->dump(...$args);

        exit(1);
    }

    /**
     * Dump the items.
     *
     * @param mixed ...$args
     * @return $this
     */
    public function dump(...$args)
    {
        if (! function_exists('dump')) {
            throw new RuntimeException('symfony/var-dumper package required, please require the package via "composer require symfony/var-dumper"');
        }

        dump($this->all(), ...$args);

        return $this;
    }

    /**
     * Execute a callback over each item.
     *
     * @param callable(TValue, TKey): mixed $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Execute a callback over each nested chunk of items.
     *
     * @param callable(...mixed): mixed  $callback
     * @return $this
     */
    public function eachSpread(callable $callback)
    {
        return $this->each(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Determine if all items pass the given truth test.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     */
    public function every(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            $callback = $this->valueRetriever($key);

            foreach ($this as $k => $v) {
                if (! $callback($v, $k)) {
                    return false;
                }
            }

            return true;
        }

        return $this->every($this->operatorForWhere(...func_get_args()));
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
     * Get a single key's value from the first matching item in the collection.
     *
     * @template TValueDefault
     *
     * @param string $key
     * @param (Closure(): TValueDefault)|TValueDefault $default
     * @return TValue|TValueDefault
     */
    public function value($key, $default = null)
    {
        if ($value = $this->firstWhere($key)) {
            return data_get($value, $key, $default);
        }

        return value($default);
    }

    /**
     * Ensure that every item in the collection is of the expected type.
     *
     * @template TEnsureOfType
     *
     * @param array<array-key, class-string<TEnsureOfType>>|class-string<TEnsureOfType> $type
     * @return static<TKey, TEnsureOfType>
     *
     * @throws UnexpectedValueException
     */
    public function ensure($type)
    {
        $allowedTypes = is_array($type) ? $type : [$type];

        return $this->each(function ($item, $index) use ($allowedTypes) {
            $itemType = get_debug_type($item);

            foreach ($allowedTypes as $allowedType) {
                if ($itemType === $allowedType || $item instanceof $allowedType) {
                    return true;
                }
            }

            throw new UnexpectedValueException(
                sprintf("Collection should only include [%s] items, but '%s' found at position %d.", implode(', ', $allowedTypes), $itemType, $index)
            );
        });
    }

    /**
     * Determine if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Run a map over each nested chunk of items.
     *
     * @template TMapSpreadValue
     *
     * @param callable(mixed...): TMapSpreadValue $callback
     * @return static<TKey, TMapSpreadValue>
     */
    public function mapSpread(callable $callback)
    {
        return $this->map(function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Run a grouping map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @template TMapToGroupsKey of array-key
     * @template TMapToGroupsValue
     *
     * @param callable(TValue, TKey): array<TMapToGroupsKey, TMapToGroupsValue> $callback
     * @return static<TMapToGroupsKey, static<int, TMapToGroupsValue>>
     */
    public function mapToGroups(callable $callback)
    {
        $groups = $this->mapToDictionary($callback);

        return $groups->map([$this, 'make']);
    }

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @template TFlatMapKey of array-key
     * @template TFlatMapValue
     *
     * @param callable(TValue, TKey): (array<TFlatMapKey, TFlatMapValue>|Collection<TFlatMapKey, TFlatMapValue>) $callback
     * @return static<TFlatMapKey, TFlatMapValue>
     */
    public function flatMap(callable $callback)
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Map the values into a new class.
     *
     * @template TMapIntoValue
     * @param class-string<TMapIntoValue> $class
     * @return static<TKey, TMapIntoValue>
     */
    public function mapInto(mixed $class)
    {
        if (is_subclass_of($class, BackedEnum::class)) {
            return $this->map(fn ($value, $key) => $class::from($value));
        }

        return $this->map(fn ($value, $key) => new $class($value, $key));
    }

    /**
     * Get the min value of a given key.
     *
     * @param null|(callable(TValue):mixed)|string $callback
     */
    public function min(mixed $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        return $this->map(fn ($value) => $callback($value))
            ->filter(fn ($value) => ! is_null($value))
            ->reduce(fn ($result, $value) => is_null($result) || $value < $result ? $value : $result);
    }

    /**
     * Get the max value of a given key.
     *
     * @param null|(callable(TValue):mixed)|string $callback
     */
    public function max(mixed $callback = null): mixed
    {
        $callback = $this->valueRetriever($callback);

        return $this->filter(fn ($value) => ! is_null($value))->reduce(function ($result, $item) use ($callback) {
            $value = $callback($item);

            return is_null($result) || $value > $result ? $value : $result;
        });
    }

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     * @return static
     */
    public function forPage(int $page, int $perPage)
    {
        $offset = max(0, ($page - 1) * $perPage);

        return $this->slice($offset, $perPage);
    }

    /**
     * Partition the collection into two arrays using the given callback or key.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     * @param null|string|TValue $operator
     * @param null|TValue $value
     * @return static<int<0, 1>, static<TKey, TValue>>
     */
    public function partition(mixed $key, mixed $operator = null, mixed $value = null)
    {
        $passed = [];
        $failed = [];

        $callback = func_num_args() === 1
            ? $this->valueRetriever($key)
            : $this->operatorForWhere(...func_get_args());

        foreach ($this as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return new static([new static($passed), new static($failed)]);
    }

    /**
     * Calculate the percentage of items that pass a given truth test.
     *
     * @param (callable(TValue, TKey): bool) $callback
     * @return null|float
     */
    public function percentage(callable $callback, int $precision = 2)
    {
        if ($this->isEmpty()) {
            return null;
        }

        return round(
            $this->filter($callback)->count() / $this->count() * 100,
            $precision
        );
    }

    /**
     * Get the sum of the given values.
     *
     * @param null|(callable(TValue): mixed)|string $callback
     * @return mixed
     */
    public function sum($callback = null)
    {
        $callback = is_null($callback)
            ? $this->identity()
            : $this->valueRetriever($callback);

        return $this->reduce(fn ($result, $item) => $result + $callback($item), 0);
    }

    /**
     * Apply the callback if the collection is empty.
     *
     * @template TWhenEmptyReturnType
     *
     * @param (callable($this): TWhenEmptyReturnType) $callback
     * @param null|(callable($this): TWhenEmptyReturnType) $default
     * @return $this|TWhenEmptyReturnType
     */
    public function whenEmpty(callable $callback, ?callable $default = null)
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Apply the callback if the collection is not empty.
     *
     * @template TWhenNotEmptyReturnType
     *
     * @param callable($this): TWhenNotEmptyReturnType $callback
     * @param null|(callable($this): TWhenNotEmptyReturnType) $default
     * @return $this|TWhenNotEmptyReturnType
     */
    public function whenNotEmpty(callable $callback, ?callable $default = null)
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Apply the callback unless the collection is empty.
     *
     * @template TUnlessEmptyReturnType
     *
     * @param callable($this): TUnlessEmptyReturnType $callback
     * @param null|(callable($this): TUnlessEmptyReturnType) $default
     * @return $this|TUnlessEmptyReturnType
     */
    public function unlessEmpty(callable $callback, ?callable $default = null)
    {
        return $this->whenNotEmpty($callback, $default);
    }

    /**
     * Apply the callback unless the collection is not empty.
     *
     * @template TUnlessNotEmptyReturnType
     *
     * @param callable($this): TUnlessNotEmptyReturnType $callback
     * @param null|(callable($this): TUnlessNotEmptyReturnType) $default
     * @return $this|TUnlessNotEmptyReturnType
     */
    public function unlessNotEmpty(callable $callback, ?callable $default = null)
    {
        return $this->whenEmpty($callback, $default);
    }

    /**
     * Filter items by the given key value pair.
     * @return static
     */
    public function where(null|callable|string $key, mixed $operator = null, mixed $value = null)
    {
        return $this->filter($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Filter items where the value for the given key is null.
     *
     * @param null|string $key
     * @return static
     */
    public function whereNull($key = null)
    {
        return $this->whereStrict($key, null);
    }

    /**
     * Filter items where the value for the given key is not null.
     *
     * @param null|string $key
     * @return static
     */
    public function whereNotNull($key = null)
    {
        return $this->where($key, '!==', null);
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     * @return static
     */
    public function whereStrict(?string $key, mixed $value)
    {
        return $this->where($key, '===', $value);
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereIn(string $key, mixed $values, bool $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->filter(fn ($item) => in_array(data_get($item, $key), $values, $strict));
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereInStrict(string $key, mixed $values)
    {
        return $this->whereIn($key, $values, true);
    }

    /**
     * Filter items such that the value of the given key is between the given values.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereBetween($key, $values)
    {
        return $this->where($key, '>=', reset($values))->where($key, '<=', end($values));
    }

    /**
     * Filter items such that the value of the given key is not between the given values.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereNotBetween($key, $values)
    {
        return $this->filter(
            fn ($item) => data_get($item, $key) < reset($values) || data_get($item, $key) > end($values)
        );
    }

    /**
     * Filter items by the given key value pair.
     *
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereNotIn(string $key, mixed $values, bool $strict = false)
    {
        $values = $this->getArrayableItems($values);

        return $this->reject(fn ($item) => in_array(data_get($item, $key), $values, $strict));
    }

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereNotInStrict(string $key, mixed $values)
    {
        return $this->whereNotIn($key, $values, true);
    }

    /**
     * Filter the items, removing any items that don't match the given type(s).
     *
     * @template TWhereInstanceOf
     *
     * @param array<array-key, class-string<TWhereInstanceOf>>|class-string<TWhereInstanceOf> $type
     * @return static<TKey, TWhereInstanceOf>
     */
    public function whereInstanceOf(array|string $type)
    {
        return $this->filter(function ($value) use ($type) {
            if (is_array($type)) {
                foreach ($type as $classType) {
                    if ($value instanceof $classType) {
                        return true;
                    }
                }

                return false;
            }

            return $value instanceof $type;
        });
    }

    /**
     * Pass the collection to the given callback and return the result.
     *
     * @template TPipeReturnType
     *
     * @param callable($this): TPipeReturnType $callback
     * @return TPipeReturnType
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Pass the collection into a new class.
     *
     * @template TPipeIntoValue
     *
     * @param class-string<TPipeIntoValue> $class
     * @return TPipeIntoValue
     */
    public function pipeInto($class)
    {
        return new $class($this);
    }

    /**
     * Pass the collection through a series of callable pipes and return the result.
     *
     * @param array<callable> $callbacks
     * @return mixed
     */
    public function pipeThrough($callbacks)
    {
        return Collection::make($callbacks)->reduce(
            fn ($carry, $callback) => $callback($carry),
            $this,
        );
    }

    /**
     * Reduce the collection to a single value.
     *
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param callable(TReduceInitial|TReduceReturnType, TValue, TKey): TReduceReturnType $callback
     * @param TReduceInitial $initial
     * @return TReduceReturnType
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;

        foreach ($this as $key => $value) {
            $result = $callback($result, $value, $key);
        }

        return $result;
    }

    /**
     * Reduce the collection to multiple aggregate values.
     *
     * @param mixed ...$initial
     * @return array
     *
     * @throws UnexpectedValueException
     */
    public function reduceSpread(callable $callback, ...$initial)
    {
        $result = $initial;

        foreach ($this as $key => $value) {
            $result = call_user_func_array($callback, array_merge($result, [$value, $key]));

            if (! is_array($result)) {
                throw new UnexpectedValueException(sprintf(
                    "%s::reduceSpread expects reducer to return an array, but got a '%s' instead.",
                    class_basename(static::class),
                    gettype($result)
                ));
            }
        }

        return $result;
    }

    /**
     * Reduce an associative collection to a single value.
     *
     * @template TReduceWithKeysInitial
     * @template TReduceWithKeysReturnType
     *
     * @param callable(TReduceWithKeysInitial|TReduceWithKeysReturnType, TValue, TKey): TReduceWithKeysReturnType $callback
     * @param TReduceWithKeysInitial $initial
     * @return TReduceWithKeysReturnType
     */
    public function reduceWithKeys(callable $callback, $initial = null)
    {
        return $this->reduce($callback, $initial);
    }

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param bool|(callable(TValue, TKey): bool)|TValue $callback
     * @return static
     */
    public function reject(mixed $callback = true)
    {
        $useAsCallable = $this->useAsCallable($callback);

        return $this->filter(function ($value, $key) use ($callback, $useAsCallable) {
            return $useAsCallable
                ? ! $callback($value, $key)
                : $value != $callback;
        });
    }

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param callable($this): mixed $callback
     * @return $this
     */
    public function tap(callable $callback)
    {
        $callback($this);

        return $this;
    }

    /**
     * Return only unique items from the collection array.
     *
     * @param null|(callable(TValue, TKey): mixed)|string $key
     * @return static
     */
    public function unique(mixed $key = null, bool $strict = false)
    {
        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;

            return false;
        });
    }

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param null|(callable(TValue, TKey): mixed)|string $key
     * @return static
     */
    public function uniqueStrict(mixed $key = null)
    {
        return $this->unique($key, true);
    }

    /**
     * Collect the values into a collection.
     *
     * @return Collection<TKey, TValue>
     */
    public function collect()
    {
        return new Collection($this->all());
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray(): array
    {
        return $this->map(fn ($value) => $value instanceof Arrayable ? $value->toArray() : $value)->all();
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<TKey, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = [];
        foreach ($this->all() as $key => $value) {
            $result[$key] = match (true) {
                $value instanceof JsonSerializable => $value->jsonSerialize(),
                $value instanceof Jsonable => json_decode($value->__toString(), true),
                $value instanceof Arrayable => $value->toArray(),
                default => $value
            };
        }

        return $result;
    }

    /**
     * Get the collection of items as JSON.
     *
     * @return string
     */
    public function toJson(int $options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get a CachingIterator instance.
     *
     * @return CachingIterator
     */
    public function getCachingIterator(int $flags = CachingIterator::CALL_TOSTRING)
    {
        /* @phpstan-ignore-next-line */
        return new CachingIterator($this->getIterator(), $flags);
    }

    /**
     * Indicate that the model's string representation should be escaped when __toString is invoked.
     *
     * @param bool $escape
     * @return $this
     */
    public function escapeWhenCastingToString($escape = true)
    {
        $this->escapeWhenCastingToString = $escape;

        return $this;
    }

    /**
     * Add a method to the list of proxied methods.
     */
    public static function proxy(string $method): void
    {
        static::$proxies[] = $method;
    }

    /**
     * Results array of items from Collection or Arrayable.
     * @param mixed $items
     * @return array<TKey,TValue>
     */
    protected function getArrayableItems($items): array
    {
        return match (true) {
            is_array($items) => $items,
            $items instanceof Enumerable => $items->all(),
            $items instanceof Arrayable => $items->toArray(),
            $items instanceof Jsonable => json_decode($items->__toString(), true),
            $items instanceof JsonSerializable => $items->jsonSerialize(),
            $items instanceof Traversable => iterator_to_array($items),
            $items instanceof UnitEnum => [$items],
            default => (array) $items,
        };
    }

    /**
     * Get an operator checker callback.
     *
     * @param callable|string $key
     * @param null|string $operator
     */
    protected function operatorForWhere(mixed $key, mixed $operator = null, mixed $value = null): callable|Closure
    {
        if ($this->useAsCallable($key)) {
            return $key;
        }

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
                case '<=>':
                    return $retrieved <=> $value;
            }
        };
    }

    /**
     * Determine if the given value is callable, but not a string.
     *
     * @param mixed $value
     */
    protected function useAsCallable($value): bool
    {
        return ! is_string($value) && is_callable($value);
    }

    /**
     * Get a value retrieving callback.
     *
     * @param null|callable|string $value
     */
    protected function valueRetriever($value): callable
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return fn ($item) => data_get($item, $value);
    }

    /**
     * Make a function to check an item's equality.
     *
     * @param mixed $value
     * @return Closure(mixed): bool
     */
    protected function equality($value)
    {
        return fn ($item) => $item === $value;
    }

    /**
     * Make a function using another function, by negating its result.
     *
     * @return Closure(TValue,TKey): bool|TValue
     */
    protected function negate(Closure $callback)
    {
        return fn (...$params) => ! $callback(...$params);
    }

    /**
     * Make a function that returns what's passed to it.
     *
     * @return Closure(TValue): TValue
     */
    protected function identity()
    {
        return fn ($value) => $value;
    }
}
