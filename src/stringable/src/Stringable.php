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

namespace Hyperf\Stringable;

use ArrayAccess;
use Closure;
use Hyperf\Collection\Collection;
use Hyperf\Conditionable\Conditionable;
use Hyperf\Macroable\Macroable;
use Hyperf\Tappable\Tappable;
use JsonSerializable;

use function Hyperf\Collection\collect;
use function Hyperf\Support\class_basename;

class Stringable implements JsonSerializable, \Stringable, ArrayAccess
{
    use Conditionable;
    use Macroable;
    use Tappable;

    /**
     * The underlying string value.
     */
    protected string $value;

    /**
     * Create a new instance of the class.
     *
     * @param string $value
     */
    public function __construct($value = '')
    {
        $this->value = (string) $value;
    }

    /**
     * Proxy dynamic properties onto methods.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->{$key}();
    }

    /**
     * Get the raw string value.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * Return the remainder of a string after the first occurrence of a given value.
     *
     * @param string $search
     * @return static
     */
    public function after($search)
    {
        return new static(Str::after($this->value, $search));
    }

    /**
     * Return the remainder of a string after the last occurrence of a given value.
     *
     * @param string $search
     * @return static
     */
    public function afterLast($search)
    {
        return new static(Str::afterLast($this->value, $search));
    }

    /**
     * Convert the given string to APA-style title case.
     *
     * @return static
     */
    public function apa()
    {
        return new static(Str::apa($this->value));
    }

    /**
     * Append the given values to the string.
     *
     * @param string $values
     * @return static
     */
    public function append(...$values)
    {
        return new static($this->value . implode('', $values));
    }

    /**
     * Transliterate a UTF-8 value to ASCII.
     *
     * @param string $language
     * @return static
     */
    public function ascii($language = 'en')
    {
        return new static(Str::ascii($this->value, $language));
    }

    /**
     * Get the trailing name component of the path.
     *
     * @param string $suffix
     * @return static
     */
    public function basename($suffix = '')
    {
        return new static(basename($this->value, $suffix));
    }

    /**
     * Get the character at the specified index.
     *
     * @param int $index
     * @return false|string
     */
    public function charAt($index)
    {
        return Str::charAt($this->value, $index);
    }

    /**
     * Remove the given string if it exists at the end of the current string.
     *
     * @param array|string $needle
     * @return static
     */
    public function chopEnd($needle)
    {
        return new static(Str::chopEnd($this->value, $needle));
    }

    /**
     * Remove the given string if it exists at the start of the current string.
     *
     * @param array|string $needle
     * @return static
     */
    public function chopStart($needle)
    {
        return new static(Str::chopStart($this->value, $needle));
    }

    /**
     * Get the basename of the class path.
     *
     * @return static
     */
    public function classBasename()
    {
        return new static(class_basename($this->value));
    }

    /**
     * Get the portion of a string before the first occurrence of a given value.
     *
     * @param string $search
     * @return static
     */
    public function before($search)
    {
        return new static(Str::before($this->value, $search));
    }

    /**
     * Get the portion of a string before the last occurrence of a given value.
     *
     * @param string $search
     * @return static
     */
    public function beforeLast($search)
    {
        return new static(Str::beforeLast($this->value, $search));
    }

    /**
     * Get the portion of a string between two given values.
     *
     * @param string $from
     * @param string $to
     * @return static
     */
    public function between($from, $to)
    {
        return new static(Str::between($this->value, $from, $to));
    }

    /**
     * Convert a value to camel case.
     *
     * @return static
     */
    public function camel()
    {
        return new static(Str::camel($this->value));
    }

    /**
     * Determine if a given string contains a given substring.
     *
     * @param string|string[] $needles
     * @return bool
     */
    public function contains($needles)
    {
        return Str::contains($this->value, $needles);
    }

    /**
     * Determine if a given string contains all array values.
     *
     * @return bool
     */
    public function containsAll(array $needles)
    {
        return Str::containsAll($this->value, $needles);
    }

