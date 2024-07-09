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

use CachingIterator;
use Closure;
use Countable;
use Exception;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use UnexpectedValueException;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Arrayable<TKey, TValue>
 * @extends IteratorAggregate<TKey, TValue>
 */
interface Enumerable extends Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    /**
     * Convert the collection to its string representation.
     */
    public function __toString(): string;

    /**
     * Dynamically access collection proxies.
     *
     * @param string $key
     * @return mixed
     *
     * @throws Exception
     */
    public function __get($key);

    /**
     * Create a new collection instance if the value isn't one already.
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     *
     * @param null|Arrayable<TMakeKey, TMakeValue>|iterable<TMakeKey, TMakeValue> $items
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make(mixed $items = []): static;

    /**
     * Create a new instance by invoking the callback a given amount of times.
     */
    public static function times(int $number, ?callable $callback = null): static;

    /**
     * Create a collection with the given range.
     */
    public static function range(float|int|string $from, float|int|string $to): static;

    /**
     * Wrap the given value in a collection if applicable.
     *
     * @template TWrapValue
     *
     * @param iterable<array-key, TWrapValue>|TWrapValue $value
     * @return static<array-key, TWrapValue>
     */
    public static function wrap(mixed $value): static;

    /**
     * Get the underlying items from the given collection if applicable.
     *
     * @template TUnwrapKey of array-key
     * @template TUnwrapValue
     *
     * @param array<TUnwrapKey, TUnwrapValue>|static<TUnwrapKey, TUnwrapValue> $value
     * @return array<TUnwrapKey, TUnwrapValue>
     */
    public static function unwrap(mixed $value): array;

    /**
     * Create a new instance with no items.
     */
    public static function empty(): static;

    /**
     * Get all items in the enumerable.
     */
    public function all(): array;

    /**
     * Alias for the "avg" method.
     *
     * @param null|(callable(TValue): float|int)|string $callback
     */
    public function average(mixed $callback = null): null|float|int;

    /**
     * Get the median of a given key.
     *
     * @param null|array<array-key, string>|string $key
     * @return null|float|int
     */
    public function median($key = null);

    /**
     * Get the mode of a given key.
     *
     * @param null|array<array-key, string>|string $key
     * @return null|array<int, float|int>
     */
    public function mode($key = null);

    /**
     * Collapse the items into a single enumerable.
     *
     * @return static<int, mixed>
     */
    public function collapse(): static;

    /**
     * Alias for the "contains" method.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function some($key, $operator = null, $value = null);

    /**
     * Determine if an item exists, using strict comparison.
     *
     * @param array-key|(callable(TValue): bool)|TValue $key
     * @param null|TValue $value
     * @return bool
     */
    public function containsStrict($key, $value = null);

    /**
     * Get the average value of a given key.
     *
     * @param null|(callable(TValue): float|int)|string $callback
     */
    public function avg(mixed $callback = null): null|float|int;

    /**
     * Determine if an item exists in the enumerable.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function contains($key, $operator = null, $value = null);

    /**
     * Determine if an item is not contained in the collection.
     *
     * @param mixed $key
     * @param mixed $operator
     * @param mixed $value
     * @return bool
     */
    public function doesntContain($key, $operator = null, $value = null);

    /**
     * Cross join with the given lists, returning all possible permutations.
     *
     * @template TCrossJoinKey
     * @template TCrossJoinValue
     *
     * @param Arrayable<TCrossJoinKey, TCrossJoinValue>|iterable<TCrossJoinKey, TCrossJoinValue> ...$lists
     * @return static<int, array<int, TCrossJoinValue|TValue>>
     */
    public function crossJoin(...$lists);

    /**
     * Dump the collection and end the script.
     *
     * @param mixed ...$args
     * @return never
     */
    public function dd(...$args);

    /**
     * Dump the collection.
     *
     * @param mixed ...$args
     * @return $this
     */
    public function dump(...$args);

    /**
     * Get the items that are not present in the given items.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     */
    public function diff($items): static;

    /**
     * Get the items that are not present in the given items, using the callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int $callback
     * @return static
     */
    public function diffUsing($items, callable $callback);

    /**
     * Get the items whose keys and values are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function diffAssoc($items);

    /**
     * Get the items whose keys and values are not present in the given items, using the callback.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @param callable(TKey, TKey): int $callback
     * @return static
     */
    public function diffAssocUsing($items, callable $callback);

    /**
     * Get the items whose keys are not present in the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function diffKeys($items);

    /**
     * Get the items whose keys are not present in the given items, using the callback.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @param callable(TKey, TKey): int $callback
     * @return static
     */
    public function diffKeysUsing($items, callable $callback);

    /**
     * Retrieve duplicate items.
     *
     * @param null|(callable(TValue): bool)|string $callback
     * @param bool $strict
     * @return static
     */
    public function duplicates($callback = null, $strict = false);

    /**
     * Retrieve duplicate items using strict comparison.
     *
     * @param null|(callable(TValue): bool)|string $callback
     * @return static
     */
    public function duplicatesStrict($callback = null);

    /**
     * Execute a callback over each item.
     *
     * @param callable(TValue, TKey): mixed $callback
     */
    public function each(callable $callback): static;

    /**
     * Execute a callback over each nested chunk of items.
     */
    public function eachSpread(callable $callback): static;

    /**
     * Determine if all items pass the given truth test.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     */
    public function every(mixed $key, mixed $operator = null, mixed $value = null): bool;

    /**
     * Get all items except for those with the specified keys.
     *
     * @param array<array-key, TKey>|Enumerable<array-key, TKey> $keys
     */
    public function except($keys): static;

    /**
     * Run a filter over each of the items.
     *
     * @param null|(callable(TValue): bool) $callback
     * @return static
     */
    public function filter(?callable $callback = null);

    /**
     * Apply the callback if the given "value" is (or resolves to) truthy.
     *
     * @template TWhenReturnType
     *
     * @param bool $value
     * @param null|(callable($this): TWhenReturnType) $callback
     * @param null|(callable($this): TWhenReturnType) $default
     * @return $this|TWhenReturnType
     */
    public function when($value, ?callable $callback = null, ?callable $default = null);

    /**
     * Apply the callback if the collection is empty.
     *
     * @template TWhenEmptyReturnType
     *
     * @param (callable($this): TWhenEmptyReturnType) $callback
     * @param null|(callable($this): TWhenEmptyReturnType) $default
     * @return $this|TWhenEmptyReturnType
     */
    public function whenEmpty(callable $callback, ?callable $default = null);

    /**
     * Apply the callback if the collection is not empty.
     *
     * @template TWhenNotEmptyReturnType
     *
     * @param callable($this): TWhenNotEmptyReturnType $callback
     * @param null|(callable($this): TWhenNotEmptyReturnType) $default
     * @return $this|TWhenNotEmptyReturnType
     */
    public function whenNotEmpty(callable $callback, ?callable $default = null);

    /**
     * Apply the callback if the given "value" is (or resolves to) truthy.
     *
     * @template TUnlessReturnType
     *
     * @param bool $value
     * @param (callable($this): TUnlessReturnType) $callback
     * @param null|(callable($this): TUnlessReturnType) $default
     * @return $this|TUnlessReturnType
     */
    public function unless($value, ?callable $callback = null, ?callable $default = null);

    /**
     * Apply the callback unless the collection is empty.
     *
     * @template TUnlessEmptyReturnType
     *
     * @param callable($this): TUnlessEmptyReturnType $callback
     * @param null|(callable($this): TUnlessEmptyReturnType) $default
     * @return $this|TUnlessEmptyReturnType
     */
    public function unlessEmpty(callable $callback, ?callable $default = null);

    /**
     * Apply the callback unless the collection is not empty.
     *
     * @template TUnlessNotEmptyReturnType
     *
     * @param callable($this): TUnlessNotEmptyReturnType $callback
     * @param null|(callable($this): TUnlessNotEmptyReturnType) $default
     * @return $this|TUnlessNotEmptyReturnType
     */
    public function unlessNotEmpty(callable $callback, ?callable $default = null);

    /**
     * Filter items by the given key value pair.
     */
    public function where(callable|string $key, mixed $operator = null, mixed $value = null): static;

    /**
     * Filter items where the value for the given key is null.
     *
     * @param null|string $key
     * @return static
     */
    public function whereNull($key = null);

    /**
     * Filter items where the value for the given key is not null.
     *
     * @param null|string $key
     * @return static
     */
    public function whereNotNull($key = null);

    /**
     * Filter items by the given key value pair using strict comparison.
     */
    public function whereStrict(string $key, mixed $value): static;

    /**
     * Filter items by the given key value pair.
     *
     * @param Arrayable|iterable $values
     */
    public function whereIn(string $key, mixed $values, bool $strict = false): static;

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param Arrayable|iterable $values
     */
    public function whereInStrict(string $key, mixed $values): static;

    /**
     * Filter items such that the value of the given key is between the given values.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereBetween($key, $values);

    /**
     * Filter items such that the value of the given key is not between the given values.
     *
     * @param string $key
     * @param Arrayable|iterable $values
     * @return static
     */
    public function whereNotBetween($key, $values);

    /**
     * Filter items by the given key value pair.
     *
     * @param Arrayable|iterable $values
     */
    public function whereNotIn(string $key, mixed $values, bool $strict = false): static;

    /**
     * Filter items by the given key value pair using strict comparison.
     *
     * @param Arrayable|iterable $values
     */
    public function whereNotInStrict(string $key, mixed $values): static;

    /**
     * Filter the items, removing any items that don't match the given type(s).
     *
     * @template TWhereInstanceOf
     *
     * @param array<array-key, class-string<TWhereInstanceOf>>|class-string<TWhereInstanceOf> $type
     * @return static<TKey, TWhereInstanceOf>
     */
    public function whereInstanceOf(array|string $type): static;

    /**
     * Get the first item from the enumerable passing the given truth test.
     *
     * @template TFirstDefault
     *
     * @param null|(callable(TValue,TKey): bool) $callback
     * @param (Closure(): TFirstDefault)|TFirstDefault $default
     * @return TFirstDefault|TValue
     */
    public function first(?callable $callback = null, $default = null);

    /**
     * Get the first item by the given key value pair.
     *
     * @param string $key
     * @return null|TValue
     */
    public function firstWhere(callable|string $key, mixed $operator = null, mixed $value = null): mixed;

    /**
     * Get a flattened array of the items in the collection.
     */
    public function flatten(float|int $depth = INF): static;

    /**
     * Flip the values with their keys.
     *
     * @return static<TValue, TKey>
     */
    public function flip(): static;

    /**
     * Get an item from the collection by key.
     *
     * @template TGetDefault
     *
     * @param TKey $key
     * @param (Closure(): TGetDefault)|TGetDefault $default
     * @return TGetDefault|TValue
     */
    public function get($key, $default = null);

    /**
     * Group an associative array by a field or using a callback.
     *
     * @param array|(callable(TValue, TKey): array-key)|string $groupBy
     * @return static<array-key, static<array-key, TValue>>
     */
    public function groupBy($groupBy, bool $preserveKeys = false): static;

    /**
     * Key an associative array by a field or using a callback.
     *
     * @param array|(callable(TValue, TKey): array-key)|string $keyBy
     * @return static<array-key, TValue>
     */
    public function keyBy($keyBy);

    /**
     * Determine if an item exists in the collection by key.
     *
     * @param array<array-key, TKey>|TKey $key
     * @return bool
     */
    public function has($key);

    /**
     * Determine if any of the keys exist in the collection.
     *
     * @param mixed $key
     * @return bool
     */
    public function hasAny($key);

    /**
     * Concatenate values of a given key as a string.
     */
    public function implode(array|callable|string $value, ?string $glue = null): string;

    /**
     * Intersect the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     */
    public function intersect(mixed $items): static;

    /**
     * Intersect the collection with the given items, using the callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int $callback
     * @return static
     */
    public function intersectUsing($items, callable $callback);

    /**
     * Intersect the collection with the given items with additional index check.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function intersectAssoc($items);

    /**
     * Intersect the collection with the given items with additional index check, using the callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int $callback
     * @return static
     */
    public function intersectAssocUsing($items, callable $callback);

    /**
     * Intersect the collection with the given items by key.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function intersectByKeys($items);

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Determine if the collection is not empty.
     */
    public function isNotEmpty(): bool;

    /**
     * Determine if the collection contains a single item.
     *
     * @return bool
     */
    public function containsOneItem();

    /**
     * Join all items from the collection using a string. The final items can use a separate glue string.
     *
     * @param string $glue
     * @param string $finalGlue
     * @return string
     */
    public function join($glue, $finalGlue = '');

    /**
     * Get the keys of the collection items.
     *
     * @return static<int, TKey>
     */
    public function keys();

    /**
     * Get the last item from the collection.
     *
     * @template TLastDefault
     *
     * @param null|(callable(TValue, TKey): bool) $callback
     * @param (Closure(): TLastDefault)|TLastDefault $default
     * @return TLastDefault|TValue
     */
    public function last(?callable $callback = null, $default = null);

    /**
     * Run a map over each of the items.
     *
     * @template TMapValue
     *
     * @param callable(TValue, TKey): TMapValue $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback): static;

    /**
     * Run a map over each nested chunk of items.
     */
    public function mapSpread(callable $callback): static;

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
    public function mapToDictionary(callable $callback): static;

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
    public function mapToGroups(callable $callback): static;

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
    public function mapWithKeys(callable $callback): static;

    /**
     * Map a collection and flatten the result by a single level.
     *
     * @template TFlatMapKey of array-key
     * @template TFlatMapValue
     *
     * @param callable(TValue, TKey): (array<TFlatMapKey, TFlatMapValue>|Collection<TFlatMapKey, TFlatMapValue>) $callback
     * @return static<TFlatMapKey, TFlatMapValue>
     */
    public function flatMap(callable $callback): static;

    /**
     * Map the values into a new class.
     *
     * @template TMapIntoValue
     *
     * @param class-string<TMapIntoValue> $class
     * @return static<TKey, TMapIntoValue>
     */
    public function mapInto(mixed $class): static;

    /**
     * Merge the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function merge($items);

    /**
     * Recursively merge the collection with the given items.
     *
     * @template TMergeRecursiveValue
     *
     * @param Arrayable<TKey, TMergeRecursiveValue>|iterable<TKey, TMergeRecursiveValue> $items
     * @return static<TKey, TMergeRecursiveValue|TValue>
     */
    public function mergeRecursive($items);

    /**
     * Create a collection by using this collection for keys and another for its values.
     *
     * @template TCombineValue
     *
     * @param Arrayable<array-key, TCombineValue>|iterable<array-key, TCombineValue> $values
     * @return static<TValue, TCombineValue>
     */
    public function combine($values);

    /**
     * Union the collection with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function union($items);

    /**
     * Get the min value of a given key.
     *
     * @param null|(callable(TValue):mixed)|string $callback
     */
    public function min(mixed $callback = null): mixed;

    /**
     * Get the max value of a given key.
     *
     * @param null|(callable(TValue):mixed)|string $callback
     */
    public function max(mixed $callback = null): mixed;

    /**
     * Create a new collection consisting of every n-th element.
     */
    public function nth(int $step, int $offset = 0): static;

    /**
     * Get the items with the specified keys.
     *
     * @param null|array<array-key, TKey>|Enumerable<array-key, TKey>|string $keys
     */
    public function only($keys): static;

    /**
     * "Paginate" the collection by slicing it into a smaller collection.
     */
    public function forPage(int $page, int $perPage): static;

    /**
     * Partition the collection into two arrays using the given callback or key.
     *
     * @param (callable(TValue, TKey): bool)|string|TValue $key
     * @return static<int<0, 1>, static<TKey, TValue>>
     */
    public function partition(mixed $key, mixed $operator = null, mixed $value = null): static;

    /**
     * Push all of the given items onto the collection.
     *
     * @template TConcatKey of array-key
     * @template TConcatValue
     *
     * @param iterable<TConcatKey, TConcatValue> $source
     * @return static<TConcatKey|TKey, TConcatValue|TValue>
     */
    public function concat($source);

    /**
     * Get one or a specified number of items randomly from the collection.
     *
     * @return static<int, TValue>|TValue
     *
     * @throws InvalidArgumentException
     */
    public function random(?int $number = null);

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
    public function reduce(callable $callback, mixed $initial = null): mixed;

    /**
     * Reduce the collection to multiple aggregate values.
     *
     * @param mixed ...$initial
     * @return array
     *
     * @throws UnexpectedValueException
     */
    public function reduceSpread(callable $callback, ...$initial);

    /**
     * Replace the collection items with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function replace($items);

    /**
     * Recursively replace the collection items with the given items.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     * @return static
     */
    public function replaceRecursive($items);

    /**
     * Reverse items order.
     *
     * @return static
     */
    public function reverse();

    /**
     * Search the collection for a given value and return the corresponding key if successful.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return bool|TKey
     */
    public function search($value, bool $strict = false);

    /**
     * Get the item before the given item.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     * @return null|TValue
     */
    public function before(mixed $value, bool $strict = false): mixed;

    /**
     * Get the item after the given item.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     * @return null|TValue
     */
    public function after(mixed $value, bool $strict = false): mixed;

    /**
     * Shuffle the items in the collection.
     *
     * @return static
     */
    public function shuffle();

    /**
     * Create chunks representing a "sliding window" view of the items in the collection.
     *
     * @return static<int, static>
     */
    public function sliding(int $size = 2, int $step = 1): static;

    /**
     * Skip the first {$count} items.
     */
    public function skip(int $count): static;

    /**
     * Skip items in the collection until the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function skipUntil($value);

    /**
     * Skip items in the collection while the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function skipWhile($value);

    /**
     * Get a slice of items from the enumerable.
     */
    public function slice(int $offset, ?int $length = null): static;

    /**
     * Split a collection into a certain number of groups.
     *
     * @return static<int, static>
     */
    public function split(int $numberOfGroups): static;

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
    public function sole($key = null, $operator = null, $value = null);

    /**
     * Get the first item in the collection but throw an exception if no matching items exist.
     *
     * @param (callable(TValue, TKey): bool)|string $key
     * @param mixed $operator
     * @param mixed $value
     * @return TValue
     * @throws ItemNotFoundException
     */
    public function firstOrFail($key = null, $operator = null, $value = null);

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @return static<int, static>
     */
    public function chunk(int $size): static;

    /**
     * Chunk the collection into chunks with a callback.
     *
     * @param callable(TValue, TKey, static<int, TValue>): bool $callback
     * @return static<int, static<int, TValue>>
     */
    public function chunkWhile(callable $callback);

    /**
     * Split a collection into a certain number of groups, and fill the first groups completely.
     *
     * @return static<int, static>
     */
    public function splitIn(int $numberOfGroups);

    /**
     * Sort through each item with a callback.
     *
     * @param null|(callable(TValue, TValue): int) $callback
     */
    public function sort(?callable $callback = null): static;

    /**
     * Sort items in descending order.
     */
    public function sortDesc(int $options = SORT_REGULAR): static;

    /**
     * Sort the collection using the given callback.
     *
     * @param array<array-key, array{string, string}|(callable(TValue, TKey): mixed)|(callable(TValue, TValue): mixed)|string>|(callable(TValue, TKey): mixed)|string $callback
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): static;

    /**
     * Sort the collection in descending order using the given callback.
     *
     * @param array<array-key, array{string, string}|(callable(TValue, TKey): mixed)|(callable(TValue, TValue): mixed)|string>|(callable(TValue, TKey): mixed)|string $callback
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR): static;

    /**
     * Sort the collection keys.
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static;

    /**
     * Sort the collection keys in descending order.
     */
    public function sortKeysDesc(int $options = SORT_REGULAR): static;

    /**
     * Sort the collection keys using a callback.
     *
     * @param callable(TKey, TKey): int $callback
     */
    public function sortKeysUsing(callable $callback): static;

    /**
     * Get the sum of the given values.
     *
     * @param null|(callable(TValue): mixed)|string $callback
     * @return mixed
     */
    public function sum($callback = null);

    /**
     * Take the first or last {$limit} items.
     */
    public function take(int $limit): static;

    /**
     * Take items in the collection until the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function takeUntil($value);

    /**
     * Take items in the collection while the given condition is met.
     *
     * @param callable(TValue,TKey): bool|TValue $value
     * @return static
     */
    public function takeWhile($value);

    /**
     * Pass the collection to the given callback and then return it.
     *
     * @param callable(TValue): mixed $callback
     * @return $this
     */
    public function tap(callable $callback): static;

    /**
     * Pass the enumerable to the given callback and return the result.
     *
     * @template TPipeReturnType
     *
     * @param callable($this): TPipeReturnType $callback
     * @return TPipeReturnType
     */
    public function pipe(callable $callback): mixed;

    /**
     * Pass the collection into a new class.
     *
     * @template TPipeIntoValue
     *
     * @param class-string<TPipeIntoValue> $class
     * @return TPipeIntoValue
     */
    public function pipeInto($class);

    /**
     * Pass the collection through a series of callable pipes and return the result.
     *
     * @param array<callable> $pipes
     * @return mixed
     */
    public function pipeThrough($pipes);

    /**
     * Get the values of a given key.
     *
     * @param array<array-key, string>|string $value
     * @return static<int, mixed>
     */
    public function pluck(array|string $value, ?string $key = null): static;

    /**
     * Create a collection of all elements that do not pass a given truth test.
     *
     * @param bool|(callable(TValue, TKey): bool)|TValue $callback
     */
    public function reject(mixed $callback = true): static;

    /**
     * Convert a flatten "dot" notation array into an expanded array.
     */
    public function undot(): static;

    /**
     * Return only unique items from the collection array.
     *
     * @param null|(callable(TValue, TKey): mixed)|string $key
     */
    public function unique(mixed $key = null, bool $strict = false): static;

    /**
     * Return only unique items from the collection array using strict comparison.
     *
     * @param null|(callable(TValue, TKey): mixed)|string $key
     */
    public function uniqueStrict(mixed $key = null): static;

    /**
     * Reset the keys on the underlying array.
     *
     * @return static<int, TValue>
     */
    public function values();

    /**
     * Pad collection to the specified length with a value.
     *
     * @template TPadValue
     *
     * @param TPadValue $value
     * @return static<int, TPadValue|TValue>
     */
    public function pad(int $size, $value): static;

    /**
     * Get the values iterator.
     *
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable;

    /**
     * Count the number of items in the collection.
     */
    public function count(): int;

    /**
     * Count the number of items in the collection by a field or using a callback.
     *
     * @param null|(callable(TValue, TKey): array-key)|string $countBy
     * @return static<array-key, int>
     */
    public function countBy($countBy = null);

    /**
     * Zip the collection together with one or more arrays.
     *
     * e.g. new Collection([1, 2, 3])->zip([4, 5, 6]);
     *      => [[1, 4], [2, 5], [3, 6]]
     *
     * @template TZipValue
     *
     * @param Arrayable<array-key, TZipValue>|iterable<array-key, TZipValue> ...$items
     * @return static<int, static<int, TValue|TZipValue>>
     */
    public function zip($items): static;

    /**
     * Collect the values into a collection.
     *
     * @return Collection<TKey, TValue>
     */
    public function collect(): Collection;

    /**
     * Get the collection of items as a plain array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray(): array;

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): mixed;

    /**
     * Get the collection of items as JSON.
     *
     * @return string
     */
    public function toJson(int $options = 0);

    /**
     * Get a CachingIterator instance.
     *
     * @return CachingIterator
     */
    public function getCachingIterator(int $flags = CachingIterator::CALL_TOSTRING);

    /**
     * Indicate that the model's string representation should be escaped when __toString is invoked.
     *
     * @param bool $escape
     * @return $this
     */
    public function escapeWhenCastingToString($escape = true);

    /**
     * Add a method to the list of proxied methods.
     */
    public static function proxy(string $method): void;
}
