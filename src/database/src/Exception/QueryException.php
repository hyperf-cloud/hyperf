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

use Exception;
use PDOException;
use Throwable;

use function Hyperf\Support\build_sql;

class QueryException extends PDOException
{
    /**
     * Create a new query exception instance.
     *
     * @param string $sql the SQL for the query
     * @param array $bindings the bindings for the query
     */
    public function __construct(
        public ?string $connectionName,
        protected string $sql,
        protected array $bindings,
        Exception $previous
    ) {
        parent::__construct('', 0, $previous);

        $this->code = $previous->getCode();
        $this->message = $this->formatMessage($sql, $bindings, $previous);

        if ($previous instanceof PDOException) {
            $this->errorInfo = $previous->errorInfo;
        }
    }

    /**
     * Get the SQL for the query.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the bindings for the query.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Format the SQL error message.
     */
    protected function formatMessage(string $sql, array $bindings, Throwable $previous): string
    {
        return $previous->getMessage() . ' (SQL: ' . build_sql($sql, $bindings) . ')';
    }
}
