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

use BadMethodCallException;
use Closure;
use Hyperf\Database\Connection;
use Hyperf\Database\Schema\Grammars\Grammar;
use Hyperf\Database\SQLiteConnection;
use Hyperf\Macroable\Macroable;
use Hyperf\Utils\Fluent;
use Hyperf\Database\Query\Expression;

class Blueprint
{
    use Macroable;

    /**
     * The storage engine that should be used for the table.
     *
     * @var string
     */
    public $engine;

    /**
     * The default character set that should be used for the table.
     */
    public $charset;

    /**
     * The collation that should be used for the table.
     */
    public $collation;

    /**
     * Whether to make the table temporary.
     *
     * @var bool
     */
    public $temporary = false;

    /**
     * The comment of the table.
     *
     * @var string
     */
    protected $comment = '';

    /**
     * The table the blueprint describes.
     *
     * @var string
     */
    protected $table;

    /**
     * The prefix of the table.
     *
     * @var string
     */
    protected $prefix;

    /**
     * The columns that should be added to the table.
     *
     * @var \Hyperf\Database\Schema\ColumnDefinition[]
     */
    protected $columns = [];

    /**
     * The commands that should be run for the table.
     *
     * @var \Hyperf\Utils\Fluent[]
     */
    protected $commands = [];

    /**
     * The column to add new columns after.
     *
     * @var string
     */
    public $after;

    /**
     * Create a new schema blueprint.
     *
     * @param string $table
     * @param string $prefix
     */
    public function __construct($table, Closure $callback = null, $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;

        if (! is_null($callback)) {
            $callback($this);
        }
    }

