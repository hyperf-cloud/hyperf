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

use Hyperf\Database\Sqlsrv\Schema\Grammars\SqlServerGrammar;
use Hyperf\Database\Sqlsrv\Schema\SqlServerBuilder;
use Hyperf\DbConnection\Connection;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class SqlServerBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testCreateDatabase()
    {
        $grammar = new SqlServerGrammar();

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
        $connection->shouldReceive('statement')->once()->with(
            'create database "my_temporary_database_a"'
        )->andReturn(true);

        $builder = new SqlServerBuilder($connection);
        $this->assertTrue($builder->createDatabase('my_temporary_database_a'));
    }

    public function testDropDatabaseIfExists()
    {
        $grammar = new SqlServerGrammar();

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
        $connection->shouldReceive('statement')->once()->with(
            'drop database if exists "my_temporary_database_b"'
        )->andReturn(true);

        $builder = new SqlServerBuilder($connection);

        $this->assertTrue($builder->dropDatabaseIfExists('my_temporary_database_b'));
    }
}
