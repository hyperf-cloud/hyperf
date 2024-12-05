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

namespace Hyperf\Database\Sqlsrv\Schema\Grammars;

use Hyperf\Database\Connection;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Grammars\Grammar;
use Hyperf\Support\Fluent;

use function Hyperf\Collection\collect;

class SqlServerGrammar extends Grammar
{
    /**
     * If this Grammar supports schema changes wrapped in a transaction.
     */
    protected bool $transactions = true;

    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected array $modifiers = ['Collate', 'Nullable', 'Default', 'Persisted', 'Increment'];

    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected array $serials = ['tinyInteger', 'smallInteger', 'mediumInteger', 'integer', 'bigInteger'];

    /**
     * The commands to be executed outside of create or alter command.
     *
     * @var string[]
     */
    protected array $fluentCommands = ['Default'];

    /**
     * Compile a create database command.
     *
     * @param Connection $connection
     */
    public function compileCreateDatabase(string $name, $connection): string
    {
        return sprintf(
            'create database %s',
            $this->wrapValue($name),
        );
    }

    /**
     * Compile a drop database if exists command.
     */
    public function compileDropDatabaseIfExists(string $name): string
    {
        return sprintf(
            'drop database if exists %s',
            $this->wrapValue($name)
        );
    }

    /**
     * Compile the query to determine the tables.
     */
    public function compileTables(): string
    {
        return 'select t.name as name, schema_name(t.schema_id) as [schema], sum(u.total_pages) * 8 * 1024 as size '
            . 'from sys.tables as t '
            . 'join sys.partitions as p on p.object_id = t.object_id '
            . 'join sys.allocation_units as u on u.container_id = p.hobt_id '
            . 'group by t.name, t.schema_id '
            . 'order by t.name';
    }

    /**
     * Compile the query to determine the views.
     */
    public function compileViews(): string
    {
        return 'select name, schema_name(v.schema_id) as [schema], definition from sys.views as v '
            . 'inner join sys.sql_modules as m on v.object_id = m.object_id '
            . 'order by name';
    }

    /**
     * Compile the query to determine the columns.
     */
    public function compileColumns(string $schema, string $table): string
    {
        return sprintf(
            'select col.name, type.name as type_name, '
            . 'col.max_length as length, col.precision as precision, col.scale as places, '
            . 'col.is_nullable as nullable, def.definition as [default], '
            . 'col.is_identity as autoincrement, col.collation_name as collation, '
            . 'com.definition as [expression], is_persisted as [persisted], '
            . 'cast(prop.value as nvarchar(max)) as comment '
            . 'from sys.columns as col '
            . 'join sys.types as type on col.user_type_id = type.user_type_id '
            . 'join sys.objects as obj on col.object_id = obj.object_id '
            . 'join sys.schemas as scm on obj.schema_id = scm.schema_id '
            . 'left join sys.default_constraints def on col.default_object_id = def.object_id and col.object_id = def.parent_object_id '
            . "left join sys.extended_properties as prop on obj.object_id = prop.major_id and col.column_id = prop.minor_id and prop.name = 'MS_Description' "
            . 'left join sys.computed_columns as com on col.column_id = com.column_id '
            . "where obj.type in ('U', 'V') and obj.name = %s and scm.name = %s "
            . 'order by col.column_id',
            $this->quoteString($table),
            $schema ? $this->quoteString($schema) : 'schema_name()',
        );
    }

    /**
     * Compile the query to determine the indexes.
     */
    public function compileIndexes(string $schema, string $table): string
    {
        return sprintf(
            "select idx.name as name, string_agg(col.name, ',') within group (order by idxcol.key_ordinal) as columns, "
            . 'idx.type_desc as [type], idx.is_unique as [unique], idx.is_primary_key as [primary] '
            . 'from sys.indexes as idx '
            . 'join sys.tables as tbl on idx.object_id = tbl.object_id '
            . 'join sys.schemas as scm on tbl.schema_id = scm.schema_id '
            . 'join sys.index_columns as idxcol on idx.object_id = idxcol.object_id and idx.index_id = idxcol.index_id '
            . 'join sys.columns as col on idxcol.object_id = col.object_id and idxcol.column_id = col.column_id '
            . 'where tbl.name = %s and scm.name = %s '
            . 'group by idx.name, idx.type_desc, idx.is_unique, idx.is_primary_key',
            $this->quoteString($table),
            $schema ? $this->quoteString($schema) : 'schema_name()',
        );
    }

