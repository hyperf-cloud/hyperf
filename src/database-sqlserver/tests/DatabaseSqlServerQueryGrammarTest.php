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

use Hyperf\Database\Sqlsrv\Query\Grammars\SqlServerGrammar;
use Hyperf\DbConnection\Connection;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DatabaseSqlServerQueryGrammarTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testToRawSql()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('escape')->with('foo', false)->andReturn("'foo'");
        $grammar = new SqlServerGrammar();

        $bindings = array_map(fn ($value) => $connection->escape($value, false), ['foo']);

        $query = $grammar->substituteBindingsIntoRawSql(
            "select * from [users] where 'Hello''World?' IS NOT NULL AND [email] = ?",
            $bindings,
        );

        $this->assertSame("select * from [users] where 'Hello''World?' IS NOT NULL AND [email] = 'foo'", $query);
    }
}
