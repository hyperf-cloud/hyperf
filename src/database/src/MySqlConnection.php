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

namespace Hyperf\Database;

use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Exception;
use Hyperf\Database\DBAL\MySqlDriver;
use Hyperf\Database\Query\Grammars\MySqlGrammar as QueryGrammar;
use Hyperf\Database\Query\Processors\MySqlProcessor;
use Hyperf\Database\Schema\Grammars\MySqlGrammar as SchemaGrammar;
use Hyperf\Database\Schema\MySqlBuilder;
use PDOStatement;

class MySqlConnection extends Connection
{
    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder(): MySqlBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new MySqlBuilder($this);
    }

    /**
     * Bind values to their parameters in the given statement.
     */
    public function bindValues(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value
            );
        }
    }

    /**
     * Determine if the given database exception was caused by a unique constraint violation.
     *
     * @return bool
     */
    protected function isUniqueConstraintError(Exception $exception)
    {
        return boolval(preg_match('#Integrity constraint violation: 1062#i', $exception->getMessage()));
    }

    /**
     * Get the default query grammar instance.
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
    protected function getDefaultPostProcessor(): MySqlProcessor
    {
        return new MySqlProcessor();
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return Driver
     */
    protected function getDoctrineDriver()
    {
        return new MySqlDriver();
    }
}