    /**
     * Get the parent directory's path.
     *
     * @param int $levels
     * @return static
     */
    public function dirname($levels = 1)
    {
        return new static(dirname($this->value, $levels));
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param string|string[] $needles
     * @return bool
     */
    public function endsWith($needles)
    {
        return Str::endsWith($this->value, $needles);
    }

    /**
     * Determine if the string is an exact match with the given value.
     *
     * @param string|\Stringable $value
     * @return bool
     */
    public function exactly($value)
    {
        if ($value instanceof \Stringable) {
            $value = $value->__toString();
        }

        return $this->value === $value;
    }

    /**
     * Explode the string into an array.
     *
     * @param string $delimiter
     * @param int $limit
     * @return Collection
     */
    public function explode($delimiter, $limit = PHP_INT_MAX)
    {
        return collect(explode($delimiter, $this->value, $limit));
    }

    /**
     * Split a string using a regular expression or by length.
     *
     * @param int|string $pattern
     * @param int $limit
     * @param int $flags
     * @return Collection
     */
    public function split($pattern, $limit = -1, $flags = 0)
    {
        if (filter_var($pattern, FILTER_VALIDATE_INT) !== false) {
            return collect(mb_str_split($this->value, $pattern));
        }

        $segments = preg_split($pattern, $this->value, $limit, $flags);

        return ! empty($segments) ? collect($segments) : collect();
    }

    /**
     * Cap a string with a single instance of a given value.
     *
     * @param string $cap
     * @return static
     */
    public function finish($cap)
    {
        return new static(Str::finish($this->value, $cap));
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param string|string[] $pattern
     * @param bool $ignoreCase
     * @return bool
     */
    public function is($pattern, $ignoreCase = false)
    {
        return Str::is($pattern, $this->value, $ignoreCase);
    }

    /**
     * Determine if a given string is 7 bit ASCII.
     *
     * @return bool
     */
    public function isAscii()
    {
        return Str::isAscii($this->value);
    }

    /**
     * Determine if the given string is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->value === '';
    }

    /**
     * Determine if the given string is not empty.
     *
     * @return bool
     */
    public function isNotEmpty()
    {
        return ! $this->isEmpty();
    }

    public function isUlid(): bool
    {
        return Str::isUlid($this->value);
    }

    public function isUrl(): bool
    {
        return Str::isUrl($this->value);
    }

    public function isUuid(): bool
    {
        return Str::isUuid($this->value);
    }

    /**
     * Convert a string to kebab case.
     *
     * @return static
     */
    public function kebab()
    {
        return new static(Str::kebab($this->value));
    }

    /**
     * Return the length of the given string.
     *
     * @param string $encoding
     * @return int
     */
    public function length($encoding = null)
    {
        return Str::length($this->value, $encoding);
    }

    /**
     * Limit the number of characters in a string.
     *
     * @param int $limit
     * @param string $end
     * @return static
     */
    public function limit($limit = 100, $end = '...')
    {
        return new static(Str::limit($this->value, $limit, $end));
    }

    /**
     * Convert the given string to lower-case.
     *
     * @return static
     */
    public function lower()
    {
        return new static(Str::lower($this->value));
    }

    /**
     * Get the string matching the given pattern.
     *
     * @param string $pattern
     * @return static
     */
    public function match($pattern)
    {
        preg_match($pattern, $this->value, $matches);

        if (! $matches) {
            return new static();
        }

        return new static($matches[1] ?? $matches[0]);
    }

    /**
     * Determine if a given string matches a given pattern.
     *
     * @param iterable<string>|string $pattern
     * @return bool
     */
    public function isMatch($pattern)
    {
        return Str::isMatch($pattern, $this->value);
    }

    /**
     * Get the string matching the given pattern.
     *
     * @param string $pattern
     * @return Collection
     */
    public function matchAll($pattern)
    {
        preg_match_all($pattern, $this->value, $matches);

        if (empty($matches[0])) {
            return collect();
        }

        return collect($matches[1] ?? $matches[0]);
    }

    /**
     * Determine if the string matches the given pattern.
     *
     * @param string $pattern
     * @return bool
     */
    public function test($pattern)
    {
        return $this->match($pattern)->isNotEmpty();
    }

    /**
     * Pad both sides of the string with another.
     *
     * @param int $length
     * @param string $pad
     * @return static
     */
    public function padBoth($length, $pad = ' ')
    {
        return new static(Str::padBoth($this->value, $length, $pad));
    }

    /**
     * Pad the left side of the string with another.
     *
     * @param int $length
     * @param string $pad
     * @return static
     */
    public function padLeft($length, $pad = ' ')
    {
        return new static(Str::padLeft($this->value, $length, $pad));
    }

    /**
     * Pad the right side of the string with another.
     *
     * @param int $length
     * @param string $pad
     * @return static
     */
    public function padRight($length, $pad = ' ')
    {
        return new static(Str::padRight($this->value, $length, $pad));
    }

    /**
     * Parse a Class@method style callback into class and method.
     *
     * @param null|string $default
     * @return array
     */
    public function parseCallback($default = null)
    {
        return Str::parseCallback($this->value, $default);
    }

    /**
     * Call the given callback and return a new string.
     *
     * @return static
     */
    public function pipe(callable $callback)
    {
        return new static(call_user_func($callback, $this));
    }

    /**
     * Get the plural form of an English word.
     *
     * @param int $count
     * @return static
     */
    public function plural($count = 2)
    {
        return new static(Str::plural($this->value, $count));
    }

    /**
     * Pluralize the last word of an English, studly caps case string.
     *
     * @param int $count
     * @return static
     */
    public function pluralStudly($count = 2)
    {
        return new static(Str::pluralStudly($this->value, $count));
    }

    /**
     * Find the multi-byte safe position of the first occurrence of the given substring.
     *
     * @param string $needle
     * @param int $offset
     * @param null|string $encoding
     * @return false|int
     */
    public function position($needle, $offset = 0, $encoding = null)
    {
        return Str::position($this->value, $needle, $offset, $encoding);
    }

    /**
     * Prepend the given values to the string.
     *
     * @param string $values
     * @return static
     */
    public function prepend(...$values)
    {
        return new static(implode('', $values) . $this->value);
    }

    /**
     * Remove any occurrence of the given string in the subject.
     *
     * @param string|string[] $search
     * @param bool $caseSensitive
     * @return static
     */
    public function remove($search, $caseSensitive = true)
    {
        return new static(Str::remove($search, $this->value, $caseSensitive));
    }

    /**
     * Repeat the string.
     *
     * @return static
     */
    public function repeat(int $times)
    {
        return new static(Str::repeat($this->value, $times));
    }

    /**
     * Replace the given value in the given string.
     *
     * @param string|string[] $search
     * @param string|string[] $replace
     * @return static
     */
    public function replace($search, $replace)
    {
        return new static(Str::replace($search, $replace, $this->value));
    }

    /**
     * Replace a given value in the string sequentially with an array.
     *
     * @param string $search
     * @return static
     */
    public function replaceArray($search, array $replace)
    {
        return new static(Str::replaceArray($search, $replace, $this->value));
    }

    /**
     * Replace the first occurrence of a given value in the string.
     *
     * @param string $search
     * @param string $replace
     * @return static
     */
    public function replaceFirst($search, $replace)
    {
        return new static(Str::replaceFirst($search, $replace, $this->value));
    }

    /**
     * Replace the last occurrence of a given value in the string.
     *
     * @param string $search
     * @param string $replace
     * @return static
     */
    public function replaceLast($search, $replace)
    {
        return new static(Str::replaceLast($search, $replace, $this->value));
    }

    /**
     * Replace the patterns matching the given regular expression.
     *
     * @param string $pattern
     * @param Closure|string $replace
     * @param int $limit
     * @return static
     */
    public function replaceMatches($pattern, $replace, $limit = -1)
    {
        return new static(Str::replaceMatches($pattern, $replace, $this->value, $limit));
    }

    /**
     * Begin a string with a single instance of a given value.
     *
     * @param string $prefix
     * @return static
     */
    public function start($prefix)
    {
        return new static(Str::start($this->value, $prefix));
    }

    /**
     * Strip HTML and PHP tags from the given string.
     *
     * @param null|string|string[] $allowedTags
     * @return static
     */
    public function stripTags($allowedTags = null)
    {
        return new static(strip_tags($this->value, $allowedTags));
    }

    /**
     * Convert the given string to upper-case.
     *
     * @return static
     */
    public function upper()
    {
        return new static(Str::upper($this->value));
    }

    /**
     * Convert the given string to title case.
     *
     * @return static
     */
    public function title()
    {
        return new static(Str::title($this->value));
    }

    /**
     * Convert the given string to proper case for each word.
     *
     * @return static
     */
    public function headline()
    {
        return new static(Str::headline($this->value));
    }

    /**
     * Get the singular form of an English word.
     *
     * @return static
     */
    public function singular()
    {
        return new static(Str::singular($this->value));
    }

    /**
     * Generate a URL friendly "slug" from a given string.
     *
     * @param string $separator
     * @param null|string $language
     * @return static
     */
    public function slug($separator = '-', $language = 'en')
    {
        return new static(Str::slug($this->value, $separator, $language));
    }

    /**
     * Convert a string to snake case.
     *
     * @param string $delimiter
     * @return static
     */
    public function snake($delimiter = '_')
    {
        return new static(Str::snake($this->value, $delimiter));
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string|string[] $needles
     * @return bool
     */
    public function startsWith($needles)
    {
        return Str::startsWith($this->value, $needles);
    }

    /**
     * Convert a value to studly caps case.
     *
     * @return static
     */
    public function studly()
    {
        return new static(Str::studly($this->value));
    }

    /**
     * Returns the portion of the string specified by the start and length parameters.
     *
     * @param int $start
     * @param null|int $length
     * @return static
     */
    public function substr($start, $length = null)
    {
        return new static(Str::substr($this->value, $start, $length));
    }

    /**
     * Returns the number of substring occurrences.
     *
     * @param string $needle
     * @param null|int $offset
     * @param null|int $length
     * @return int
     */
    public function substrCount($needle, $offset = null, $length = null)
    {
        return Str::substrCount($this->value, $needle, $offset ?? 0, $length);
    }

    /**
     * Take the first or last {$limit} characters.
     *
     * @return static
     */
    public function take(int $limit)
    {
        if ($limit < 0) {
            return $this->substr($limit);
        }

        return $this->substr(0, $limit);
    }

    /**
     * Convert the string to Base64 encoding.
     *
     * @return static
     */
    public function toBase64()
    {
        return new static(base64_encode($this->value));
    }

    /**
     * Trim the string of the given characters.
     *
     * @param string $characters
     * @return static
     */
    public function trim($characters = null)
    {
        return new static(Str::trim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Left trim the string of the given characters.
     *
     * @param string $characters
     * @return static
     */
    public function ltrim($characters = null)
    {
        return new static(Str::ltrim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Right trim the string of the given characters.
     *
     * @param string $characters
     * @return static
     */
    public function rtrim($characters = null)
    {
        return new static(Str::rtrim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Make a string's first character uppercase.
     *
     * @return static
     */
    public function ucfirst()
    {
        return new static(Str::ucfirst($this->value));
    }

    /**
     * Unwrap the string with the given strings.
     *
     * @param string $before
     * @param null|string $after
     * @return static
     */
    public function unwrap($before, $after = null)
    {
        return new static(Str::unwrap($this->value, $before, $after));
    }

    /**
     * Replaces the first or the last ones chars from a string by a given char.
     *
     * @param int $offset if is negative it starts from the end
     * @param string $replacement default is *
     * @return static
     */
    public function mask(int $offset = 0, int $length = 0, string $replacement = '*')
    {
        return new static(Str::mask($this->value, $offset, $length, $replacement));
    }

    /**
     * Execute the given callback if the string is empty.
     *
     * @param callable $callback
     * @param null|callable $default
     * @return static
     */
    public function whenEmpty($callback, $default = null)
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Execute the given callback if the string is not empty.
     *
     * @param callable $callback
     * @param null|callable $default
     * @return static
     */
    public function whenNotEmpty($callback, $default = null)
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Limit the number of words in a string.
     *
     * @param int $words
     * @param string $end
     * @return static
     */
    public function words($words = 100, $end = '...')
    {
        return new static(Str::words($this->value, $words, $end));
    }

    /**
     * Get the number of words a string contains.
     *
     * @return int
     */
    public function wordCount()
    {
        return str_word_count($this->value);
    }

    /**
     * Convert the object to a string when JSON encoded.
     */
    public function jsonSerialize(): mixed
    {
        return $this->__toString();
    }

    /**
     * Determine if the given offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->value[$offset]);
    }

    /**
     * Get the value at the given offset.
     */
    public function offsetGet(mixed $offset): string
    {
        return $this->value[$offset];
    }

    /**
     * Set the value at the given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->value[$offset] = $value;
    }

    /**
     * Unset the value at the given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->value[$offset]);
    }

    public function betweenFirst($from, $to): static
    {
        return new static(Str::betweenFirst($this->value, $from, $to));
    }

    public function classNamespace(): static
    {
        return new static(Str::classNamespace($this->value));
    }

    public function convertCase($mode = MB_CASE_FOLD, $encoding = 'UTF-8'): static
    {
        return new static(Str::convertCase($this->value, $mode, $encoding));
    }

    public function excerpt($phrase = '', $options = []): ?string
    {
        return Str::excerpt($this->value, $phrase, $options);
    }

    public function isJson(): bool
    {
        return Str::isJson($this->value);
    }

    public function lcfirst(): static
    {
        return new static(Str::lcfirst($this->value));
    }

    public function newLine($count = 1): static
    {
        return $this->append(str_repeat(PHP_EOL, $count));
    }

    public function replaceStart($search, $replace): static
    {
        return new static(Str::replaceStart($search, $replace, $this->value));
    }

    public function replaceEnd($search, $replace): static
    {
        return new static(Str::replaceEnd($search, $replace, $this->value));
    }

    public function reverse(): static
    {
        return new static(Str::reverse($this->value));
    }

    public function scan($format): Collection
    {
        return collect(sscanf($this->value, $format));
    }

    public function squish(): static
    {
        return new static(Str::squish($this->value));
    }

    public function substrReplace($replace, $offset = 0, $length = null): static
    {
        return new static(Str::substrReplace($this->value, $replace, $offset, $length));
    }

    public function swap(array $map)
    {
        return new static(strtr($this->value, $map));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function ucsplit(): Collection
    {
        return collect(Str::ucsplit($this->value));
    }

    public function value(): string
    {
        return $this->toString();
    }

    public function whenContains($needles, $callback, $default = null)
    {
        return $this->when($this->contains($needles), $callback, $default);
    }

    public function whenContainsAll(array $needles, $callback, $default = null)
    {
        return $this->when($this->containsAll($needles), $callback, $default);
    }

    public function whenEndsWith($needles, $callback, $default = null)
    {
        return $this->when($this->endsWith($needles), $callback, $default);
    }

    public function whenExactly($needles, $callback, $default = null)
    {
        return $this->when($this->exactly($needles), $callback, $default);
    }

    public function whenIs($pattern, $callback, $default = null, $ignoreCase = false)
    {
        return $this->when($this->is($pattern, $ignoreCase), $callback, $default);
    }

    public function whenIsUlid($callback, $default = null)
    {
        return $this->when($this->isUlid(), $callback, $default);
    }

    public function whenIsUuid($callback, $default = null)
    {
        return $this->when($this->isUuid(), $callback, $default);
    }

    public function whenNotExactly($needles, $callback, $default = null)
    {
        return $this->when(! $this->exactly($needles), $callback, $default);
    }

    public function whenStartsWith($needles, $callback, $default = null)
    {
        return $this->when($this->startsWith($needles), $callback, $default);
    }

    public function whenTest($pattern, $callback, $default = null)
    {
        return $this->when($this->test($pattern), $callback, $default);
    }

    public function wrap($before, $after = null)
    {
        return new static(Str::wrap($this->value, $before, $after));
    }

    /**
     * Wrap a string to a given number of characters.
     *
     * @param mixed $characters
     * @param mixed $break
     * @param mixed $cutLongWords
     */
    public function wordWrap($characters = 75, $break = "\n", $cutLongWords = false): static
    {
        return new static(Str::wordWrap($this->value, $characters, $break, $cutLongWords));
    }
}
