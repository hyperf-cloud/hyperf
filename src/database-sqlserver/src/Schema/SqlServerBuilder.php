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

namespace Hyperf\Database\Sqlsrv\Schema;

use Hyperf\Database\Exception\InvalidArgumentException;
use Hyperf\Database\Schema\Builder;

class SqlServerBuilder extends Builder
{
    /**
     * Create a database in the schema.
     */
    public function createDatabase(string $name): bool
    {
        return $this->connection->statement(
            $this->grammar->compileCreateDatabase($name, $this->connection)
        );
    }

    /**
     * Drop a database from the schema if the database exists.
     */
    public function dropDatabaseIfExists(string $name): bool
    {
        return $this->connection->statement(
            $this->grammar->compileDropDatabaseIfExists($name)
        );
    }

    /**
     * Drop all tables from the database.
     */
    public function dropAllTables(): void
    {
        $this->connection->statement($this->grammar->compileDropAllForeignKeys());

        $this->connection->statement($this->grammar->compileDropAllTables());
    }

    /**
     * Drop all views from the database.
     */
    public function dropAllViews(): void
    {
        $this->connection->statement($this->grammar->compileDropAllViews());
    }

    /**
     * Get the indexes for a given table.
     */
    public function getIndexes(string $table): array
    {
        [$schema, $table] = $this->parseSchemaAndTable($table);

        $table = $this->connection->getTablePrefix() . $table;

        return $this->connection->getPostProcessor()->processIndexes(
            $this->connection->selectFromWriteConnection($this->grammar->compileIndexes($schema, $table))
        );
    }

    /**
     * Get the foreign keys for a given table.
     */
    public function getForeignKeys(string $table): array
    {
        [$schema, $table] = $this->parseSchemaAndTable($table);

        $table = $this->connection->getTablePrefix() . $table;

        return $this->connection->getPostProcessor()->processForeignKeys(
            $this->connection->selectFromWriteConnection($this->grammar->compileForeignKeys($schema, $table))
        );
    }

    /**
     * Parse the database object reference and extract the schema and table.
     */
    protected function parseSchemaAndTable(string $reference): array
    {
        $parts = array_pad(explode('.', $reference, 2), -2, 'dbo');

        if (str_contains($parts[1], '.')) {
            $database = $parts[0];

            throw new InvalidArgumentException("Using three-part reference is not supported, you may use `Schema::connection('{$database}')` instead.");
        }

        return $parts;
    }
}
