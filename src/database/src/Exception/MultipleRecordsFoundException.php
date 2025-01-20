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

namespace Hyperf\Database\Exception;

use RuntimeException;
use Throwable;

class MultipleRecordsFoundException extends RuntimeException
{
    /**
     * The number of records found.
     */
    public int $count;

    public function __construct(int $count, int $code = 0, ?Throwable $previous = null)
    {
        $this->count = $count;

        parent::__construct("{$count} records were found.", $code, $previous);
    }

    /**
     * Get the number of records found.
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
