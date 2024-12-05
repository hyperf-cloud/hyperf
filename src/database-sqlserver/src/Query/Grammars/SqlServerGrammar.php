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

namespace Hyperf\Database\Sqlsrv\Query\Grammars;

use Hyperf\Collection\Arr;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Query\Grammars\Grammar;
use Hyperf\Database\Sqlsrv\Query\IndexHint;
use Hyperf\Stringable\Str;

use function Hyperf\Collection\collect;
use function Hyperf\Collection\last;

class SqlServerGrammar extends Grammar
{
    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '!<', '!>', '<>', '!=',
        'like', 'not like', 'ilike',
        '&', '&=', '|', '|=', '^', '^=',
    ];

    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected array $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'indexHint',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'offset',
        'limit',
        'lock',
    ];

    /**
     * The grammar specific bitwise operators.
     */
    protected array $bitwiseOperators = [];

    /**
     * Compile a select query into SQL.
     */
    public function compileSelect(Builder $query): string
    {
        // An order by clause is required for SQL Server offset to function...
        if ($query->offset && empty($query->orders)) {
            $query->orders[] = ['sql' => '(SELECT 0)'];
        }

        if (($query->unions || $query->havings) && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $sql = trim(
            $this->concatenate(
                $this->compileComponents($query)
            )
        );

        if ($query->unions) {
            $sql = $this->wrapUnion($sql) . ' ' . $this->compileUnions($query);
        }

        $query->columns = $original;

        return $sql;
    }

    /**
     * Prepare the binding for a "JSON contains" statement.
     *
     * @param mixed $binding
     */
    public function prepareBindingForJsonContains($binding): string
    {
        return is_bool($binding) ? json_encode($binding) : $binding;
    }

    /**
     * Compile a "JSON value cast" statement into SQL.
     */
    public function compileJsonValueCast(string $value): string
    {
        return 'json_query(' . $value . ')';
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param int|string $seed
     */
    public function compileRandom($seed): string
    {
        return 'NEWID()';
    }

    /**
     * Compile an exists statement into SQL.
     */
    public function compileExists(Builder $query): string
    {
        $existsQuery = clone $query;

        $existsQuery->columns = [];

        return $this->compileSelect($existsQuery->selectRaw('1 [exists]')->limit(1));
    }

    /**
     * Compile an "upsert" statement into SQL.
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update): string
    {
        $columns = $this->columnize(array_keys(reset($values)));

        $sql = 'merge ' . $this->wrapTable($query->from) . ' ';

        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        $sql .= 'using (values ' . $parameters . ') ' . $this->wrapTable('laravel_source') . ' (' . $columns . ') ';

        $on = collect($uniqueBy)->map(function ($column) use ($query) {
            return $this->wrap('laravel_source.' . $column) . ' = ' . $this->wrap($query->from . '.' . $column);
        })->implode(' and ');

        $sql .= 'on ' . $on . ' ';

        if ($update) {
            $update = collect($update)->map(function ($value, $key) {
                return is_numeric($key)
                    ? $this->wrap($value) . ' = ' . $this->wrap('laravel_source.' . $value)
                    : $this->wrap($key) . ' = ' . $this->parameter($value);
            })->implode(', ');

            $sql .= 'when matched then update set ' . $update . ' ';
        }

        $sql .= 'when not matched then insert (' . $columns . ') values (' . $columns . ');';

        return $sql;
    }

    /**
     * Prepare the bindings for an update statement.
     */
    public function prepareBindingsForUpdate(array $bindings, array $values): array
    {
        $cleanBindings = Arr::except($bindings, 'select');

        return array_values(
            array_merge($values, Arr::flatten($cleanBindings))
        );
    }

    /**
     * Compile the SQL statement to define a savepoint.
     *
     * @param string $name
     */
    public function compileSavepoint($name): string
    {
        return 'SAVE TRANSACTION ' . $name;
    }

    /**
     * Compile the SQL statement to execute a savepoint rollback.
     *
     * @param string $name
     */
    public function compileSavepointRollBack($name): string
    {
        return 'ROLLBACK TRANSACTION ' . $name;
    }

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param Expression|string $table
     */
    public function wrapTable($table): string
    {
        if (! $this->isExpression($table)) {
            return $this->wrapTableValuedFunction(parent::wrapTable($table));
        }

        return $this->getValue($table);
    }

    /**
     * Compile an insert statement into SQL.
     */
    public function compileInsert(Builder $query, array $values): string
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same number of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        return "insert into {$table} ({$columns}) values {$parameters}";
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param array $values
     */
    public function compileUpdate(Builder $query, $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($query, $values);

        $where = $this->compileWheres($query);

        return trim(
            isset($query->joins)
                ? $this->compileUpdateWithJoins($query, $table, $columns, $where)
                : $this->compileUpdateWithoutJoins($query, $table, $columns, $where)
        );
    }

    /**
     * Compile a delete statement into SQL.
     */
    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->from);

        $where = $this->compileWheres($query);

        return trim(
            isset($query->joins)
                ? $this->compileDeleteWithJoins($query, $table, $where)
                : $this->compileDeleteWithoutJoins($query, $table, $where)
        );
    }

    /**
     * Get the grammar specific bitwise operators.
     *
     * @return array
     */
    public function getBitwiseOperators()
    {
        return $this->bitwiseOperators;
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param array $columns
     */
    protected function compileColumns(Builder $query, $columns): ?string
    {
        if (! is_null($query->aggregate)) {
            return null;
        }

        $select = $query->distinct ? 'select distinct ' : 'select ';

        // If there is a limit on the query, but not an offset, we will add the top
        // clause to the query, which serves as a "limit" type clause within the
        // SQL Server system similar to the limit keywords available in MySQL.
        if (is_numeric($query->limit) && $query->limit > 0 && $query->offset <= 0) {
            $select .= 'top ' . ((int) $query->limit) . ' ';
        }

        return $select . $this->columnize($columns);
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param string $table
     */
    protected function compileFrom(Builder $query, $table): string
    {
        $from = parent::compileFrom($query, $table);

        if (is_string($query->lock)) {
            return $from . ' ' . $query->lock;
        }

        if (! is_null($query->lock)) {
            return $from . ' with(rowlock,' . ($query->lock ? 'updlock,' : '') . 'holdlock)';
        }

        return $from;
    }

    /**
     * @param array $where
     */
    protected function whereBitwise(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return '(' . $this->wrap($where['column']) . ' ' . $operator . ' ' . $value . ') != 0';
    }

    /**
     * Compile a "where date" clause.
     *
     * @param array $where
     */
    protected function whereDate(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        return 'cast(' . $this->wrap($where['column']) . ' as date) ' . $where['operator'] . ' ' . $value;
    }

    /**
     * Compile a "where time" clause.
     *
     * @param array $where
     */
    protected function whereTime(Builder $query, $where): string
    {
        $value = $this->parameter($where['value']);

        return 'cast(' . $this->wrap($where['column']) . ' as time) ' . $where['operator'] . ' ' . $value;
    }

    /**
     * Compile a "JSON contains" statement into SQL.
     *
     * @param string $column
     * @param string $value
     */
    protected function compileJsonContains($column, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return $value . ' in (select [value] from openjson(' . $field . $path . '))';
    }

    /**
     * Compile a "JSON contains key" statement into SQL.
     */
    protected function compileJsonContainsKey(string $column): string
    {
        $segments = explode('->', $column);

        $lastSegment = array_pop($segments);

        if (preg_match('/\[([0-9]+)\]$/', $lastSegment, $matches)) {
            $segments[] = Str::beforeLast($lastSegment, $matches[0]);

            $key = $matches[1];
        } else {
            $key = "'" . str_replace("'", "''", $lastSegment) . "'";
        }

        [$field, $path] = $this->wrapJsonFieldAndPath(implode('->', $segments));

        return $key . ' in (select [key] from openjson(' . $field . $path . '))';
    }

    /**
     * Compile a "JSON length" statement into SQL.
     *
     * @param string $column
     * @param string $operator
     * @param string $value
     */
    protected function compileJsonLength($column, $operator, $value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($column);

        return '(select count(*) from openjson(' . $field . $path . ')) ' . $operator . ' ' . $value;
    }

    /**
     * Compile a single having clause.
     */
    protected function compileHaving(array $having): string
    {
        if ($having['type'] === 'Bitwise') {
            return $this->compileHavingBitwise($having);
        }

        return parent::compileHaving($having);
    }

    /**
     * Compile a having clause involving a bitwise operator.
     */
    protected function compileHavingBitwise(array $having): string
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return '(' . $column . ' ' . $having['operator'] . ' ' . $parameter . ') != 0';
    }

    /**
     * Compile a delete statement without joins into SQL.
     */
    protected function compileDeleteWithoutJoins(Builder $query, string $table, string $where): string
    {
        $sql = "delete from {$table} {$where}";

        return ! is_null($query->limit) && $query->limit > 0 && $query->offset <= 0
            ? Str::replaceFirst('delete', 'delete top (' . $query->limit . ')', $sql)
            : $sql;
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param int $limit
     */
    protected function compileLimit(Builder $query, $limit): string
    {
        $limit = (int) $limit;

        if ($limit && $query->offset > 0) {
            return "fetch next {$limit} rows only";
        }

        return '';
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param int $offset
     */
    protected function compileOffset(Builder $query, $offset): string
    {
        $offset = (int) $offset;

        if ($offset) {
            return "offset {$offset} rows";
        }

        return '';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param bool|string $value
     */
    protected function compileLock(Builder $query, $value): string
    {
        return '';
    }

    /**
     * Wrap a union subquery in parentheses.
     */
    protected function wrapUnion(string $sql): string
    {
        return 'select * from (' . $sql . ') as ' . $this->wrapTable('temp_table');
    }

    /**
     * Compile an update statement with joins into SQL.
     */
    protected function compileUpdateWithJoins(Builder $query, string $table, string $columns, string $where): string
    {
        $alias = last(explode(' as ', $table));

        $joins = $this->compileJoins($query, $query->joins);

        return "update {$alias} set {$columns} from {$table} {$joins} {$where}";
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param string $value
     */
    protected function wrapValue($value): string
    {
        return $value === '*' ? $value : '[' . str_replace(']', ']]', $value) . ']';
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param string $value
     */
    protected function wrapJsonSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_value(' . $field . $path . ')';
    }

    /**
     * Wrap the given JSON boolean value.
     *
     * @param string $value
     */
    protected function wrapJsonBooleanValue($value): string
    {
        return "'" . $value . "'";
    }

    /**
     * Wrap a table in keyword identifiers.
     */
    protected function wrapTableValuedFunction(string $table): string
    {
        if (preg_match('/^(.+?)(\(.*?\))]$/', $table, $matches) === 1) {
            $table = $matches[1] . ']' . $matches[2];
        }

        return $table;
    }

    /**
     * Compile a single union statement.
     */
    protected function compileUnion(array $union): string
    {
        $conjunction = $union['all'] ? ' union all ' : ' union ';

        return $conjunction . $this->wrapUnion($union['query']->toSql());
    }

    /**
     * Compile the columns for an update statement.
     */
    protected function compileUpdateColumns(Builder $query, array $values): string
    {
        return collect($values)->map(function ($value, $key) {
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        })->implode(', ');
    }

    /**
     * Compile an update statement without joins into SQL.
     */
    protected function compileUpdateWithoutJoins(Builder $query, string $table, string $columns, string $where): string
    {
        return "update {$table} set {$columns} {$where}";
    }

    /**
     * Compile a delete statement with joins into SQL.
     */
    protected function compileDeleteWithJoins(Builder $query, string $table, string $where): string
    {
        $alias = last(explode(' as ', $table));

        $joins = $this->compileJoins($query, $query->joins);

        return "delete {$alias} from {$table} {$joins} {$where}";
    }

    /**
     * Compile a "where JSON contains key" clause.
     *
     * @return string
     */
    protected function whereJsonContainsKey(Builder $query, array $where)
    {
        $not = $where['not'] ? 'not ' : '';

        return $not . $this->compileJsonContainsKey(
            $where['column']
        );
    }

    /**
     * Wrap the given JSON path.
     *
     * @param string $value
     * @param string $delimiter
     */
    protected function wrapJsonPath($value, $delimiter = '->'): string
    {
        $value = preg_replace("/([\\\\]+)?\\'/", "''", $value);

        $jsonPath = collect(explode($delimiter, $value))
            ->map(fn ($segment) => $this->wrapJsonPathSegment($segment))
            ->implode('.');

        return "'$" . (str_starts_with($jsonPath, '[') ? '' : '.') . $jsonPath . "'";
    }

    /**
     * Wrap the given JSON path segment.
     */
    protected function wrapJsonPathSegment(string $segment): string
    {
        if (preg_match('/(\[[^\]]+\])+$/', $segment, $parts)) {
            $key = Str::beforeLast($segment, $parts[0]);

            if (! empty($key)) {
                return '"' . $key . '"' . $parts[0];
            }

            return $parts[0];
        }

        return '"' . $segment . '"';
    }

    /**
     * Compile the index hints for the query.
     */
    protected function compileIndexHint(Builder $query, IndexHint $indexHint): string
    {
        return $indexHint->type === 'force'
            ? "with (index({$indexHint->index}))"
            : '';
    }
}