    /**
     * Compile the query to determine the foreign keys.
     */
    public function compileForeignKeys(string $schema, string $table): string
    {
        return sprintf(
            'select fk.name as name, '
            . "string_agg(lc.name, ',') within group (order by fkc.constraint_column_id) as columns, "
            . 'fs.name as foreign_schema, ft.name as foreign_table, '
            . "string_agg(fc.name, ',') within group (order by fkc.constraint_column_id) as foreign_columns, "
            . 'fk.update_referential_action_desc as on_update, '
            . 'fk.delete_referential_action_desc as on_delete '
            . 'from sys.foreign_keys as fk '
            . 'join sys.foreign_key_columns as fkc on fkc.constraint_object_id = fk.object_id '
            . 'join sys.tables as lt on lt.object_id = fk.parent_object_id '
            . 'join sys.schemas as ls on lt.schema_id = ls.schema_id '
            . 'join sys.columns as lc on fkc.parent_object_id = lc.object_id and fkc.parent_column_id = lc.column_id '
            . 'join sys.tables as ft on ft.object_id = fk.referenced_object_id '
            . 'join sys.schemas as fs on ft.schema_id = fs.schema_id '
            . 'join sys.columns as fc on fkc.referenced_object_id = fc.object_id and fkc.referenced_column_id = fc.column_id '
            . 'where lt.name = %s and ls.name = %s '
            . 'group by fk.name, fs.name, ft.name, fk.update_referential_action_desc, fk.delete_referential_action_desc',
            $this->quoteString($table),
            $schema ? $this->quoteString($schema) : 'schema_name()',
        );
    }

    /**
     * Compile a create table command.
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        return 'create table ' . $this->wrapTable($blueprint) . " ({$columns})";
    }

    /**
     * Compile a column addition table command.
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add %s',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * Compile a rename column command.
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection): array
    {
        return (array) sprintf(
            "sp_rename '%s', %s, 'COLUMN'",
            $this->wrap($blueprint->getTable() . '.' . $command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile a change column command into a series of SQL statements.
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection): array
    {
        $changes = [$this->compileDropDefaultConstraint($blueprint, $command)];

        foreach ($blueprint->getChangedColumns() as $column) {
            $sql = sprintf(
                'alter table %s alter column %s %s',
                $this->wrapTable($blueprint),
                $this->wrap($column),
                $this->getType($column)
            );

            foreach ($this->modifiers as $modifier) {
                if (method_exists($this, $method = "modify{$modifier}")) {
                    $sql .= $this->{$method}($blueprint, $column);
                }
            }

            $changes[] = $sql;
        }

        return $changes;
    }

    /**
     * Compile a primary key command.
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'alter table %s add constraint %s primary key (%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a unique key command.
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a plain index key command.
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a spatial index key command.
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'create spatial index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a default command.
     */
    public function compileDefault(Blueprint $blueprint, Fluent $command)
    {
        if ($command->column->change && ! is_null($command->column->default)) {
            return sprintf(
                'alter table %s add default %s for %s',
                $this->wrapTable($blueprint),
                $this->getDefaultValue($command->column->default),
                $this->wrap($command->column)
            );
        }
    }

    /**
     * Compile a drop table command.
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            'if exists (select * from sys.sysobjects where id = object_id(%s, \'U\')) drop table %s',
            "'" . str_replace("'", "''", $this->getTablePrefix() . $blueprint->getTable()) . "'",
            $this->wrapTable($blueprint)
        );
    }

    /**
     * Compile the SQL needed to drop all tables.
     */
    public function compileDropAllTables(): string
    {
        return "EXEC sp_msforeachtable 'DROP TABLE ?'";
    }

    /**
     * Compile a drop column command.
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $this->wrapArray($command->columns);

        $dropExistingConstraintsSql = $this->compileDropDefaultConstraint($blueprint, $command) . ';';

        return $dropExistingConstraintsSql . 'alter table ' . $this->wrapTable($blueprint) . ' drop column ' . implode(', ', $columns);
    }

    /**
     * Compile a drop default constraint command.
     */
    public function compileDropDefaultConstraint(Blueprint $blueprint, Fluent $command): string
    {
        $columns = $command->name === 'change'
            ? "'" . collect($blueprint->getChangedColumns())->pluck('name')->implode("','") . "'"
            : "'" . implode("','", $command->columns) . "'";

        $tableName = $this->getTablePrefix() . $blueprint->getTable();

        $sql = "DECLARE @sql NVARCHAR(MAX) = '';";
        $sql .= "SELECT @sql += 'ALTER TABLE [dbo].[{$tableName}] DROP CONSTRAINT ' + OBJECT_NAME([default_object_id]) + ';' ";
        $sql .= 'FROM sys.columns ';
        $sql .= "WHERE [object_id] = OBJECT_ID('[dbo].[{$tableName}]') AND [name] in ({$columns}) AND [default_object_id] <> 0;";
        $sql .= 'EXEC(@sql)';

        return $sql;
    }

