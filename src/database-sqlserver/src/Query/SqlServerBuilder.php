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

namespace Hyperf\Database\Sqlsrv\Query;

use Closure;
use Hyperf\Collection\Arr;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Query\Expression as ExpressionContract;
use Hyperf\Database\Sqlsrv\Exception\InvalidArgumentException;
use RectorPrefix202308\Illuminate\Contracts\Database\Query\ConditionExpression;

use function Hyperf\Collection\head;

class SqlServerBuilder extends Builder
{
    /**
     * All of the available bitwise operators.
     *
     * @var string[]
     */
    public array $bitwiseOperators = [
        '&', '|', '^', '<<', '>>', '&~',
    ];

    /**
     * The index hint for the query.
     */
    public IndexHint $indexHint;

    /**
     * Add an "order by" clause to the query.
     *
     * @param Builder|Closure|ExpressionContract|string $column
     * @param string $direction
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function orderBy($column, $direction = 'asc'): static
    {
        if ($this->isQueryable($column)) {
            [$query, $bindings] = $this->createSub($column);

            $column = new Expression('(' . $query . ')');

            $this->addBinding($bindings, $this->unions ? 'unionOrder' : 'order');
        }

        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this->{$this->unions ? 'unionOrders' : 'orders'}[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param int $value
     * @return $this
     */
    public function offset($value): static
    {
        $property = $this->unions ? 'unionOffset' : 'offset';

        $this->{$property} = max(0, (int) $value);

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param array|Closure|ExpressionContract|string $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        if ($column instanceof ConditionExpression) {
            $type = 'Expression';

            $this->wheres[] = compact('type', 'column', 'boolean');

            return $this;
        }

        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        // If the column is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parentheses.
        // We will add that Closure to the query and return back out immediately.
        if ($column instanceof Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }

        // If the column is a Closure instance and there is an operator value, we will
        // assume the developer wants to run a subquery and then compare the result
        // of that subquery with the given value that was provided to the method.
        if ($this->isQueryable($column) && ! is_null($operator)) {
            [$sub, $bindings] = $this->createSub($column);

            return $this->addBinding($bindings, 'where')
                ->where(new Expression('(' . $sub . ')'), $operator, $value, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($this->isQueryable($value)) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $type = 'Basic';

        $columnString = ($column instanceof ExpressionContract)
            ? $this->grammar->getValue($column)
            : $column;

        // If the column is making a JSON reference we'll check to see if the value
        // is a boolean. If it is, we'll add the raw boolean string as an actual
        // value to the query to ensure this is properly handled by the query.
        if (str_contains($columnString, '->') && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');

            if (is_string($column)) {
                $type = 'JsonBoolean';
            }
        }

        if ($this->isBitwiseOperator($operator)) {
            $type = 'Bitwise';
        }

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $this->wheres[] = compact(
            'type',
            'column',
            'operator',
            'value',
            'boolean'
        );

        if (! $value instanceof ExpressionContract) {
            $this->addBinding($this->flattenValue($value), 'where');
        }

        return $this;
    }

    /**
     * Add a "having" clause to the query.
     *
     * @param Closure|ExpressionContract|string $column
     * @param null|float|int|string $operator
     * @param null|float|int|string $value
     * @param string $boolean
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        $type = 'Basic';

        if ($column instanceof ConditionExpression) {
            $type = 'Expression';

            $this->havings[] = compact('type', 'column', 'boolean');

            return $this;
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        if ($column instanceof Closure && is_null($operator)) {
            return $this->havingNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        if ($this->isBitwiseOperator($operator)) {
            $type = 'Bitwise';
        }

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof ExpressionContract) {
            $this->addBinding($this->flattenValue($value), 'having');
        }

        return $this;
    }

    /**
     * Add a nested having statement to the query.
     *
     * @return $this
     */
    public function havingNested(Closure $callback, string $boolean = 'and'): static
    {
        $callback($query = $this->forNestedWhere());

        return $this->addNestedHavingQuery($query, $boolean);
    }

    /**
     * Add another query builder as a nested having to the query builder.
     *
     * @return $this
     */
    public function addNestedHavingQuery(Builder $query, string $boolean = 'and'): static
    {
        if (count($query->havings)) {
            $type = 'Nested';

            $this->havings[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['having'], 'having');
        }

        return $this;
    }

    /**
     * Add a clause that determines if a JSON path exists to the query.
     *
     * @return $this
     */
    public function whereJsonContainsKey(string $column, string $boolean = 'and', bool $not = false): static
    {
        $type = 'JsonContainsKey';

        $this->wheres[] = compact('type', 'column', 'boolean', 'not');

        return $this;
    }

    /**
     * Add an "or" clause that determines if a JSON path exists to the query.
     *
     * @return $this
     */
    public function orWhereJsonContainsKey(string $column): static
    {
        return $this->whereJsonContainsKey($column, 'or');
    }

    /**
     * Add a clause that determines if a JSON path does not exist to the query.
     *
     * @return $this
     */
    public function whereJsonDoesntContainKey(string $column, string $boolean = 'and'): static
    {
        return $this->whereJsonContainsKey($column, $boolean, true);
    }

    /**
     * Add an "or" clause that determines if a JSON path does not exist to the query.
     *
     * @return $this
     */
    public function orWhereJsonDoesntContainKey(string $column): static
    {
        return $this->whereJsonDoesntContainKey($column, 'or');
    }

    /**
     * Add an index hint to suggest a query index.
     *
     * @return $this
     */
    public function useIndex(string $index): static
    {
        $this->indexHint = new IndexHint('hint', $index);

        return $this;
    }

    /**
     * Add an index hint to force a query index.
     *
     * @return $this
     */
    public function forceIndex(string $index): static
    {
        $this->indexHint = new IndexHint('force', $index);

        return $this;
    }

    /**
     * Add an index hint to ignore a query index.
     *
     * @return $this
     */
    public function ignoreIndex(string $index): static
    {
        $this->indexHint = new IndexHint('ignore', $index);

        return $this;
    }

    /**
     * Determine if the operator is a bitwise operator.
     */
    protected function isBitwiseOperator(string $operator): bool
    {
        return in_array(strtolower($operator), $this->bitwiseOperators, true)
            || in_array(strtolower($operator), $this->grammar->getBitwiseOperators(), true);
    }

    /**
     * Get a scalar type value from an unknown type of input.
     */
    protected function flattenValue(mixed $value): mixed
    {
        return is_array($value) ? head(Arr::flatten($value)) : $value;
    }
}
