<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace Hyperf\Database\Schema;

use Hyperf\Utils\Fluent;
use Hyperf\Utils\Str;

/**
 * @method ForeignKeyDefinition references(array|string $columns) Specify the referenced column(s)
 * @method ForeignKeyDefinition on(string $table) Specify the referenced table
 * @method ForeignKeyDefinition onDelete(string $action) Add an ON DELETE action
 * @method ForeignKeyDefinition onUpdate(string $action) Add an ON UPDATE action
 * @method ForeignKeyDefinition deferrable(bool $value = true) Set the foreign key as deferrable (PostgreSQL)
 * @method ForeignKeyDefinition initiallyImmediate(bool $value = true) Set the default time to check the constraint (PostgreSQL)
 */
class ForeignKeyDefinition extends Fluent
{
    public function constrained($table = null, $column = 'id')
    {
        return $this->references($column)->on($table ?: Str::plural(Str::before($this->name, '_' . $column)));
    }
}
