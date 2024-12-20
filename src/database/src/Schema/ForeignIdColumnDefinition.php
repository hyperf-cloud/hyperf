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

use Hyperf\Stringable\Str;

class ForeignIdColumnDefinition extends ColumnDefinition
{
    /**
     * The schema builder blueprint instance.
     */
    protected Blueprint $blueprint;

    /**
     * Create a new foreign ID column definition.
     *
     * @param array $attributes
     */
    public function __construct(Blueprint $blueprint, $attributes = [])
    {
        parent::__construct($attributes);

        $this->blueprint = $blueprint;
    }

    /**
     * Create a foreign key constraint on this column referencing the "id" column of the conventionally related table.
     *
     * @param null|string $table
     * @return ForeignKeyDefinition
     */
    public function constrained($table = null, string $column = 'id')
    {
        return $this->references($column)->on($table ?? Str::plural(Str::beforeLast($this->name, '_' . $column)));
    }

    /**
     * Specify which column this foreign ID references on another table.
     *
     * @return ForeignKeyDefinition
     */
    public function references(string $column)
    {
        return $this->blueprint->foreign($this->name)->references($column);
    }
}
