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

namespace Hyperf\Database\Schema;

use Hyperf\Database\Query\Processors\MySqlProcessor;

class MySqlBuilder extends Builder
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
     * Determine if the given table exists.
     *
     * @param string $table
     */
    public function hasTable($table): bool
    {
        $table = $this->connection->getTablePrefix() . $table;

        return count($this->connection->select(
            $this->grammar->compileTableExists(),
            [$this->connection->getDatabaseName(), $table]
        )) > 0;
    }

    /**
     * Get the column listing for a given table.
     *
     * @param string $table
     */
    public function getColumnListing($table): array
    {
        $table = $this->connection->getTablePrefix() . $table;

        $results = $this->connection->select(
            $this->grammar->compileColumnListing(),
            [$this->connection->getDatabaseName(), $table]
        );

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }

    /**
     * Get the columns.
     */
    public function getColumns(): array
    {
        $results = $this->connection->select(
            $this->grammar->compileColumns(),
            [
                $this->connection->getDatabaseName(),
            ]
        );

        return $this->connection->getPostProcessor()->processColumns($results);
    }

    /**
     * Get the column type listing for a given table.
     */
    public function getColumnTypeListing(string $table, ?string $database = null): array
    {
        $table = $this->connection->getTablePrefix() . $table;

        $results = $this->connection->select(
            $this->grammar->compileColumnListing(),
            [$database ?? $this->connection->getDatabaseName(), $table]
        );

        /** @var MySqlProcessor $processor */
        $processor = $this->connection->getPostProcessor();
        return $processor->processListing($results);
    }

    /**
     * Get the indexes for a given table.
     */
    public function getIndexes(string $table): array
    {
        $table = $this->connection->getTablePrefix() . $table;

        return $this->connection->getPostProcessor()->processIndexes(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileIndexes($this->connection->getDatabaseName(), $table)
            )
        );
    }

    /**
     * Get the tables that belong to the database.
     */
    public function getTables(): array
    {
        return $this->connection->getPostProcessor()->processTables(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileTables($this->connection->getDatabaseName())
            )
        );
    }

    /**
     * Get the views for the database.
     */
    public function getViews(): array
    {
        return $this->connection->getPostProcessor()->processViews(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileViews($this->connection->getDatabaseName())
            )
        );
    }

    /**
     * Drop all tables from the database.
     */
    public function dropAllTables(): void
    {
        $tables = [];

        foreach ($this->getAllTables() as $row) {
            $row = (array) $row;

            $tables[] = reset($row);
        }

        if (empty($tables)) {
            return;
        }

        $this->disableForeignKeyConstraints();

        $this->connection->statement(
            $this->grammar->compileDropAllTables($tables)
        );

        $this->enableForeignKeyConstraints();
    }

    /**
     * Drop all views from the database.
     */
    public function dropAllViews(): void
    {
        $views = [];

        foreach ($this->getAllViews() as $row) {
            $row = (array) $row;

            $views[] = reset($row);
        }

        if (empty($views)) {
            return;
        }

        $this->connection->statement(
            $this->grammar->compileDropAllViews($views)
        );
    }

    /**
     * Get all the table names for the database.
     *
     * @return array
     */
    public function getAllTables()
    {
        return $this->connection->select(
            $this->grammar->compileGetAllTables()
        );
    }

    /**
     * Get the foreign keys for a given table.
     */
    public function getForeignKeys(string $table): array
    {
        $table = $this->connection->getTablePrefix() . $table;

        return $this->connection->getPostProcessor()->processForeignKeys(
            $this->connection->selectFromWriteConnection(
                $this->grammar->compileForeignKeys($this->connection->getDatabaseName(), $table)
            )
        );
    }

    /**
     * Get all the view names for the database.
     */
    protected function getAllViews(): array
    {
        return $this->connection->select(
            $this->grammar->compileGetAllViews()
        );
    }
}