    /**
     * Execute the blueprint against the database.
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Get the raw SQL statements for the blueprint.
     *
     * @return array
     */
    public function toSql(Connection $connection, Grammar $grammar)
    {
        $this->addImpliedCommands($grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensureCommandsAreValid($connection);

        foreach ($this->commands as $command) {
            $method = 'compile' . ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (! is_null($sql = $grammar->{$method}($this, $command, $connection))) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }

        return $statements;
    }

    /**
     * Add the fluent commands specified on any columns.
     */
    public function addFluentCommands(Grammar $grammar)
    {
        foreach ($this->columns as $column) {
            foreach ($grammar->getFluentCommands() as $commandName) {
                $attributeName = lcfirst($commandName);

                if (! isset($column->{$attributeName})) {
                    continue;
                }

                $value = $column->{$attributeName};

                $this->addCommand(
                    $commandName,
                    compact('value', 'column')
                );
            }
        }
    }

    /**
     * Indicate that the table needs to be created.
     *
     * @return \Hyperf\Utils\Fluent
     */
    public function create()
    {
        return $this->addCommand('create');
    }

    /**
     * Set the table comment.
     */
    public function comment(string $comment)
    {
        $this->comment = $comment;
    }

    /**
     * Indicate that the table needs to be temporary.
     */
    public function temporary()
    {
        $this->temporary = true;
    }

    /**
     * Indicate that the table should be dropped.
     *
     * @return \Hyperf\Utils\Fluent
     */
    public function drop()
    {
        return $this->addCommand('drop');
    }

    /**
     * Indicate that the table should be dropped if it exists.
     *
     * @return \Hyperf\Utils\Fluent
     */
    public function dropIfExists()
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * Indicate that the given columns should be dropped.
     *
     * @param array|mixed $columns
     * @return \Hyperf\Utils\Fluent
     */
    public function dropColumn($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * Indicate that the given columns should be renamed.
     *
     * @param string $from
     * @param string $to
     * @return \Hyperf\Utils\Fluent
     */
    public function renameColumn($from, $to)
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * Indicate that the given primary key should be dropped.
     *
     * @param array|string $index
     * @return \Hyperf\Utils\Fluent
     */
    public function dropPrimary($index = null)
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * Indicate that the given unique key should be dropped.
     *
     * @param array|string $index
     * @return \Hyperf\Utils\Fluent
     */
    public function dropUnique($index)
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * Indicate that the given index should be dropped.
     *
     * @param array|string $index
     * @return \Hyperf\Utils\Fluent
     */
    public function dropIndex($index)
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * Indicate that the given spatial index should be dropped.
     *
     * @param array|string $index
     * @return \Hyperf\Utils\Fluent
     */
    public function dropSpatialIndex($index)
    {
        return $this->dropIndexCommand('dropSpatialIndex', 'spatialIndex', $index);
    }

    /**
     * Indicate that the given foreign key should be dropped.
     *
     * @param array|string $index
     * @return \Hyperf\Utils\Fluent
     */
    public function dropForeign($index)
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    /**
     * Indicate that the given indexes should be renamed.
     *
     * @param string $from
     * @param string $to
     * @return \Hyperf\Utils\Fluent
     */
    public function renameIndex($from, $to)
    {
        return $this->addCommand('renameIndex', compact('from', 'to'));
    }

    /**
     * Indicate that the timestamp columns should be dropped.
     */
    public function dropTimestamps()
    {
        $this->dropColumn('created_at', 'updated_at');
    }

    /**
     * Indicate that the timestamp columns should be dropped.
     */
    public function dropTimestampsTz()
    {
        $this->dropTimestamps();
    }

    /**
     * Indicate that the soft delete column should be dropped.
     *
     * @param string $column
     */
    public function dropSoftDeletes($column = 'deleted_at')
    {
        $this->dropColumn($column);
    }

    /**
     * Indicate that the soft delete column should be dropped.
     *
     * @param string $column
     */
    public function dropSoftDeletesTz($column = 'deleted_at')
    {
        $this->dropSoftDeletes($column);
    }

    /**
     * Indicate that the remember token column should be dropped.
     */
    public function dropRememberToken()
    {
        $this->dropColumn('remember_token');
    }

    /**
     * Indicate that the polymorphic columns should be dropped.
     *
     * @param string $name
     * @param null|string $indexName
     */
    public function dropMorphs($name, $indexName = null)
    {
        $this->dropIndex($indexName ?: $this->createIndexName('index', ["{$name}_type", "{$name}_id"]));

        $this->dropColumn("{$name}_type", "{$name}_id");
    }

    /**
     * Rename the table to a given name.
     *
     * @param string $to
     * @return \Hyperf\Utils\Fluent
     */
    public function rename($to)
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Specify the primary key(s) for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @param null|string $algorithm
     * @return \Hyperf\Utils\Fluent
     */
    public function primary($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * Specify a unique index for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @param null|string $algorithm
     * @return \Hyperf\Utils\Fluent
     */
    public function unique($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    /**
     * Specify an index for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @param null|string $algorithm
     * @return \Hyperf\Utils\Fluent
     */
    public function index($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Specify a spatial index for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @return \Hyperf\Utils\Fluent
     */
    public function spatialIndex($columns, $name = null)
    {
        return $this->indexCommand('spatialIndex', $columns, $name);
    }

    /**
     * Specify a foreign key for the table.
     *
     * @param array|string $columns
     * @param string $name
     * @return \Hyperf\Database\Schema\ForeignKeyDefinition|\Hyperf\Utils\Fluent
     */
    public function foreign($columns, $name = null)
    {
        return $this->indexCommand('foreign', $columns, $name);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function increments($column)
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function integerIncrements($column)
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function tinyIncrements($column)
    {
        return $this->unsignedTinyInteger($column, true);
    }

    /**
     * Create a new auto-incrementing small integer (2-byte) column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function smallIncrements($column)
    {
        return $this->unsignedSmallInteger($column, true);
    }

    /**
     * Create a new auto-incrementing medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function mediumIncrements($column)
    {
        return $this->unsignedMediumInteger($column, true);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function bigIncrements($column)
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Create a new char column on the table.
     *
     * @param string $column
     * @param int $length
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function char($column, $length = null)
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Create a new string column on the table.
     *
     * @param string $column
     * @param int $length
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function string($column, $length = null)
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a new text column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a new medium text column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function mediumText($column)
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a new long text column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function longText($column)
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a new integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function integer($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function smallInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function bigInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function unsignedInteger($column, $autoIncrement = false)
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function unsignedTinyInteger($column, $autoIncrement = false)
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer (2-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function unsignedSmallInteger($column, $autoIncrement = false)
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer (3-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function unsignedMediumInteger($column, $autoIncrement = false)
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param string $column
     * @param bool $autoIncrement
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function unsignedBigInteger($column, $autoIncrement = false)
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new float column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function float($column, $total = 8, $places = 2)
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /**
     * Create a new double column on the table.
     *
     * @param string $column
     * @param null|int $total
     * @param null|int $places
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function double($column, $total = null, $places = null)
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /**
     * Create a new decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function decimal($column, $total = 8, $places = 2)
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    /**
     * Create a new unsigned decimal column on the table.
     *
     * @param string $column
     * @param int $total
     * @param int $places
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function unsignedDecimal($column, $total = 8, $places = 2)
    {
        return $this->addColumn('decimal', $column, [
            'total' => $total, 'places' => $places, 'unsigned' => true,
        ]);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function boolean($column)
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new enum column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function enum($column, array $allowed)
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a new json column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function json($column)
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new jsonb column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function jsonb($column)
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Create a new date column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function date($column)
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a new date-time column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function dateTime($column, $precision = 0)
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * Create a new date-time column (with time zone) on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function dateTimeTz($column, $precision = 0)
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    /**
     * Create a new time column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function time($column, $precision = 0)
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * Create a new time column (with time zone) on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function timeTz($column, $precision = 0)
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }

    /**
     * Create a new timestamp column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function timestamp($column, $precision = 0)
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a new timestamp (with time zone) column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function timestampTz($column, $precision = 0)
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * @param int $precision
     */
    public function timestamps($precision = 0)
    {
        $this->timestamp('created_at', $precision)->nullable();

        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * Alias for self::timestamps().
     *
     * @param int $precision
     */
    public function nullableTimestamps($precision = 0)
    {
        $this->timestamps($precision);
    }

    /**
     * Add creation and update timestampTz columns to the table.
     *
     * @param int $precision
     */
    public function timestampsTz($precision = 0)
    {
        $this->timestampTz('created_at', $precision)->nullable();

        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * Add a "deleted at" timestamp for the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function softDeletes($column = 'deleted_at', $precision = 0)
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Add a "deleted at" timestampTz for the table.
     *
     * @param string $column
     * @param int $precision
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function softDeletesTz($column = 'deleted_at', $precision = 0)
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    /**
     * Create a new year column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function year($column)
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a new binary column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function binary($column)
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function id($column = 'id')
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create a new uuid column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function uuid($column)
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create a new IP address column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function ipAddress($column)
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a new MAC address column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function macAddress($column)
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Create a new geometry column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function geometry($column)
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a new point column on the table.
     *
     * @param string $column
     * @param null|int $srid
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function point($column, $srid = null)
    {
        return $this->addColumn('point', $column, compact('srid'));
    }

    /**
     * Create a new linestring column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function lineString($column)
    {
        return $this->addColumn('linestring', $column);
    }

    /**
     * Create a new polygon column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function polygon($column)
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Create a new geometrycollection column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function geometryCollection($column)
    {
        return $this->addColumn('geometrycollection', $column);
    }

    /**
     * Create a new multipoint column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function multiPoint($column)
    {
        return $this->addColumn('multipoint', $column);
    }

    /**
     * Create a new multilinestring column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function multiLineString($column)
    {
        return $this->addColumn('multilinestring', $column);
    }

    /**
     * Create a new multipolygon column on the table.
     *
     * @param string $column
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function multiPolygon($column)
    {
        return $this->addColumn('multipolygon', $column);
    }

    /**
     * Add the proper columns for a polymorphic table.
     *
     * @param string $name
     * @param null|string $indexName
     */
    public function morphs($name, $indexName = null)
    {
        $this->string("{$name}_type");

        $this->unsignedBigInteger("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table.
     *
     * @param string $name
     * @param null|string $indexName
     */
    public function nullableMorphs($name, $indexName = null)
    {
        $this->string("{$name}_type")->nullable();

        $this->unsignedBigInteger("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Adds the `remember_token` column to the table.
     *
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function rememberToken()
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param string $type
     * @param string $name
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $this->columns[] = $column = new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * Remove a column from the schema blueprint.
     *
     * @param string $name
     * @return $this
     */
    public function removeColumn($name)
    {
        $this->columns = array_values(array_filter($this->columns, function ($c) use ($name) {
            return $c['attributes']['name'] != $name;
        }));

        return $this;
    }

    /**
     * Get the table the blueprint describes.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the comment on the blueprint.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Get the columns on the blueprint.
     *
     * @return \Hyperf\Database\Schema\ColumnDefinition[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get the commands on the blueprint.
     *
     * @return \Hyperf\Utils\Fluent[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Get the columns on the blueprint that should be added.
     *
     * @return \Hyperf\Database\Schema\ColumnDefinition[]
     */
    public function getAddedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * Get the columns on the blueprint that should be changed.
     *
     * @return \Hyperf\Database\Schema\ColumnDefinition[]
     */
    public function getChangedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return (bool) $column->change;
        });
    }

    /**
     * Ensure the commands on the blueprint are valid for the connection type.
     *
     * @throws \BadMethodCallException
     */
    protected function ensureCommandsAreValid(Connection $connection)
    {
        if ($connection instanceof SQLiteConnection) {
            if ($this->commandsNamed(['dropColumn', 'renameColumn'])->count() > 1) {
                throw new BadMethodCallException(
                    "SQLite doesn't support multiple calls to dropColumn / renameColumn in a single modification."
                );
            }

            if ($this->commandsNamed(['dropForeign'])->count() > 0) {
                throw new BadMethodCallException(
                    "SQLite doesn't support dropping foreign keys (you would need to re-create the table)."
                );
            }
        }
    }

    /**
     * Get all of the commands matching the given names.
     *
     * @return \Hyperf\Utils\Collection
     */
    protected function commandsNamed(array $names)
    {
        return collect($this->commands)->filter(function ($command) use ($names) {
            return in_array($command->name, $names);
        });
    }

    /**
     * Add the commands that are implied by the blueprint's state.
     */
    protected function addImpliedCommands(Grammar $grammar)
    {
        if (count($this->getAddedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        if (count($this->getChangedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('change'));
        }

        $this->addFluentIndexes();

        $this->addFluentCommands($grammar);
    }

    /**
     * Add the index commands fluently specified on columns.
     */
    protected function addFluentIndexes()
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index', 'spatialIndex'] as $index) {
                // If the index has been specified on the given column, but is simply equal
                // to "true" (boolean), no name has been specified for this index so the
                // index method can be called without a name and it will generate one.
                if ($column->{$index} === true) {
                    $this->{$index}($column->name);

                    continue 2;
                }

                // If the index has been specified on the given column, and it has a string
                // value, we'll go ahead and call the index method and pass the name for
                // the index since the developer specified the explicit name for this.
                if (isset($column->{$index})) {
                    $this->{$index}($column->name, $column->{$index});

                    continue 2;
                }
            }
        }
    }

    /**
     * Determine if the blueprint has a create command.
     *
     * @return bool
     */
    protected function creating()
    {
        return collect($this->commands)->contains(function ($command) {
            return $command->name === 'create';
        });
    }

    /**
     * Add a new index command to the blueprint.
     *
     * @param string $type
     * @param array|string $columns
     * @param string $index
     * @param null|string $algorithm
     * @return \Hyperf\Utils\Fluent
     */
    protected function indexCommand($type, $columns, $index, $algorithm = null)
    {
        $columns = (array) $columns;

        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type,
            compact('index', 'columns', 'algorithm')
        );
    }

    /**
     * Create a new drop index command on the blueprint.
     *
     * @param string $command
     * @param string $type
     * @param array|string $index
     * @return \Hyperf\Utils\Fluent
     */
    protected function dropIndexCommand($command, $type, $index)
    {
        $columns = [];

        // If the given "index" is actually an array of columns, the developer means
        // to drop an index merely by specifying the columns involved without the
        // conventional name, so we will build the index name from the columns.
        if (is_array($index)) {
            $index = $this->createIndexName($type, $columns = $index);
        }

        return $this->indexCommand($command, $columns, $index);
    }

    /**
     * Create a default index name for the table.
     *
     * @param string $type
     * @return string
     */
    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->prefix . $this->table . '_' . implode('_', $columns) . '_' . $type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Add a new command to the blueprint.
     *
     * @param string $name
     * @return \Hyperf\Utils\Fluent
     */
    protected function addCommand($name, array $parameters = [])
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Create a new Fluent command.
     *
     * @param string $name
     * @return \Hyperf\Utils\Fluent
     */
    protected function createCommand($name, array $parameters = [])
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }

    /**
     * Determine if the blueprint has auto-increment columns.
     *
     * @return bool
     */
    public function hasAutoIncrementColumn()
    {
        return ! is_null(collect($this->getAddedColumns())->first(function ($column) {
            return $column->autoIncrement === true;
        }));
    }

    /**
     * Get the auto-increment column starting values.
     *
     * @return array
     */
    public function autoIncrementingStartingValues()
    {
        if (! $this->hasAutoIncrementColumn()) {
            return [];
        }

        return collect($this->getAddedColumns())->mapWithKeys(function ($column) {
            return $column->autoIncrement === true
                ? [$column->name => $column->get('startingValue', $column->get('from'))]
                : [$column->name => null];
        })->filter()->all();
    }

    /**
     * Specify an fulltext for the table.
     *
     * @param  string|array  $columns
     * @param  string|null  $name
     * @param  string|null  $algorithm
     * @return \Hyperf\Utils\Fluent
     */
    public function fulltext($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('fulltext', $columns, $name, $algorithm);
    }

    /**
     * Specify a raw index for the table.
     *
     * @param  string  $expression
     * @param  string  $name
     * @return \Hyperf\Utils\Fluent
     */
    public function rawIndex($expression, $name)
    {
        return $this->index([new Expression($expression)], $name);
    }

    /**
     * Add a new column definition to the blueprint.
     *
     * @param  \Hyperf\Database\Schema\ColumnDefinition  $definition
     * @return \Hyperf\Database\Schema\ColumnDefinition
     */
    protected function addColumnDefinition($definition)
    {
        $this->columns[] = $definition;

        if ($this->after) {
            $definition->after($this->after);

            $this->after = $definition->name;
        }

        return $definition;
    }

    /**
     * Create a new UUID column on the table with a foreign key constraint.
     *
     * @param  string  $column
     * @return \Hyperf\Database\Schema\ForeignIdColumnDefinition
     */
    public function foreignUuid($column)
    {
        return $this->addColumnDefinition(new ForeignIdColumnDefinition($this, [
            'type' => 'uuid',
            'name' => $column,
        ]));
    }
}
