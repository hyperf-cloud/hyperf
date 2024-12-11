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

namespace Hyperf\Database\PgSQL;

use Exception;
use Generator;
use Hyperf\Database\Connection;
use Hyperf\Database\Exception\QueryException;
use Hyperf\Database\Grammar;
use Hyperf\Database\PgSQL\Concerns\PostgreSqlSwooleExtManagesTransactions;
use Hyperf\Database\PgSQL\DBAL\PostgresDriver;
use Hyperf\Database\PgSQL\Query\Grammars\PostgresSqlSwooleExtGrammar as QueryGrammar;
use Hyperf\Database\PgSQL\Query\Processors\PostgresProcessor;
use Hyperf\Database\PgSQL\Schema\Grammars\PostgresSqlSwooleExtGrammar as SchemaGrammar;
use Hyperf\Database\PgSQL\Schema\PostgresBuilder;
use Swoole\Coroutine\PostgreSQL;
use Swoole\Coroutine\PostgreSQLStatement;

class PostgreSqlSwooleExtConnection extends Connection
{
    use PostgreSqlSwooleExtManagesTransactions;

    protected int $fetchMode = SW_PGSQL_ASSOC;

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): PostgresBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new PostgresBuilder($this);
    }

    /**
     * Execute an SQL statement and return the boolean result.
     */
    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $statement = $this->prepare($query);

            $result = $statement->execute($this->prepareBindings($bindings));
            if ($result === false) {
                throw new QueryException($this->getName(), $query, $bindings, new Exception($statement->error, $statement->errCode));
            }

            $this->recordsHaveBeenModified();

            return true;
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $statement = $this->prepare($query);
            $this->recordsHaveBeenModified();

            $statement->execute($this->prepareBindings($bindings));

            $count = $statement->affectedRows();
            if ($count === false) {
                throw new QueryException($this->getName(), $query, $bindings, new Exception($statement->error, $statement->errCode));
            }

            $this->recordsHaveBeenModified($count > 0);

            return $count;
        });
    }

    /**
     * Run a select statement against the database.
     */
    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $statement = $this->prepare($query, $useReadPdo);

            $result = $statement->execute($this->prepareBindings($bindings));

            if ($result === false || ! empty($statement->error)) {
                throw new QueryException($this->getName(), $query, [], new Exception($statement->error));
            }

            return $statement->fetchAll($this->fetchMode) ?: [];
        });
    }

    /**
     * Run a select statement against the database and returns a generator.
     */
    public function cursor(string $query, array $bindings = [], bool $useReadPdo = true): Generator
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $statement = $this->prepare($query, $useReadPdo);

            $statement->execute($this->prepareBindings($bindings));

            return $statement;
        });

        while ($record = $statement->fetchRow(0)) {
            yield $record;
        }
    }

    public function fetch(string $query, array $bindings = [])
    {
        $records = $this->queryAll($query, $bindings);

        return array_shift($records);
    }

    public function queryAll(string $query, array $bindings = []): array
    {
        $statement = $this->prepare($query);

        $result = $statement->execute($bindings);
        if (! $result) {
            throw new QueryException($this->getName(), $query, [], new Exception($statement->error));
        }

        return $statement->fetchAll();
    }

    /**
     * @param string $needle
     * @param string $replace
     * @param string $haystack
     * @deprecated ,using `strReplaceOnce` instead
     */
    public function str_replace_once($needle, $replace, $haystack): array|string
    {
        return $this->strReplaceOnce($needle, $replace, $haystack);
    }

    public function strReplaceOnce(string $needle, string $replace, string $haystack): array|string
    {
        // Looks for the first occurence of $needle in $haystack
        // and replaces it with $replace.
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            // Nothing found
            return $haystack;
        }

        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }

    public function unprepared(string $query): bool
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->query($query) !== false
            );

            return $change;
        });
    }

    /**
     * Get the default query grammar instance.
     * @return Grammar
     */
    protected function getDefaultQueryGrammar(): QueryGrammar
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): SchemaGrammar
    {
        return $this->withTablePrefix(new SchemaGrammar());
    }

    /**
     * Get the default post processor instance.
     */
    protected function getDefaultPostProcessor(): PostgresProcessor
    {
        return new PostgresProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     */
    protected function getDoctrineDriver(): PostgresDriver
    {
        return new PostgresDriver();
    }

    protected function prepare(string $query, bool $useReadPdo = true): PostgreSQLStatement
    {
        $num = 1;
        while (strpos($query, '?')) {
            $query = $this->strReplaceOnce('?', '$' . $num++, $query);
        }

        /** @var PostgreSQL $pdo */
        $pdo = $this->getPdoForSelect($useReadPdo);
        $statement = $pdo->prepare($query);
        if (! $statement) {
            throw new QueryException($this->getName(), $query, [], new Exception($pdo->error));
        }

        return $statement;
    }
}