    /**
     * Compile a drop primary key command.
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop constraint {$index}";
    }

    /**
     * Compile a drop unique key command.
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "drop index {$index} on {$this->wrapTable($blueprint)}";
    }

    /**
     * Compile a drop index command.
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "drop index {$index} on {$this->wrapTable($blueprint)}";
    }

    /**
     * Compile a drop spatial index command.
     */
    public function compileDropSpatialIndex(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop foreign key command.
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command): string
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop constraint {$index}";
    }

    /**
     * Compile a rename table command.
     */
    public function compileRename(Blueprint $blueprint, Fluent $command): string
    {
        $from = $this->wrapTable($blueprint);

        return "sp_rename {$from}, " . $this->wrapTable($command->to);
    }

    /**
     * Compile a rename index command.
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command): string
    {
        return sprintf(
            "sp_rename %s, %s, N'INDEX'",
            $this->quoteString($this->wrapTable($blueprint) . '.' . $this->wrap($command->from)),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile the command to enable foreign key constraints.
     */
    public function compileEnableForeignKeyConstraints(): string
    {
        return 'EXEC sp_msforeachtable @command1="print \'?\'", @command2="ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all";';
    }

    /**
     * Compile the command to disable foreign key constraints.
     */
    public function compileDisableForeignKeyConstraints(): string
    {
        return 'EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all";';
    }

    /**
     * Compile the command to drop all foreign keys.
     */
    public function compileDropAllForeignKeys(): string
    {
        return "DECLARE @sql NVARCHAR(MAX) = N'';
            SELECT @sql += 'ALTER TABLE '
                + QUOTENAME(OBJECT_SCHEMA_NAME(parent_object_id)) + '.' + + QUOTENAME(OBJECT_NAME(parent_object_id))
                + ' DROP CONSTRAINT ' + QUOTENAME(name) + ';'
            FROM sys.foreign_keys;

            EXEC sp_executesql @sql;";
    }

    /**
     * Compile the command to drop all views.
     */
    public function compileDropAllViews(): string
    {
        return "DECLARE @sql NVARCHAR(MAX) = N'';
            SELECT @sql += 'DROP VIEW ' + QUOTENAME(OBJECT_SCHEMA_NAME(object_id)) + '.' + QUOTENAME(name) + ';'
            FROM sys.views;

            EXEC sp_executesql @sql;";
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param Blueprint|Expression|string $table
     */
    public function wrapTable($table): string
    {
        if ($table instanceof Blueprint && $table->temporary) {
            $this->setTablePrefix('#');
        }

        return parent::wrapTable($table);
    }

    /**
     * Quote the given string literal.
     *
     * @param array|string $value
     */
    public function quoteString($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, __FUNCTION__], $value));
        }

        return "N'{$value}'";
    }

    /**
     * Create the column definition for a spatial Point type.
     */
    public function typePoint(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial LineString type.
     *
     * @return string
     */
    public function typeLineString(Fluent $column)
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial Polygon type.
     *
     * @return string
     */
    public function typePolygon(Fluent $column)
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial GeometryCollection type.
     *
     * @return string
     */
    public function typeGeometryCollection(Fluent $column)
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial MultiPoint type.
     *
     * @return string
     */
    public function typeMultiPoint(Fluent $column)
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial MultiLineString type.
     *
     * @return string
     */
    public function typeMultiLineString(Fluent $column)
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial MultiPolygon type.
     *
     * @return string
     */
    public function typeMultiPolygon(Fluent $column)
    {
        return 'geography';
    }

    /**
     * Create the column definition for a char type.
     */
    protected function typeChar(Fluent $column): string
    {
        return "nchar({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     */
    protected function typeString(Fluent $column): string
    {
        return "nvarchar({$column->length})";
    }

    /**
     * Create the column definition for a tiny text type.
     */
    protected function typeTinyText(Fluent $column): string
    {
        return 'nvarchar(255)';
    }

    /**
     * Create the column definition for a text type.
     */
    protected function typeText(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a medium text type.
     */
    protected function typeMediumText(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a long text type.
     */
    protected function typeLongText(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for an integer type.
     */
    protected function typeInteger(Fluent $column): string
    {
        return 'int';
    }

    /**
     * Create the column definition for a big integer type.
     */
    protected function typeBigInteger(Fluent $column): string
    {
        return 'bigint';
    }

    /**
     * Create the column definition for a medium integer type.
     */
    protected function typeMediumInteger(Fluent $column): string
    {
        return 'int';
    }

    /**
     * Create the column definition for a tiny integer type.
     */
    protected function typeTinyInteger(Fluent $column): string
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a small integer type.
     */
    protected function typeSmallInteger(Fluent $column): string
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a float type.
     */
    protected function typeFloat(Fluent $column): string
    {
        if ($column->precision) {
            return "float({$column->precision})";
        }

        return 'float';
    }

    /**
     * Create the column definition for a double type.
     */
    protected function typeDouble(Fluent $column): string
    {
        return 'float';
    }

    /**
     * Create the column definition for a decimal type.
     */
    protected function typeDecimal(Fluent $column): string
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     */
    protected function typeBoolean(Fluent $column): string
    {
        return 'bit';
    }

    /**
     * Create the column definition for an enumeration type.
     */
    protected function typeEnum(Fluent $column): string
    {
        return sprintf(
            'nvarchar(255) check ("%s" in (%s))',
            $column->name,
            $this->quoteString($column->allowed)
        );
    }

    /**
     * Create the column definition for a json type.
     */
    protected function typeJson(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a jsonb type.
     */
    protected function typeJsonb(Fluent $column): string
    {
        return 'nvarchar(max)';
    }

    /**
     * Create the column definition for a date type.
     */
    protected function typeDate(Fluent $column): string
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     */
    protected function typeDateTime(Fluent $column): string
    {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     */
    protected function typeDateTimeTz(Fluent $column): string
    {
        return $this->typeTimestampTz($column);
    }

    /**
     * Create the column definition for a time type.
     */
    protected function typeTime(Fluent $column): string
    {
        return $column->precision ? "time({$column->precision})" : 'time';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     */
    protected function typeTimeTz(Fluent $column): string
    {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     */
    protected function typeTimestamp(Fluent $column): string
    {
        if ($column->useCurrent) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }

        return $column->precision ? "datetime2({$column->precision})" : 'datetime';
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @see https://docs.microsoft.com/en-us/sql/t-sql/data-types/datetimeoffset-transact-sql?view=sql-server-ver15
     */
    protected function typeTimestampTz(Fluent $column): string
    {
        if ($column->useCurrent) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }

        return $column->precision ? "datetimeoffset({$column->precision})" : 'datetimeoffset';
    }

    /**
     * Create the column definition for a year type.
     */
    protected function typeYear(Fluent $column): string
    {
        return $this->typeInteger($column);
    }

    /**
     * Create the column definition for a binary type.
     */
    protected function typeBinary(Fluent $column): string
    {
        if ($column->length) {
            return $column->fixed ? "binary({$column->length})" : "varbinary({$column->length})";
        }

        return 'varbinary(max)';
    }

    /**
     * Create the column definition for a uuid type.
     */
    protected function typeUuid(Fluent $column): string
    {
        return 'uniqueidentifier';
    }

    /**
     * Create the column definition for an IP address type.
     */
    protected function typeIpAddress(Fluent $column): string
    {
        return 'nvarchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     */
    protected function typeMacAddress(Fluent $column): string
    {
        return 'nvarchar(17)';
    }

    /**
     * Create the column definition for a spatial Geometry type.
     */
    protected function typeGeometry(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a spatial Geography type.
     */
    protected function typeGeography(Fluent $column): string
    {
        return 'geography';
    }

    /**
     * Create the column definition for a generated, computed column type.
     */
    protected function typeComputed(Fluent $column)
    {
        $expression = $column->expression;
        if (! $expression instanceof Expression) {
            $expression = new Expression($expression);
        }

        return "as ({$this->getValue($expression)})";
    }

    /**
     * Get the SQL for a collation column modifier.
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->collation)) {
            return ' collate ' . $column->collation;
        }
    }

    /**
     * Get the SQL for a nullable column modifier.
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        if ($column->type !== 'computed') {
            return $column->nullable ? ' null' : ' not null';
        }
    }

    /**
     * Get the SQL for a default column modifier.
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (! $column->change && ! is_null($column->default)) {
            return ' default ' . $this->getDefaultValue($column->default);
        }
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @return null|string
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (! $column->change && in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' identity primary key';
        }
    }

    /**
     * Get the SQL for a generated stored column modifier.
     */
    protected function modifyPersisted(Blueprint $blueprint, Fluent $column)
    {
        if ($column->change) {
            if ($column->type === 'computed') {
                return $column->persisted ? ' add persisted' : ' drop persisted';
            }

            return null;
        }

        if ($column->persisted) {
            return ' persisted';
        }
    }
}
