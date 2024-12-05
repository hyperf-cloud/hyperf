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

namespace HyperfTest\Database\Sqlsrv;

use Hyperf\Database\Connection;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Sqlsrv\Schema\Grammars\SqlServerGrammar;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DatabaseSchemaBlueprintTest extends TestCase
{
    public function testDefaultCurrentDateTime()
    {
        $base = new Blueprint('users', function ($table) {
            $table->dateTime('created')->useCurrent();
        });

        $connection = m::mock(Connection::class);

        $blueprint = clone $base;
        $this->assertEquals(['alter table "users" add "created" datetime not null default CURRENT_TIMESTAMP'], $blueprint->toSql($connection, new SqlServerGrammar()));
    }

    public function testDefaultCurrentTimestamp()
    {
        $base = new Blueprint('users', function ($table) {
            $table->timestamp('created')->useCurrent();
        });

        $connection = m::mock(Connection::class);

        $blueprint = clone $base;
        $this->assertEquals(['alter table "users" add "created" datetime not null default CURRENT_TIMESTAMP'], $blueprint->toSql($connection, new SqlServerGrammar()));
    }

    public function testRenameColumnWithoutDoctrine()
    {
        $base = new Blueprint('users', function ($table) {
            $table->renameColumn('foo', 'bar');
        });

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('usingNativeSchemaOperations')->andReturn(true);

        $blueprint = clone $base;
        $this->assertEquals(['sp_rename \'"users"."foo"\', "bar", \'COLUMN\''], $blueprint->toSql($connection, new SqlServerGrammar()));
    }

    public function testDropColumnWithoutDoctrine()
    {
        $base = new Blueprint('users', function ($table) {
            $table->dropColumn('foo');
        });

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('usingNativeSchemaOperations')->andReturn(true);

        $blueprint = clone $base;
        $this->assertStringContainsString('alter table "users" drop column "foo"', $blueprint->toSql($connection, new SqlServerGrammar())[0]);
    }

    public function testTinyTextColumn()
    {
        $base = new Blueprint('posts', function ($table) {
            $table->addColumn('tinyText', 'note');
        });

        $connection = m::mock(Connection::class);

        $blueprint = clone $base;
        $this->assertEquals([
            'alter table "posts" add "note" nvarchar(255) not null',
        ], $blueprint->toSql($connection, new SqlServerGrammar()));
    }

    public function testTinyTextNullableColumn()
    {
        $base = new Blueprint('posts', function ($table) {
            $table->addColumn('tinyText', 'note')->nullable();
        });

        $connection = m::mock(Connection::class);

        $blueprint = clone $base;
        $this->assertEquals([
            'alter table "posts" add "note" nvarchar(255) null',
        ], $blueprint->toSql($connection, new SqlServerGrammar()));
    }
}
