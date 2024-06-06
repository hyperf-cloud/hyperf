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
namespace Hyperf\Codec\Exception;

use Throwable;

class InvalidArgumentException extends \InvalidArgumentException
{
    /**
     * The origin data that caused the exception.
     */
    private mixed $original;

    /**
     * @throws InvalidArgumentException
     */
    public static function throw(Throwable|string $exception, mixed $original): void
    {
        [$message, $code,  $previous] = $exception instanceof Throwable ? [$exception->getMessage(), $exception->getCode(), $exception] : [$exception, 0, null];
        $e = new static($message, $code, $previous);
        $e->original = $original;
        throw $e;
    }

    /**
     * Get the origin data that caused the exception.
     */
    public function getOriginal(): mixed
    {
        return $this->original;
    }
}
