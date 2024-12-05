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

namespace Hyperf\Database\Sqlsrv\Query\Processors;

use Exception;
use Hyperf\Database\Connection;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Processors\Processor;

class SqlServerProcessor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param string $sql
     * @param array $values
     * @param null|string $sequence
     * @throws Exception
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null): int
    {
        /** @var Connection $connection */
        $connection = $query->getConnection();

        $connection->insert($sql, $values);

        if ($connection->getConfig('odbc') === true) {
            $id = $this->processInsertGetIdForOdbc($connection);
        } else {
            $id = $connection->getPdo()->lastInsertId();
        }

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a columns query.
     */
    public function processColumns(array $results): array
    {
        return array_map(function ($result) {
            $result = (object) $result;

            $type = match ($typeName = $result->type_name) {
                'binary', 'varbinary', 'char', 'varchar', 'nchar', 'nvarchar' => $result->length == -1 ? $typeName . '(max)' : $typeName . "({$result->length})",
                'decimal', 'numeric' => $typeName . "({$result->precision},{$result->places})",
                'float', 'datetime2', 'datetimeoffset', 'time' => $typeName . "({$result->precision})",
                default => $typeName,
            };

            return [
                'name' => $result->name,
                'type_name' => $result->type_name,
                'type' => $type,
                'collation' => $result->collation,
                'nullable' => (bool) $result->nullable,
                'default' => $result->default,
                'auto_increment' => (bool) $result->autoincrement,
                'comment' => $result->comment,
                'generation' => $result->expression ? [
                    'type' => $result->persisted ? 'stored' : 'virtual',
                    'expression' => $result->expression,
                ] : null,
            ];
        }, $results);
    }

    /**
     * Process the results of an indexes query.
     */
    public function processIndexes(array $results): array
    {
        return array_map(function ($result) {
            $result = (object) $result;

            return [
                'name' => strtolower($result->name),
                'columns' => explode(',', $result->columns),
                'type' => strtolower($result->type),
                'unique' => (bool) $result->unique,
                'primary' => (bool) $result->primary,
            ];
        }, $results);
    }

    /**
     * Process the results of a foreign keys query.
     */
    public function processForeignKeys(array $results): array
    {
        return array_map(function ($result) {
            $result = (object) $result;

            return [
                'name' => $result->name,
                'columns' => explode(',', $result->columns),
                'foreign_schema' => $result->foreign_schema,
                'foreign_table' => $result->foreign_table,
                'foreign_columns' => explode(',', $result->foreign_columns),
                'on_update' => strtolower(str_replace('_', ' ', $result->on_update)),
                'on_delete' => strtolower(str_replace('_', ' ', $result->on_delete)),
            ];
        }, $results);
    }

    /**
     * Process an "insert get ID" query for ODBC.
     *
     * @throws Exception
     */
    protected function processInsertGetIdForOdbc(Connection $connection): int
    {
        $result = $connection->selectFromWriteConnection(
            'SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int) AS insertid'
        );

        if (! $result) {
            throw new Exception('Unable to retrieve lastInsertID for ODBC.');
        }

        $row = $result[0];

        return is_object($row) ? $row->insertid : $row['insertid'];
    }
}
