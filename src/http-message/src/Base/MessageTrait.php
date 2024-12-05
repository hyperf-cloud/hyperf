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

namespace Hyperf\HttpMessage\Base;

use Hyperf\HttpMessage\Stream\SwooleStream;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Trait implementing functionality common to requests and responses.
 */
trait MessageTrait
{
    /**
     * @var array lowercase headers
     */
    protected array $headerNames = [];

    protected array $headers = [];

    protected string $protocol = '1.1';

    protected ?StreamInterface $stream = null;

    /**
     * Retrieves the HTTP protocol version as a string.
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     */
    public function withProtocolVersion(mixed $version): static
    {
        if ($this->protocol === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }

    /**
     * Retrieves all message header values.
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return string[][] Returns an associative array of the message's headers. Each
     *                    key MUST be a header name, and each value MUST be an array of strings
     *                    for that header.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name case-insensitive header field name
     * @return bool Returns true if any header names match the given header
     *              name using a case-insensitive string comparison. Returns false if
     *              no matching header name is found in the message.
     */
    public function hasHeader(mixed $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name case-insensitive header field name
     * @return string[] An array of string values as provided for the given
     *                  header. If the header does not appear in the message, this method MUST
     *                  return an empty array.
     */
    public function getHeader(mixed $name): array
    {
        $name = strtolower($name);

        if (! isset($this->headerNames[$name])) {
            return [];
        }

        $name = $this->headerNames[$name];

        return $this->headers[$name];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name case-insensitive header field name
     * @return string A string of values as provided for the given header
     *                concatenated together using a comma. If the header does not appear in
     *                the message, this method MUST return an empty string.
     */
    public function getHeaderLine(mixed $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name case-insensitive header field name
     * @param string|string[] $value header value(s)
     * @throws InvalidArgumentException for invalid header names or values
     */
    public function withHeader(mixed $name, mixed $value): static
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        $value = $this->trimHeaderValues($value);
        $normalized = strtolower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    public function withHeaders(array $headers): static
    {
        return (clone $this)->setHeaders($headers);
    }

    /**
     * Return an instance with the specified header appended with the given value.
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name case-insensitive header field name to add
     * @param string|string[] $value header value(s)
     * @throws InvalidArgumentException for invalid header names or values
     */
    public function withAddedHeader(mixed $name, mixed $value): static
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        $value = $this->trimHeaderValues($value);
        $normalized = strtolower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            $name = $this->headerNames[$normalized];
            $new->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    /**
     * Return an instance without the specified header.
     * Header resolution MUST be done without case-sensitivity.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name case-insensitive header field name to remove
     */
    public function withoutHeader(mixed $name): static
    {
        $normalized = strtolower($name);

        if (! isset($this->headerNames[$normalized])) {
            return $this;
        }

        $name = $this->headerNames[$normalized];

        $new = clone $this;
        unset($new->headers[$name], $new->headerNames[$normalized]);

        return $new;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface returns the body as a stream
     */
    public function getBody(): StreamInterface
    {
        if (! $this->stream) {
            $this->stream = new SwooleStream('');
        }

        return $this->stream;
    }

    /**
     * Return an instance with the specified message body.
     * The body MUST be a StreamInterface object.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body body
     * @throws InvalidArgumentException when the body is not valid
     */
    public function withBody(StreamInterface $body): static
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;
        return $new;
    }

    /**
     * Get a specific field from a header like content type or all fields as array.
     *
     * If the header occurs more than once, only the value from the first header
     * is returned.
     *
     * Throws an Exception if the requested header does not exist. If
     * the specific header field does not exist, returns null.
     *
     * @param string $name name of header, like in getHeader()
     * @param string $wantedPart the wanted part, default is first, if null an array with all parts is returned
     * @param string $firstName key name for the first part
     * @return array|string wanted part or all parts as array($firstName => firstPart, partname => value)
     * @throws RuntimeException
     */
    public function getHeaderField(string $name, string $wantedPart = '0', string $firstName = '0')
    {
        return $this->splitHeaderField($this->getHeaderLine($name), $wantedPart, $firstName);
    }

    public function getContentType(): string
    {
        return $this->getHeaderLine('Content-Type');
    }

    /**
     * Check if part is a multipart message.
     *
     * @return bool if part is multipart
     */
    public function isMultipart(): bool
    {
        try {
            return stripos($this->getContentType(), 'multipart/') === 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function setProtocolVersion(string $version): static
    {
        $this->protocol = $version;
        return $this;
    }

    public function setHeader(string $name, mixed $value): static
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        $value = $this->trimHeaderValues($value);
        $normalized = strtolower($name);

        if (isset($this->headerNames[$normalized])) {
            unset($this->headers[$this->headerNames[$normalized]]);
        }
        $this->headerNames[$normalized] = $name;
        $this->headers[$name] = $value;

        return $this;
    }

    public function addHeader(string $name, mixed $value): static
    {
        if (! is_array($value)) {
            $value = [$value];
        }

        $value = $this->trimHeaderValues($value);
        $normalized = strtolower($name);

        if (isset($this->headerNames[$normalized])) {
            $name = $this->headerNames[$normalized];
            $this->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = $value;
        }

        return $this;
    }

    public function unsetHeader(string $name): static
    {
        $normalized = strtolower($name);

        if (! isset($this->headerNames[$normalized])) {
            return $this;
        }

        $name = $this->headerNames[$normalized];

        unset($this->headers[$name], $this->headerNames[$normalized]);

        return $this;
    }

    public function getStandardHeaders(): array
    {
        $headers = $this->getHeaders();
        if (! $this->hasHeader('connection')) {
            $headers['Connection'] = [$this->shouldKeepAlive() ? 'keep-alive' : 'close'];
        }
        if (! $this->hasHeader('content-length')) {
            $headers['Content-Length'] = [(string) ($this->getBody()->getSize() ?? 0)];
        }
        return $headers;
    }

    public function shouldKeepAlive(): bool
    {
        return strtolower($this->getHeaderLine('Connection')) === 'keep-alive';
    }

    public function setBody(StreamInterface $body): static
    {
        $this->stream = $body;
        return $this;
    }

    /**
     * @param array<string, array<string>|string> $headers
     */
    public function setHeaders(array $headers): static
    {
        $this->headerNames = $this->headers = [];
        foreach ($headers as $header => $value) {
            if (! is_array($value)) {
                $value = [$value];
            }

            $value = $this->trimHeaderValues($value);
            $header = (string) $header;

            $normalized = strtolower($header);
            if (isset($this->headerNames[$normalized])) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
        return $this;
    }

    /**
     * Trims whitespace from the header values.
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB ).
     *
     * @param string[] $values Header values
     * @return string[] Trimmed header values
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    private function trimHeaderValues(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[] = trim((string) $value, " \t");
        }
        return $result;
    }

    /**
     * split a header field like content type in its different parts.
     *
     * @param string $field header field
     * @param string $wantedPart the wanted part, else an array with all parts is returned
     * @param string $firstName key name for the first part
     * @return array|string wanted part or all parts as array($firstName => firstPart, partname => value)
     * @throws RuntimeException
     */
    private function splitHeaderField(string $field, ?string $wantedPart = null, string $firstName = '0'): array|string
    {
        $wantedPart = strtolower($wantedPart ?? '');
        $firstName = strtolower($firstName);

        // special case - a bit optimized
        if ($firstName === $wantedPart) {
            $field = strtok($field, ';');
            return $field[0] === '"' ? substr($field, 1, -1) : $field;
        }

        $field = $firstName . '=' . $field;
        if (! preg_match_all('%([^=\s]+)\s*=\s*("[^"]+"|[^;]+)(;\s*|$)%', $field, $matches)) {
            throw new RuntimeException('not a valid header field');
        }

        if ($wantedPart) {
            foreach ($matches[1] as $key => $name) {
                if (strcasecmp($name, $wantedPart)) {
                    continue;
                }
                if ($matches[2][$key][0] !== '"') {
                    return $matches[2][$key];
                }
                return substr($matches[2][$key], 1, -1);
            }
            return '';
        }

        $split = [];
        foreach ($matches[1] as $key => $name) {
            $name = strtolower($name);
            if ($matches[2][$key][0] === '"') {
                $split[$name] = substr($matches[2][$key], 1, -1);
            } else {
                $split[$name] = $matches[2][$key];
            }
        }

        return $split;
    }
}
