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

namespace Hyperf\Database\SQLite;

use Closure;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as DoctrineDriver;
use Exception;
use Hyperf\Database\Connection;
use Hyperf\Database\Query\Grammars\Grammar as HyperfQueryGrammar;
use Hyperf\Database\Query\Processors\Processor;
use Hyperf\Database\Schema\Builder as SchemaBuilder;
use Hyperf\Database\Schema\Grammars\Grammar as HyperfSchemaGrammar;
use Hyperf\Database\SQLite\Query\Grammars\SQLiteGrammar;
use Hyperf\Database\SQLite\Query\Grammars\SQLiteGrammar as QueryGrammar;
use Hyperf\Database\SQLite\Query\Processors\SQLiteProcessor;
use Hyperf\Database\SQLite\Schema\Grammars\SQLiteGrammar as SchemaGrammar;
use Hyperf\Database\SQLite\Schema\SQLiteBuilder;
use PDO;

class SQLiteConnection extends Connection
{
    /**
     * Create a new database connection instance.
     *
     * @param Closure|PDO $pdo
     * @param string $database
     * @param string $tablePrefix
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        $enableForeignKeyConstraints = $this->getForeignKeyConstraintsConfigurationValue();

        if ($enableForeignKeyConstraints === null) {
            return;
        }

        $enableForeignKeyConstraints
            ? $this->getSchemaBuilder()->enableForeignKeyConstraints()
            : $this->getSchemaBuilder()->disableForeignKeyConstraints();
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return SQLiteBuilder
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SQLiteBuilder($this);
    }

    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     *
     * @return bool
     */
    protected function isUniqueConstraintError(Exception $exception)
    {
        return boolval(preg_match('#(column(s)? .* (is|are) not unique|UNIQUE constraint failed: .*)#i', $exception->getMessage()));
    }

    /**
     * Get the default query grammar instance.
     *
     * @return SQLiteGrammar
     */
    protected function getDefaultQueryGrammar(): HyperfQueryGrammar
    {
        /* @phpstan-ignore-next-line */
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return SchemaGrammar
     */
    protected function getDefaultSchemaGrammar(): HyperfSchemaGrammar
    {
        /* @phpstan-ignore-next-line */
        return $this->withTablePrefix(new SchemaGrammar());
    }

    /**
     * Get the default post processor instance.
     *
     * @return SQLiteProcessor
     */
    protected function getDefaultPostProcessor(): Processor
    {
        return new SQLiteProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver();
    }

    /**
     * Get the database connection foreign key constraints configuration option.
     *
     * @return null|bool
     */
    protected function getForeignKeyConstraintsConfigurationValue()
    {
        return $this->getConfig('foreign_key_constraints');
    }
}
