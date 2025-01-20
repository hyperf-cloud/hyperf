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

namespace HyperfTest\Database;

use BadMethodCallException;
use Hyperf\Collection\Collection;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\Model\Builder as ModelBuilder;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Expression as Raw;
use Hyperf\Database\Query\Grammars\Grammar;
use Hyperf\Database\Query\Grammars\MySqlGrammar;
use Hyperf\Database\Query\Processors\MySqlProcessor;
use Hyperf\Database\Query\Processors\Processor;
use Hyperf\Di\Container;
use Hyperf\Paginator\LengthAwarePaginator;
use Hyperf\Paginator\Paginator;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

use function Hyperf\Collection\collect;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class QueryBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function getBuilder(): Builder
    {
        return new Builder(Mockery::mock(ConnectionInterface::class), new Grammar(), Mockery::mock(Processor::class));
    }

    public function getMysqlBuilder(): Builder
    {
        return new Builder(Mockery::mock(ConnectionInterface::class), new MySqlGrammar(), Mockery::mock(Processor::class));
    }

    public function testQueryBuilder()
    {
        $this->assertInstanceOf(Builder::class, $this->getBuilder());
    }

    public function testBasicSelect()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "users"', $builder->toSql());
    }

    public function testBasicSelectWithGetColumns()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()->shouldReceive('processSelect');
        $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
            $this->assertEquals('select * from "users"', $sql);
            return [];
        });
        $builder->getConnection()->shouldReceive('select')->once()->andReturnUsing(function ($sql) {
            $this->assertEquals('select "foo", "bar" from "users"', $sql);
            return [];
        });

        $builder->from('users')->get();
        $this->assertNull($builder->columns);

        $builder->from('users')->get(['foo', 'bar']);
        $this->assertNull($builder->columns);

        $this->assertEquals('select * from "users"', $builder->toSql());
        $this->assertNull($builder->columns);
    }

    public function testBasicSelectUseWritePdo()
    {
        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->shouldReceive('select')->once()->with('select * from `users`', [], false);
        $builder->useWritePdo()->select('*')->from('users')->get();
        $this->assertTrue($builder->useWritePdo);
        $this->assertSame('select * from `users`', $builder->toSql());

        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->shouldReceive('select')->once()->with('select * from `users`', [], true);
        $builder->select('*')->from('users')->get();
        $this->assertFalse($builder->useWritePdo);
        $this->assertSame('select * from `users`', $builder->toSql());
    }

    public function testBasicTableWrappingProtectsQuotationMarks()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('some"table');
        $this->assertEquals('select * from "some""table"', $builder->toSql());
    }

    public function testAliasWrappingAsWholeConstant()
    {
        $builder = $this->getBuilder();
        $builder->select('x.y as foo.bar')->from('baz');
        $this->assertEquals('select "x"."y" as "foo.bar" from "baz"', $builder->toSql());
    }

    public function testAliasWrappingWithSpacesInDatabaseName()
    {
        $builder = $this->getBuilder();
        $builder->select('w x.y.z as foo.bar')->from('baz');
        $this->assertEquals('select "w x"."y"."z" as "foo.bar" from "baz"', $builder->toSql());
    }

    public function testAddingSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('foo')->addSelect('bar')->addSelect(['baz', 'boom'])->from('users');
        $this->assertEquals('select "foo", "bar", "baz", "boom" from "users"', $builder->toSql());
    }

    public function testBasicSelectWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('users');
        $this->assertEquals('select * from "prefix_users"', $builder->toSql());
    }

    public function testBasicSelectDistinct()
    {
        $builder = $this->getBuilder();
        $builder->distinct()->select('foo', 'bar')->from('users');
        $this->assertEquals('select distinct "foo", "bar" from "users"', $builder->toSql());
    }

    public function testBasicAlias()
    {
        $builder = $this->getBuilder();
        $builder->select('foo as bar')->from('users');
        $this->assertEquals('select "foo" as "bar" from "users"', $builder->toSql());
    }

    public function testAliasWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('users as people');
        $this->assertEquals('select * from "prefix_users" as "prefix_people"', $builder->toSql());
    }

    public function testJoinAliasesWithPrefix()
    {
        $builder = $this->getBuilder();
        $builder->getGrammar()->setTablePrefix('prefix_');
        $builder->select('*')->from('services')->join('translations AS t', 't.item_id', '=', 'services.id');
        $this->assertEquals('select * from "prefix_services" inner join "prefix_translations" as "prefix_t" on "prefix_t"."item_id" = "prefix_services"."id"', $builder->toSql());
    }

    public function testBasicTableWrapping()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('public.users');
        $this->assertEquals('select * from "public"."users"', $builder->toSql());
    }

    public function testWhenCallback()
    {
        $callback = function ($query, $condition) {
            $this->assertTrue($condition);

            $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testWhenCallbackWithReturn()
    {
        $callback = function ($query, $condition) {
            $this->assertTrue($condition);

            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(true, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(false, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testWhenCallbackWithDefault()
    {
        $callback = function ($query, $condition) {
            $this->assertEquals($condition, 'truthy');

            $query->where('id', '=', 1);
        };

        $default = function ($query, $condition) {
            $this->assertEquals($condition, 0);

            $query->where('id', '=', 2);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when('truthy', $callback, $default)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->when(0, $callback, $default)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
    }

    public function testUnlessCallback()
    {
        $callback = function ($query, $condition) {
            $this->assertFalse($condition);

            $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testUnlessCallbackWithReturn()
    {
        $callback = function ($query, $condition) {
            $this->assertFalse($condition);

            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(false, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(true, $callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "email" = ?', $builder->toSql());
    }

    public function testUnlessCallbackWithDefault()
    {
        $callback = function ($query, $condition) {
            $this->assertEquals($condition, 0);

            $query->where('id', '=', 1);
        };

        $default = function ($query, $condition) {
            $this->assertEquals($condition, 'truthy');

            $query->where('id', '=', 2);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless(0, $callback, $default)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->unless('truthy', $callback, $default)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 2, 1 => 'foo'], $builder->getBindings());
    }

    public function testTapCallback()
    {
        $callback = function ($query) {
            return $query->where('id', '=', 1);
        };

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->tap($callback)->where('email', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? and "email" = ?', $builder->toSql());
    }

    public function testBasicWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $this->assertEquals('select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testMySqlWrappingProtectsQuotationMarks()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->From('some`table');
        $this->assertEquals('select * from `some``table`', $builder->toSql());
    }

    public function testDateBasedWheresAcceptsTwoArguments()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', 1);
        $this->assertEquals('select * from `users` where date(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', 1);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', 1);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
    }

    public function testDateBasedOrWheresAcceptsTwoArguments()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereDate('created_at', 1);
        $this->assertEquals('select * from `users` where `id` = ? or date(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereDay('created_at', 1);
        $this->assertEquals('select * from `users` where `id` = ? or day(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereMonth('created_at', 1);
        $this->assertEquals('select * from `users` where `id` = ? or month(`created_at`) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhereyear('created_at', 1);
        $this->assertEquals('select * from `users` where `id` = ? or year(`created_at`) = ?', $builder->toSql());
    }

    public function testDateBasedWheresExpressionIsNotBound()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', new Raw('NOW()'))->where('admin', true);
        $this->assertEquals([true], $builder->getBindings());
    }

    public function testWhereDateMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', '2015-12-21');
        $this->assertEquals('select * from `users` where date(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => '2015-12-21'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDate('created_at', '=', new Raw('NOW()'));
        $this->assertEquals('select * from `users` where date(`created_at`) = NOW()', $builder->toSql());
    }

    public function testWhereDayMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1);
        $this->assertEquals('select * from `users` where day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testOrWhereDayMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereDay('created_at', '=', 1)->orWhereDay('created_at', '=', 2);
        $this->assertEquals('select * from `users` where day(`created_at`) = ? or day(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testWhereMonthMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5);
        $this->assertEquals('select * from `users` where month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5], $builder->getBindings());
    }

    public function testOrWhereMonthMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereMonth('created_at', '=', 5)->orWhereMonth('created_at', '=', 6);
        $this->assertEquals('select * from `users` where month(`created_at`) = ? or month(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 5, 1 => 6], $builder->getBindings());
    }

    public function testWhereYearMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014);
        $this->assertEquals('select * from `users` where year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014], $builder->getBindings());
    }

    public function testOrWhereYearMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereYear('created_at', '=', 2014)->orWhereYear('created_at', '=', 2015);
        $this->assertEquals('select * from `users` where year(`created_at`) = ? or year(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => 2014, 1 => 2015], $builder->getBindings());
    }

    public function testWhereTimeMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '>=', '22:00');
        $this->assertEquals('select * from `users` where time(`created_at`) >= ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereTimeOperatorOptionalMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereTime('created_at', '22:00');
        $this->assertEquals('select * from `users` where time(`created_at`) = ?', $builder->toSql());
        $this->assertEquals([0 => '22:00'], $builder->getBindings());
    }

    public function testWhereBetweens()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [1, 2]);
        $this->assertEquals('select * from "users" where "id" between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotBetween('id', [1, 2]);
        $this->assertEquals('select * from "users" where "id" not between ? and ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereBetween('id', [new Raw(1), new Raw(2)]);
        $this->assertEquals('select * from "users" where "id" between 1 and 2', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testBasicOrWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhere('email', '=', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? or "email" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereRaw('id = ? or email = ?', [1, 'foo']);
        $this->assertEquals('select * from "users" where id = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testRawOrWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereRaw('email = ?', ['foo']);
        $this->assertEquals('select * from "users" where "id" = ? or email = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testBasicWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" = ? or "id" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testBasicWhereNotIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', [1, 2, 3]);
        $this->assertEquals('select * from "users" where "id" = ? or "id" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 1, 2 => 2, 3 => 3], $builder->getBindings());
    }

    public function testRawWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', [new Raw(1)]);
        $this->assertEquals('select * from "users" where "id" in (1)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', [new Raw(1)]);
        $this->assertEquals('select * from "users" where "id" = ? or "id" in (1)', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testInsertOrIgnoreMethod()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This database engine does not support insert or ignore.');
        $builder = $this->getBuilder();
        $builder->from('users')->insertOrIgnore(['email' => 'foo']);
    }

    public function testMySqlInsertOrIgnoreMethod()
    {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('affectingStatement')->once()->with('insert ignore into `users` (`email`) values (?)', ['foo'])->andReturn(1);
        $result = $builder->from('users')->insertOrIgnore(['email' => 'foo']);
        $this->assertEquals(1, $result);
    }

    public function testEmptyWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', []);
        $this->assertEquals('select * from "users" where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereIn('id', []);
        $this->assertEquals('select * from "users" where "id" = ? or 0 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testEmptyWhereNotIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', []);
        $this->assertEquals('select * from "users" where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNotIn('id', []);
        $this->assertEquals('select * from "users" where "id" = ? or 1 = 1', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testWhereIntegerInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerInRaw('id', ['1a', 2]);
        $this->assertEquals('select * from "users" where "id" in (1, 2)', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testWhereIntegerNotInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerNotInRaw('id', ['1a', 2]);
        $this->assertEquals('select * from "users" where "id" not in (1, 2)', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testEmptyWhereIntegerInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerInRaw('id', []);
        $this->assertEquals('select * from "users" where 0 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testEmptyWhereIntegerNotInRaw()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIntegerNotInRaw('id', []);
        $this->assertEquals('select * from "users" where 1 = 1', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testBasicWhereColumn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->whereColumn('first_name', 'last_name')
            ->orWhereColumn('first_name', 'middle_name');
        $this->assertEquals('select * from "users" where "first_name" = "last_name" or "first_name" = "middle_name"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn('updated_at', '>', 'created_at');
        $this->assertEquals('select * from "users" where "updated_at" > "created_at"', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testArrayWhereColumn()
    {
        $conditions = [
            ['first_name', 'last_name'],
            ['updated_at', '>', 'created_at'],
        ];

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereColumn($conditions);
        $this->assertEquals('select * from "users" where ("first_name" = "last_name" and "updated_at" > "created_at")', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());
    }

    public function testUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('select * from "users" where "id" = ? union select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getMySqlBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('(select * from `users` where `id` = ?) union (select * from `users` where `id` = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getMysqlBuilder();
        $expectedSql = '(select `a` from `t1` where `a` = ? and `b` = ?) union (select `a` from `t2` where `a` = ? and `b` = ?) order by `a` asc limit 10';
        $union = $this->getMysqlBuilder()->select('a')->from('t2')->where('a', 11)->where('b', 2);
        $builder->select('a')->from('t1')->where('a', 10)->where('b', 1)->union($union)->orderBy('a')->limit(10);
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals([0 => 10, 1 => 1, 2 => 11, 3 => 2], $builder->getBindings());
    }

    public function testUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $this->assertEquals('select * from "users" where "id" = ? union all select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testMultipleUnions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('select * from "users" where "id" = ? union select * from "users" where "id" = ? union select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testMultipleUnionAlls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->unionAll($this->getBuilder()->select('*')->from('users')->where('id', '=', 3));
        $this->assertEquals('select * from "users" where "id" = ? union all select * from "users" where "id" = ? union all select * from "users" where "id" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $builder->getBindings());
    }

    public function testUnionOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');
        $this->assertEquals('select * from "users" where "id" = ? union select * from "users" where "id" = ? order by "id" desc', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testUnionLimitsAndOffsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getBuilder()->select('*')->from('dogs'));
        $builder->skip(5)->take(10);
        $this->assertEquals('select * from "users" union select * from "dogs" limit 10 offset 5', $builder->toSql());
    }

    public function testUnionWithJoin()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getBuilder()->select('*')->from('dogs')->join('breeds', function ($join) {
            $join->on('dogs.breed_id', '=', 'breeds.id')->where('breeds.is_native', '=', 1);
        }));
        $this->assertEquals('select * from "users" union select * from "dogs" inner join "breeds" on "dogs"."breed_id" = "breeds"."id" and "breeds"."is_native" = ?', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testMySqlUnionOrderBys()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1);
        $builder->union($this->getMySqlBuilder()->select('*')->from('users')->where('id', '=', 2));
        $builder->orderBy('id', 'desc');
        $this->assertEquals('(select * from `users` where `id` = ?) union (select * from `users` where `id` = ?) order by `id` desc', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testMySqlUnionLimitsAndOffsets()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users');
        $builder->union($this->getMySqlBuilder()->select('*')->from('dogs'));
        $builder->skip(5)->take(10);
        $this->assertEquals('(select * from `users`) union (select * from `dogs`) limit 10 offset 5', $builder->toSql());
    }

    public function testUnionAggregate()
    {
        $expected = 'select count(*) as aggregate from ((select * from `posts`) union (select * from `videos`)) as `temp_table`';
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
        $builder->getProcessor()->shouldReceive('processSelect')->once();
        $builder->from('posts')->union($this->getMySqlBuilder()->from('videos'))->count();
        $this->assertSame('(select * from `posts`) union (select * from `videos`)', $builder->toSql());

        $expected = 'select count(*) as aggregate from ((select `id` from `posts`) union (select `id` from `videos`)) as `temp_table`';
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->with($expected, [], true);
        $builder->getProcessor()->shouldReceive('processSelect')->once();
        $builder->from('posts')->select('id')->union($this->getMySqlBuilder()->from('videos')->select('id'))->count();
        $this->assertSame('(select `id` from `posts`) union (select `id` from `videos`)', $builder->toSql());
    }

    public function testSubSelectWhereIns()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals('select * from "users" where "id" in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
        $this->assertEquals([25], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotIn('id', function ($q) {
            $q->select('id')->from('users')->where('age', '>', 25)->take(3);
        });
        $this->assertEquals('select * from "users" where "id" not in (select "id" from "users" where "age" > ? limit 3)', $builder->toSql());
        $this->assertEquals([25], $builder->getBindings());
    }

    public function testBasicWhereNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull('id');
        $this->assertEquals('select * from "users" where "id" is null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull('id');
        $this->assertEquals('select * from "users" where "id" = ? or "id" is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testArrayWhereNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" is null and "expires_at" is null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" = ? or "id" is null or "expires_at" is null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testBasicWhereNotNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull('id');
        $this->assertEquals('select * from "users" where "id" is not null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull('id');
        $this->assertEquals('select * from "users" where "id" > ? or "id" is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testArrayWhereNotNulls()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->whereNotNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" is not null and "expires_at" is not null', $builder->toSql());
        $this->assertEquals([], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', '>', 1)->orWhereNotNull(['id', 'expires_at']);
        $this->assertSame('select * from "users" where "id" > ? or "id" is not null or "expires_at" is not null', $builder->toSql());
        $this->assertEquals([0 => 1], $builder->getBindings());
    }

    public function testGroupBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('email');
        $this->assertEquals('select * from "users" group by "email"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('id', 'email');
        $this->assertEquals('select * from "users" group by "id", "email"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(['id', 'email']);
        $this->assertEquals('select * from "users" group by "id", "email"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy(new Raw('DATE(created_at)'));
        $this->assertEquals('select * from "users" group by DATE(created_at)', $builder->toSql());
    }

    public function testOrderBys()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderBy('age', 'desc');
        $this->assertEquals('select * from "users" order by "email" asc, "age" desc', $builder->toSql());

        $builder->orders = null;
        $this->assertEquals('select * from "users"', $builder->toSql());

        $builder->orders = [];
        $this->assertEquals('select * from "users"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('email')->orderByRaw('"age" ? desc', ['foo']);
        $this->assertEquals('select * from "users" order by "email" asc, "age" ? desc', $builder->toSql());
        $this->assertEquals(['foo'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderByDesc('name');
        $this->assertEquals('select * from "users" order by "name" desc', $builder->toSql());
    }

    public function testReorder()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name');
        $this->assertSame('select * from "users" order by "name" asc', $builder->toSql());
        $builder->reorder();
        $this->assertSame('select * from "users"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderBy('name');
        $this->assertSame('select * from "users" order by "name" asc', $builder->toSql());
        $builder->reorder('email', 'desc');
        $this->assertSame('select * from "users" order by "email" desc', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('first');
        $builder->union($this->getBuilder()->select('*')->from('second'));
        $builder->orderBy('name');
        $this->assertSame('select * from "first" union select * from "second" order by "name" asc', $builder->toSql());
        $builder->reorder();
        $this->assertSame('select * from "first" union select * from "second"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->orderByRaw('?', [true]);
        $this->assertEquals([true], $builder->getBindings());
        $builder->reorder();
        $this->assertEquals([], $builder->getBindings());
    }

    public function testHavings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', '>', 1);
        $this->assertEquals('select * from "users" having "email" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->orHaving('email', '=', 'test@example.com')
            ->orHaving('email', '=', 'test2@example.com');
        $this->assertEquals('select * from "users" having "email" = ? or "email" = ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->groupBy('email')->having('email', '>', 1);
        $this->assertEquals('select * from "users" group by "email" having "email" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('email as foo_email')->from('users')->having('foo_email', '>', 1);
        $this->assertEquals('select "email" as "foo_email" from "users" having "foo_email" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select(['category', new Raw('count(*) as "total"')])
            ->from('item')
            ->where('department', '=', 'popular')
            ->groupBy('category')
            ->having('total', '>', new Raw('3'));
        $this->assertEquals('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > 3', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select(['category', new Raw('count(*) as "total"')])
            ->from('item')
            ->where('department', '=', 'popular')
            ->groupBy('category')
            ->having('total', '>', 3);
        $this->assertEquals('select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > ?', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->havingBetween('last_login_date', ['2018-11-16', '2018-12-16']);
        $this->assertEquals('select * from "users" having "last_login_date" between ? and ?', $builder->toSql());
    }

    public function testHavingShortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('email', 1)->orHaving('email', 2);
        $this->assertEquals('select * from "users" having "email" = ? or "email" = ?', $builder->toSql());
    }

    public function testHavingFollowedBySelectGet()
    {
        $builder = $this->getBuilder();
        $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > ?';
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with($query, ['popular', 3], true)
            ->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "total"')])
            ->where('department', '=', 'popular')
            ->groupBy('category')
            ->having('total', '>', 3)
            ->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());

        // Using \Raw value
        $builder = $this->getBuilder();
        $query = 'select "category", count(*) as "total" from "item" where "department" = ? group by "category" having "total" > 3';
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with($query, ['popular'], true)
            ->andReturn([['category' => 'rock', 'total' => 5]]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('item');
        $result = $builder->select(['category', new Raw('count(*) as "total"')])
            ->where('department', '=', 'popular')
            ->groupBy('category')
            ->having('total', '>', new Raw('3'))
            ->get();
        $this->assertEquals([['category' => 'rock', 'total' => 5]], $result->all());
    }

    public function testRawHavings()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->havingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "users" having user_foo < user_bar', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->having('baz', '=', 1)->orHavingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "users" having "baz" = ? or user_foo < user_bar', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->havingBetween('last_login_date', ['2018-11-16', '2018-12-16'])
            ->orHavingRaw('user_foo < user_bar');
        $this->assertEquals('select * from "users" having "last_login_date" between ? and ? or user_foo < user_bar', $builder->toSql());
    }

    public function testLimitsAndOffsets()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->offset(5)->limit(10);
        $this->assertEquals('select * from "users" limit 10 offset 5', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(5)->take(10);
        $this->assertEquals('select * from "users" limit 10 offset 5', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(0)->take(0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->skip(-5)->take(-10);
        $this->assertEquals('select * from "users" offset 0', $builder->toSql());
    }

    public function testForPage()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 15);
        $this->assertEquals('select * from "users" limit 15 offset 15', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 15);
        $this->assertEquals('select * from "users" limit 15 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 15);
        $this->assertEquals('select * from "users" limit 15 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(2, 0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(0, 0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->forPage(-2, 0);
        $this->assertEquals('select * from "users" limit 0 offset 0', $builder->toSql());
    }

    public function testGetCountForPaginationWithBindings()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->selectSub(function ($q) {
            $q->select('body')->from('posts')->where('id', 4);
        }, 'post');

        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
        $this->assertEquals([4], $builder->getBindings());
    }

    public function testGetCountForPaginationWithColumnAliases()
    {
        $builder = $this->getBuilder();
        $columns = ['body as post_body', 'teaser', 'posts.created as published'];
        $builder->from('posts')->select($columns);

        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count("body", "teaser", "posts"."created") as aggregate from "posts"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination($columns);
        $this->assertEquals(1, $count);
    }

    public function testGetCountForPaginationWithUnion()
    {
        $builder = $this->getBuilder();
        $builder->from('posts')->select('id')->union($this->getBuilder()->from('videos')->select('id'));

        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from (select "id" from "posts" union select "id" from "videos") as "temp_table"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });

        $count = $builder->getCountForPagination();
        $this->assertEquals(1, $count);
    }

    public function testWhereShortcut()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('id', 1)->orWhere('name', 'foo');
        $this->assertEquals('select * from "users" where "id" = ? or "name" = ?', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 'foo'], $builder->getBindings());
    }

    public function testWhereWithArrayConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where([['foo', 1], ['bar', 2]]);
        $this->assertEquals('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where(['foo' => 1, 'bar' => 2]);
        $this->assertEquals('select * from "users" where ("foo" = ? and "bar" = ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where([['foo', 1], ['bar', '<', 2]]);
        $this->assertEquals('select * from "users" where ("foo" = ? and "bar" < ?)', $builder->toSql());
        $this->assertEquals([0 => 1, 1 => 2], $builder->getBindings());
    }

    public function testNestedWheres()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere(function ($q) {
            $q->where('name', '=', 'bar')->where('age', '=', 25);
        });
        $this->assertEquals('select * from "users" where "email" = ? or ("name" = ? and "age" = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar', 2 => 25], $builder->getBindings());
    }

    public function testNestedWhereBindings()
    {
        $builder = $this->getBuilder();
        $builder->where('email', '=', 'foo')->where(function ($q) {
            $q->selectRaw('?', ['ignore'])->where('name', '=', 'bar');
        });
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function testFullSubSelects()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('email', '=', 'foo')->orWhere('id', '=', function ($q) {
            $q->select(new Raw('max(id)'))->from('users')->where('email', '=', 'bar');
        });

        $this->assertEquals('select * from "users" where "email" = ? or "id" = (select max(id) from "users" where "email" = ?)', $builder->toSql());
        $this->assertEquals([0 => 'foo', 1 => 'bar'], $builder->getBindings());
    }

    public function testWhereExists()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereNotExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where "id" = ? or exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('id', '=', 1)->orWhereNotExists(function ($q) {
            $q->select('*')->from('products')->where('products.id', '=', new Raw('"orders"."id"'));
        });
        $this->assertEquals('select * from "orders" where "id" = ? or not exists (select * from "products" where "products"."id" = "orders"."id")', $builder->toSql());
    }

    public function testBasicJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', 'users.id', 'contacts.id');
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->join('contacts', 'users.id', '=', 'contacts.id')
            ->leftJoin('photos', 'users.id', '=', 'photos.id');
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" left join "photos" on "users"."id" = "photos"."id"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->leftJoinWhere('photos', 'users.id', '=', 'bar')
            ->joinWhere('photos', 'users.id', '=', 'foo');
        $this->assertEquals('select * from "users" left join "photos" on "users"."id" = ? inner join "photos" on "users"."id" = ?', $builder->toSql());
        $this->assertEquals(['bar', 'foo'], $builder->getBindings());
    }

    public function testCrossJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('sizes')->crossJoin('colors');
        $this->assertEquals('select * from "sizes" cross join "colors"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('tableB')->join('tableA', 'tableA.column1', '=', 'tableB.column2', 'cross');
        $this->assertEquals('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('tableB')->crossJoin('tableA', 'tableA.column1', '=', 'tableB.column2');
        $this->assertEquals('select * from "tableB" cross join "tableA" on "tableA"."column1" = "tableB"."column2"', $builder->toSql());
    }

    public function testComplexJoin()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orOn('users.name', '=', 'contacts.name');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "users"."name" = "contacts"."name"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->where('users.id', '=', 'foo')->orWhere('users.name', '=', 'bar');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = ? or "users"."name" = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());

        // Run the assertions again
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = ? or "users"."name" = ?', $builder->toSql());
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testJoinWhereNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is null', $builder->toSql());
    }

    public function testJoinWhereNotNull()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."deleted_at" is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotNull('contacts.deleted_at');
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."deleted_at" is not null', $builder->toSql());
    }

    public function testJoinWhereIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinWhereInSubquery()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $q = $this->getBuilder();
            $q->select('name')->from('contacts')->where('name', 'baz');
            $j->on('users.id', '=', 'contacts.id')->whereIn('contacts.name', $q);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" in (select "name" from "contacts" where "name" = ?)', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $q = $this->getBuilder();
            $q->select('name')->from('contacts')->where('name', 'baz');
            $j->on('users.id', '=', 'contacts.id')->orWhereIn('contacts.name', $q);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" in (select "name" from "contacts" where "name" = ?)', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testJoinWhereNotIn()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->whereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" and "contacts"."name" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->join('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->orWhereNotIn('contacts.name', [48, 'baz', null]);
        });
        $this->assertEquals('select * from "users" inner join "contacts" on "users"."id" = "contacts"."id" or "contacts"."name" not in (?, ?, ?)', $builder->toSql());
        $this->assertEquals([48, 'baz', null], $builder->getBindings());
    }

    public function testJoinsWithNestedConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->where(function ($j) {
                $j->where('contacts.country', '=', 'US')->orWhere('contacts.is_partner', '=', 1);
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and ("contacts"."country" = ? or "contacts"."is_partner" = ?)', $builder->toSql());
        $this->assertEquals(['US', 1], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', '=', 'contacts.id')->where('contacts.is_active', '=', 1)->orOn(function ($j) {
                $j->orWhere(function ($j) {
                    $j->where('contacts.country', '=', 'UK')->orOn('contacts.type', '=', 'users.type');
                })->where(function ($j) {
                    $j->where('contacts.country', '=', 'US')->orWhereNull('contacts.is_partner');
                });
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and "contacts"."is_active" = ? or (("contacts"."country" = ? or "contacts"."type" = "users"."type") and ("contacts"."country" = ? or "contacts"."is_partner" is null))', $builder->toSql());
        $this->assertEquals([1, 'UK', 'US'], $builder->getBindings());
    }

    public function testJoinsWithAdvancedConditions()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->where(function ($j) {
                $j->whereRole('admin')
                    ->orWhereNull('contacts.disabled')
                    ->orWhereRaw('year(contacts.created_at) = 2016');
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and ("role" = ? or "contacts"."disabled" is null or year(contacts.created_at) = 2016)', $builder->toSql());
        $this->assertEquals(['admin'], $builder->getBindings());
    }

    public function testJoinsWithSubqueryCondition()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereIn('contact_type_id', function ($q) {
                $q->select('id')->from('contact_types')->where('category_id', '1')->whereNull('deleted_at');
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and "contact_type_id" in (select "id" from "contact_types" where "category_id" = ? and "deleted_at" is null)', $builder->toSql());
        $this->assertEquals(['1'], $builder->getBindings());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('contact_types')
                    ->whereRaw('contact_types.id = contacts.contact_type_id')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at');
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and exists (select 1 from "contact_types" where contact_types.id = contacts.contact_type_id and "category_id" = ? and "deleted_at" is null)', $builder->toSql());
        $this->assertEquals(['1'], $builder->getBindings());
    }

    public function testJoinsWithAdvancedSubqueryCondition()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->leftJoin('contacts', function ($j) {
            $j->on('users.id', 'contacts.id')->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('contact_types')
                    ->whereRaw('contact_types.id = contacts.contact_type_id')
                    ->where('category_id', '1')
                    ->whereNull('deleted_at')
                    ->whereIn('level_id', function ($q) {
                        $q->select('id')->from('levels')->where('is_active', true);
                    });
            });
        });
        $this->assertEquals('select * from "users" left join "contacts" on "users"."id" = "contacts"."id" and exists (select 1 from "contact_types" where contact_types.id = contacts.contact_type_id and "category_id" = ? and "deleted_at" is null and "level_id" in (select "id" from "levels" where "is_active" = ?))', $builder->toSql());
        $this->assertEquals(['1', true], $builder->getBindings());
    }

    public function testJoinsWithNestedJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function (
            $j
        ) {
            $j->on('users.id', 'contacts.id')
                ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id');
        });
        $this->assertEquals('select "users"."id", "contacts"."id", "contact_types"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id") on "users"."id" = "contacts"."id"', $builder->toSql());
    }

    public function testJoinsWithMultipleNestedJoins()
    {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id', 'countrys.id', 'planets.id')
            ->from('users')
            ->leftJoin('contacts', function ($j) {
                $j->on('users.id', 'contacts.id')
                    ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
                    ->leftJoin('countrys', function ($q) {
                        $q->on('contacts.country', '=', 'countrys.country')->join('planets', function ($q) {
                            $q->on('countrys.planet_id', '=', 'planet.id')
                                ->where('planet.is_settled', '=', 1)
                                ->where('planet.population', '>=', 10000);
                        });
                    });
            });
        $this->assertEquals('select "users"."id", "contacts"."id", "contact_types"."id", "countrys"."id", "planets"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id" left join ("countrys" inner join "planets" on "countrys"."planet_id" = "planet"."id" and "planet"."is_settled" = ? and "planet"."population" >= ?) on "contacts"."country" = "countrys"."country") on "users"."id" = "contacts"."id"', $builder->toSql());
        $this->assertEquals(['1', 10000], $builder->getBindings());
    }

    public function testJoinsWithNestedJoinWithAdvancedSubqueryCondition()
    {
        $builder = $this->getBuilder();
        $builder->select('users.id', 'contacts.id', 'contact_types.id')->from('users')->leftJoin('contacts', function (
            $j
        ) {
            $j->on('users.id', 'contacts.id')
                ->join('contact_types', 'contacts.contact_type_id', '=', 'contact_types.id')
                ->whereExists(function ($q) {
                    $q->select('*')
                        ->from('countrys')
                        ->whereColumn('contacts.country', '=', 'countrys.country')
                        ->join('planets', function ($q) {
                            $q->on('countrys.planet_id', '=', 'planet.id')->where('planet.is_settled', '=', 1);
                        })
                        ->where('planet.population', '>=', 10000);
                });
        });
        $this->assertEquals('select "users"."id", "contacts"."id", "contact_types"."id" from "users" left join ("contacts" inner join "contact_types" on "contacts"."contact_type_id" = "contact_types"."id") on "users"."id" = "contacts"."id" and exists (select * from "countrys" inner join "planets" on "countrys"."planet_id" = "planet"."id" and "planet"."is_settled" = ? where "contacts"."country" = "countrys"."country" and "planet"."population" >= ?)', $builder->toSql());
        $this->assertEquals(['1', 10000], $builder->getBindings());
    }

    public function testJoinSub()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->joinSub('select * from "contacts"', 'sub', 'users.id', '=', 'sub.id');
        $this->assertEquals('select * from "users" inner join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->from('users')->joinSub(function ($q) {
            $q->from('contacts');
        }, 'sub', 'users.id', '=', 'sub.id');
        $this->assertEquals('select * from "users" inner join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

        $builder = $this->getBuilder();
        $eloquentBuilder = new ModelBuilder($this->getBuilder()->from('contacts'));
        $builder->from('users')->joinSub($eloquentBuilder, 'sub', 'users.id', '=', 'sub.id');
        $this->assertEquals('select * from "users" inner join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());

        $builder = $this->getBuilder();
        $sub1 = $this->getBuilder()->from('contacts')->where('name', 'foo');
        $sub2 = $this->getBuilder()->from('contacts')->where('name', 'bar');
        $builder->from('users')
            ->joinSub($sub1, 'sub1', 'users.id', '=', 1, 'inner', true)
            ->joinSub($sub2, 'sub2', 'users.id', '=', 'sub2.user_id');
        $expected = 'select * from "users" ';
        $expected .= 'inner join (select * from "contacts" where "name" = ?) as "sub1" on "users"."id" = ? ';
        $expected .= 'inner join (select * from "contacts" where "name" = ?) as "sub2" on "users"."id" = "sub2"."user_id"';
        $this->assertEquals($expected, $builder->toSql());
        $this->assertEquals(['foo', 1, 'bar'], $builder->getRawBindings()['join']);
    }

    public function testLeftJoinSub()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->leftJoinSub($this->getBuilder()->from('contacts'), 'sub', 'users.id', '=', 'sub.id');
        $this->assertEquals('select * from "users" left join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());
    }

    public function testRightJoinSub()
    {
        $builder = $this->getBuilder();
        $builder->from('users')->rightJoinSub($this->getBuilder()->from('contacts'), 'sub', 'users.id', '=', 'sub.id');
        $this->assertEquals('select * from "users" right join (select * from "contacts") as "sub" on "users"."id" = "sub"."id"', $builder->toSql());
    }

    public function testRawExpressionsInSelect()
    {
        $builder = $this->getBuilder();
        $builder->select(new Raw('substr(foo, 6)'))->from('users');
        $this->assertEquals('select substr(foo, 6) from "users"', $builder->toSql());
    }

    public function testFindReturnsFirstResultByID()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select * from "users" where "id" = ? limit 1', [1], true)
            ->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar']])
            ->andReturnUsing(function ($query, $results) {
                return $results;
            });
        $results = $builder->from('users')->find(1);
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testFirstMethodReturnsFirstResult()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select * from "users" where "id" = ? limit 1', [1], true)
            ->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar']])
            ->andReturnUsing(function ($query, $results) {
                return $results;
            });
        $results = $builder->from('users')->where('id', '=', 1)->first();
        $this->assertEquals(['foo' => 'bar'], $results);
    }

    public function testPluckMethodGetsCollectionOfColumnValues()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo');
        $this->assertEquals(['bar', 'baz'], $results->all());

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([
            ['id' => 1, 'foo' => 'bar'],
            ['id' => 10, 'foo' => 'baz'],
        ]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [
            ['id' => 1, 'foo' => 'bar'],
            ['id' => 10, 'foo' => 'baz'],
        ])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->pluck('foo', 'id');
        $this->assertEquals([1 => 'bar', 10 => 'baz'], $results->all());
    }

    public function testImplode()
    {
        // Test without glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo');
        $this->assertEquals('barbaz', $results);

        // Test with glue.
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->once()->andReturn([['foo' => 'bar'], ['foo' => 'baz']]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->with($builder, [
            ['foo' => 'bar'],
            ['foo' => 'baz'],
        ])->andReturnUsing(function ($query, $results) {
            return $results;
        });
        $results = $builder->from('users')->where('id', '=', 1)->implode('foo', ',');
        $this->assertEquals('bar,baz', $results);
    }

    public function testValueMethodReturnsSingleColumn()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select "foo" from "users" where "id" = ? limit 1', [1], true)
            ->andReturn([['foo' => 'bar']]);
        $builder->getProcessor()
            ->shouldReceive('processSelect')
            ->once()
            ->with($builder, [['foo' => 'bar']])
            ->andReturn([['foo' => 'bar']]);
        $results = $builder->from('users')->where('id', '=', 1)->value('foo');
        $this->assertEquals('bar', $results);
    }

    public function testAggregateFunctions()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->count();
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select exists(select * from "users") as "exists"', [], true)
            ->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->exists();
        $this->assertTrue($results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select exists(select * from "users") as "exists"', [], true)
            ->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->doesntExist();
        $this->assertTrue($results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select max("id") as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->max('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select min("id") as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->min('id');
        $this->assertEquals(1, $results);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select sum("id") as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $results = $builder->from('users')->sum('id');
        $this->assertEquals(1, $results);
    }

    public function testAggregateResetFollowedByGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select sum("id") as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 2]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select "column1", "column2" from "users"', [], true)
            ->andReturn([['column1' => 'foo', 'column2' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users')->select('column1', 'column2');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $sum = $builder->sum('id');
        $this->assertEquals(2, $sum);
        $result = $builder->get();
        $this->assertEquals([['column1' => 'foo', 'column2' => 'bar']], $result->all());
    }

    public function testAggregateResetFollowedBySelectGet()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count("column1") as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select "column2", "column3" from "users"', [], true)
            ->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->select('column2', 'column3')->get();
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function testAggregateResetFollowedByGetWithColumns()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count("column1") as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select "column2", "column3" from "users"', [], true)
            ->andReturn([['column2' => 'foo', 'column3' => 'bar']]);
        $builder->getProcessor()->shouldReceive('processSelect')->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users');
        $count = $builder->count('column1');
        $this->assertEquals(1, $count);
        $result = $builder->get(['column2', 'column3']);
        $this->assertEquals([['column2' => 'foo', 'column3' => 'bar']], $result->all());
    }

    public function testAggregateWithSubSelect()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('select')
            ->once()
            ->with('select count(*) as aggregate from "users"', [], true)
            ->andReturn([['aggregate' => 1]]);
        $builder->getProcessor()->shouldReceive('processSelect')->once()->andReturnUsing(function ($builder, $results) {
            return $results;
        });
        $builder->from('users')->selectSub(function ($query) {
            $query->from('posts')->select('foo', 'bar')->where('title', 'foo');
        }, 'post');
        $count = $builder->count();
        $this->assertEquals(1, $count);
        $this->assertEquals('(select "foo", "bar" from "posts" where "title" = ?) as "post"', $builder->columns[0]->getValue());
        $this->assertEquals(['foo'], $builder->getBindings());
    }

    public function testSubqueriesBindings()
    {
        $builder = $this->getBuilder();
        $second = $this->getBuilder()->select('*')->from('users')->orderByRaw('id = ?', 2);
        $third = $this->getBuilder()->select('*')->from('users')->where('id', 3)->groupBy('id')->having('id', '!=', 4);
        $builder->groupBy('a')->having('a', '=', 1)->union($second)->union($third);
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3, 3 => 4], $builder->getBindings());

        $builder = $this->getBuilder()->select('*')->from('users')->where('email', '=', function ($q) {
            $q->select(new Raw('max(id)'))
                ->from('users')
                ->where('email', '=', 'bar')
                ->orderByRaw('email like ?', '%.com')
                ->groupBy('id')
                ->having('id', '=', 4);
        })->orWhere('id', '=', 'foo')->groupBy('id')->having('id', '=', 5);
        $this->assertEquals([0 => 'bar', 1 => 4, 2 => '%.com', 3 => 'foo', 4 => 5], $builder->getBindings());
    }

    public function testInsertMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('insert')
            ->once()
            ->with('insert into "users" ("email") values (?)', ['foo'])
            ->andReturn(true);
        $result = $builder->from('users')->insert(['email' => 'foo']);
        $this->assertTrue($result);
    }

    public function testInsertUsingMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('insert')
            ->once()
            ->with('insert into "table1" ("foo") select "bar" from "table2" where "foreign_id" = ?', [5])
            ->andReturn(true);

        $result = $builder->from('table1')->insertUsing(['foo'], function (Builder $query) {
            $query->select(['bar'])->from('table2')->where('foreign_id', '=', 5);
        });

        $this->assertTrue($result);
    }

    public function testInsertGetIdMethod()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
            ->shouldReceive('processInsertGetId')
            ->once()
            ->with($builder, 'insert into "users" ("email") values (?)', ['foo'], 'id')
            ->andReturn(1);
        $result = $builder->from('users')->insertGetId(['email' => 'foo'], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertGetIdMethodRemovesExpressions()
    {
        $builder = $this->getBuilder();
        $builder->getProcessor()
            ->shouldReceive('processInsertGetId')
            ->once()
            ->with($builder, 'insert into "users" ("email", "bar") values (?, bar)', ['foo'], 'id')
            ->andReturn(1);
        $result = $builder->from('users')->insertGetId(['email' => 'foo', 'bar' => new Raw('bar')], 'id');
        $this->assertEquals(1, $result);
    }

    public function testInsertMethodRespectsRawBindings()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('insert')
            ->once()
            ->with('insert into "users" ("email") values (CURRENT TIMESTAMP)', [])
            ->andReturn(true);
        $result = $builder->from('users')->insert(['email' => new Raw('CURRENT TIMESTAMP')]);
        $this->assertTrue($result);
    }

    public function testMultipleInsertsWithExpressionValues()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('insert')
            ->once()
            ->with('insert into "users" ("email") values (UPPER(\'Foo\')), (LOWER(\'Foo\'))', [])
            ->andReturn(true);
        $result = $builder->from('users')->insert([
            ['email' => new Raw("UPPER('Foo')")],
            ['email' => new Raw("LOWER('Foo')")],
        ]);
        $this->assertTrue($result);
    }

    public function testUpdateMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update "users" set "email" = ?, "name" = ? where "id" = ?', ['foo', 'bar', 1])
            ->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update `users` set `email` = ?, `name` = ? where `id` = ? order by `foo` desc limit 5', [
                'foo',
                'bar',
                1,
            ])
            ->andReturn(1);
        $result = $builder->from('users')
            ->where('id', '=', 1)
            ->orderBy('foo', 'desc')
            ->limit(5)
            ->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoins()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" set "email" = ?, "name" = ? where "users"."id" = ?', [
                'foo',
                'bar',
                1,
            ])
            ->andReturn(1);
        $result = $builder->from('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1)
            ->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update "users" inner join "orders" on "users"."id" = "orders"."user_id" and "users"."id" = ? set "email" = ?, "name" = ?', [
                1,
                'foo',
                'bar',
            ])
            ->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodWithJoinsOnMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` set `email` = ?, `name` = ? where `users`.`id` = ?', [
                'foo',
                'bar',
                1,
            ])
            ->andReturn(1);
        $result = $builder->from('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('users.id', '=', 1)
            ->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update `users` inner join `orders` on `users`.`id` = `orders`.`user_id` and `users`.`id` = ? set `email` = ?, `name` = ?', [
                1,
                'foo',
                'bar',
            ])
            ->andReturn(1);
        $result = $builder->from('users')->join('orders', function ($join) {
            $join->on('users.id', '=', 'orders.user_id')->where('users.id', '=', 1);
        })->update(['email' => 'foo', 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateMethodRespectsRaw()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update "users" set "email" = foo, "name" = ? where "id" = ?', ['bar', 1])
            ->andReturn(1);
        $result = $builder->from('users')->where('id', '=', 1)->update(['email' => new Raw('foo'), 'name' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateOrInsertMethod()
    {
        $builder = Mockery::mock(Builder::class . '[where,exists,insert]', [
            Mockery::mock(ConnectionInterface::class),
            new Grammar(),
            Mockery::mock(Processor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(Mockery::self());
        $builder->shouldReceive('exists')->once()->andReturn(false);
        $builder->shouldReceive('insert')->once()->with(['email' => 'foo', 'name' => 'bar'])->andReturn(true);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));

        $builder = Mockery::mock(Builder::class . '[where,exists,update]', [
            Mockery::mock(ConnectionInterface::class),
            new Grammar(),
            Mockery::mock(Processor::class),
        ]);

        $builder->shouldReceive('where')->once()->with(['email' => 'foo'])->andReturn(Mockery::self());
        $builder->shouldReceive('exists')->once()->andReturn(true);
        $builder->shouldReceive('take')->andReturnSelf();
        $builder->shouldReceive('update')->once()->with(['name' => 'bar'])->andReturn(1);

        $this->assertTrue($builder->updateOrInsert(['email' => 'foo'], ['name' => 'bar']));
    }

    public function testDeleteMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete from "users" where "email" = ?', ['foo'])
            ->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete from "users" where "users"."id" = ?', [1])
            ->andReturn(1);
        $result = $builder->from('users')->delete(1);
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete from `users` where `email` = ? order by `id` asc limit 1', ['foo'])
            ->andReturn(1);
        $result = $builder->from('users')->where('email', '=', 'foo')->orderBy('id')->take(1)->delete();
        $this->assertEquals(1, $result);
    }

    public function testDeleteWithJoinMethod()
    {
        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `email` = ?', ['foo'])
            ->andReturn(1);
        $result = $builder->from('users')
            ->join('contacts', 'users.id', '=', 'contacts.id')
            ->where('email', '=', 'foo')
            ->orderBy('id')
            ->limit(1)
            ->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete `a` from `users` as `a` inner join `users` as `b` on `a`.`id` = `b`.`user_id` where `email` = ?', ['foo'])
            ->andReturn(1);
        $result = $builder->from('users AS a')
            ->join('users AS b', 'a.id', '=', 'b.user_id')
            ->where('email', '=', 'foo')
            ->orderBy('id')
            ->limit(1)
            ->delete();
        $this->assertEquals(1, $result);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('delete')
            ->once()
            ->with('delete `users` from `users` inner join `contacts` on `users`.`id` = `contacts`.`id` where `users`.`id` = ?', [1])
            ->andReturn(1);
        $result = $builder->from('users')
            ->join('contacts', 'users.id', '=', 'contacts.id')
            ->orderBy('id')
            ->take(1)
            ->delete(1);
        $this->assertEquals(1, $result);
    }

    public function testTruncateMethod()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('statement')->once()->with('truncate "users"', []);
        $builder->from('users')->truncate();
        $this->assertTrue(true);
    }

    public function testMySqlWrapping()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users');
        $this->assertEquals('select * from `users`', $builder->toSql());
    }

    public function testMySqlUpdateWrappingJson()
    {
        $grammar = new MySqlGrammar();
        $processor = Mockery::mock(Processor::class);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('update')
            ->with('update `users` set `name` = json_set(`name`, \'$."first_name"\', ?), `name` = json_set(`name`, \'$."last_name"\', ?) where `active` = ?', [
                'John',
                'Doe',
                1,
            ]);

        $builder = new Builder($connection, $grammar, $processor);

        $builder->from('users')->where('active', '=', 1)->update([
            'name->first_name' => 'John',
            'name->last_name' => 'Doe',
        ]);
    }

    public function testMySqlUpdateWrappingNestedJson()
    {
        $grammar = new MySqlGrammar();
        $processor = Mockery::mock(Processor::class);

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('update')
            ->with('update `users` set `meta` = json_set(`meta`, \'$."name"."first_name"\', ?), `meta` = json_set(`meta`, \'$."name"."last_name"\', ?) where `active` = ?', [
                'John',
                'Doe',
                1,
            ]);

        $builder = new Builder($connection, $grammar, $processor);

        $builder->from('users')->where('active', '=', 1)->update([
            'meta->name->first_name' => 'John',
            'meta->name->last_name' => 'Doe',
        ]);
    }

    public function testMySqlUpdateWithJsonPreparesBindingsCorrectly()
    {
        $grammar = new MySqlGrammar();
        $processor = Mockery::mock(Processor::class);

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('update')
            ->once()
            ->with('update `users` set `options` = json_set(`options`, \'$."enable"\', false), `updated_at` = ? where `id` = ?', [
                '2015-05-26 22:02:06',
                0,
            ]);
        $builder = new Builder($connection, $grammar, $processor);
        $builder->from('users')->where('id', '=', 0)->update([
            'options->enable' => false,
            'updated_at' => '2015-05-26 22:02:06',
        ]);

        $connection->shouldReceive('update')
            ->once()
            ->with('update `users` set `options` = json_set(`options`, \'$."size"\', ?), `updated_at` = ? where `id` = ?', [
                45,
                '2015-05-26 22:02:06',
                0,
            ]);
        $builder = new Builder($connection, $grammar, $processor);
        $builder->from('users')->where('id', '=', 0)->update([
            'options->size' => 45,
            'updated_at' => '2015-05-26 22:02:06',
        ]);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update `users` set `options` = json_set(`options`, \'$."size"\', ?)', [null]);
        $builder->from('users')->update(['options->size' => null]);

        $builder = $this->getMySqlBuilder();
        $builder->getConnection()
            ->shouldReceive('update')
            ->once()
            ->with('update `users` set `options` = json_set(`options`, \'$."size"\', 45)', []);
        $builder->from('users')->update(['options->size' => new Raw('45')]);
        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testMySqlWrappingJsonWithString()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->sku', '=', 'foo-bar');
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."sku"\')) = ?', $builder->toSql());
        $this->assertCount(1, $builder->getRawBindings()['where']);
        $this->assertEquals('foo-bar', $builder->getRawBindings()['where'][0]);
    }

    public function testMySqlWrappingJsonWithInteger()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
    }

    public function testMySqlWrappingJsonWithDouble()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price', '=', 1.5);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"\')) = ?', $builder->toSql());
    }

    public function testMySqlWrappingJsonWithBoolean()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."available"\') = true', $builder->toSql());
    }

    public function testMySqlWrappingJsonWithBooleanAndIntegerThatLooksLikeOne()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->available', '=', true)->where('items->active', '=', false)->where('items->number_available', '=', 0);
        $this->assertEquals('select * from `users` where json_extract(`items`, \'$."available"\') = true and json_extract(`items`, \'$."active"\') = false and json_unquote(json_extract(`items`, \'$."number_available"\')) = ?', $builder->toSql());
    }

    public function testMySqlWrappingJson()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereRaw('items->\'$."price"\' = 1');
        $this->assertEquals('select * from `users` where items->\'$."price"\' = 1', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('items->price')->from('users')->where('users.items->price', '=', 1)->orderBy('items->price');
        $this->assertEquals('select json_unquote(json_extract(`items`, \'$."price"\')) from `users` where json_unquote(json_extract(`users`.`items`, \'$."price"\')) = ? order by json_unquote(json_extract(`items`, \'$."price"\')) asc', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ?', $builder->toSql());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('items->price->in_usd', '=', 1)->where('items->age', '=', 2);
        $this->assertEquals('select * from `users` where json_unquote(json_extract(`items`, \'$."price"."in_usd"\')) = ? and json_unquote(json_extract(`items`, \'$."age"\')) = ?', $builder->toSql());
    }

    public function testMySqlSoundsLikeOperator()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('name', 'sounds like', 'John Doe');
        $this->assertEquals('select * from `users` where `name` sounds like ?', $builder->toSql());
        $this->assertEquals(['John Doe'], $builder->getBindings());
    }

    public function testMergeWheresCanMergeWheresAndBindings()
    {
        $builder = $this->getBuilder();
        $builder->wheres = ['foo'];
        $builder->mergeWheres(['wheres'], [12 => 'foo', 13 => 'bar']);
        $this->assertEquals(['foo', 'wheres'], $builder->wheres);
        $this->assertEquals(['foo', 'bar'], $builder->getBindings());
    }

    public function testProvidingNullWithOperatorsBuildsCorrectly()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', null);
        $this->assertEquals('select * from "users" where "foo" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '=', null);
        $this->assertEquals('select * from "users" where "foo" is null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '!=', null);
        $this->assertEquals('select * from "users" where "foo" is not null', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('foo', '<>', null);
        $this->assertEquals('select * from "users" where "foo" is not null', $builder->toSql());
    }

    public function testDynamicWhere()
    {
        $method = 'whereFooBarAndBazOrQux';
        $parameters = ['corge', 'waldo', 'fred'];
        $builder = Mockery::mock(Builder::class)->makePartial();

        $builder->shouldReceive('where')->with('foo_bar', '=', $parameters[0], 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('baz', '=', $parameters[1], 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('qux', '=', $parameters[2], 'or')->once()->andReturnSelf();

        $this->assertEquals($builder, $builder->dynamicWhere($method, $parameters));
    }

    public function testDynamicWhereIsNotGreedy()
    {
        $method = 'whereIosVersionAndAndroidVersionOrOrientation';
        $parameters = ['6.1', '4.2', 'Vertical'];
        $builder = Mockery::mock(Builder::class)->makePartial();

        $builder->shouldReceive('where')->with('ios_version', '=', '6.1', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('android_version', '=', '4.2', 'and')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('orientation', '=', 'Vertical', 'or')->once()->andReturnSelf();

        $builder->dynamicWhere($method, $parameters);
        $this->assertTrue(true);
    }

    public function testCallTriggersDynamicWhere()
    {
        $builder = $this->getBuilder();

        $this->assertEquals($builder, $builder->whereFooAndBar('baz', 'qux'));
        $this->assertCount(2, $builder->wheres);
    }

    public function testBuilderThrowsExpectedExceptionWithUndefinedMethod()
    {
        $this->expectException(BadMethodCallException::class);

        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select');
        $builder->getProcessor()->shouldReceive('processSelect')->andReturn([]);
        $builder->noValidMethodHere();
    }

    public function testMySqlLock()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock();
        $this->assertEquals('select * from `foo` where `bar` = ? for update', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false);
        $this->assertEquals('select * from `foo` where `bar` = ? lock in share mode', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock('lock in share mode');
        $this->assertEquals('select * from `foo` where `bar` = ? lock in share mode', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testSelectWithLockUsesWritePdo()
    {
        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->shouldReceive('select')->once()->with(Mockery::any(), Mockery::any(), false);
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock()->get();

        $builder = $this->getMySqlBuilderWithProcessor();
        $builder->getConnection()->shouldReceive('select')->once()->with(Mockery::any(), Mockery::any(), false);
        $builder->select('*')->from('foo')->where('bar', '=', 'baz')->lock(false)->get();

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testBindingOrder()
    {
        $expectedSql = 'select * from "users" inner join "othertable" on "bar" = ? where "registered" = ? group by "city" having "population" > ? order by match ("foo") against(?)';
        $expectedBindings = ['foo', 1, 3, 'bar'];

        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->join('othertable', function ($join) {
                $join->where('bar', '=', 'foo');
            })
            ->where('registered', 1)
            ->groupBy('city')
            ->having('population', '>', 3)
            ->orderByRaw('match ("foo") against(?)', ['bar']);
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());

        // order of statements reversed
        $builder = $this->getBuilder();
        $builder->select('*')
            ->from('users')
            ->orderByRaw('match ("foo") against(?)', ['bar'])
            ->having('population', '>', 3)
            ->groupBy('city')
            ->where('registered', 1)
            ->join('othertable', function ($join) {
                $join->where('bar', '=', 'foo');
            });
        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindings()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $builder->addBinding(['baz']);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testAddBindingWithArrayMergesBindingsInCorrectOrder()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['bar', 'baz'], 'having');
        $builder->addBinding(['foo'], 'where');
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuilders()
    {
        $builder = $this->getBuilder();
        $builder->addBinding(['foo', 'bar']);
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding(['baz']);
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testMergeBuildersBindingOrder()
    {
        $builder = $this->getBuilder();
        $builder->addBinding('foo', 'where');
        $builder->addBinding('baz', 'having');
        $otherBuilder = $this->getBuilder();
        $otherBuilder->addBinding('bar', 'where');
        $builder->mergeBindings($otherBuilder);
        $this->assertEquals(['foo', 'bar', 'baz'], $builder->getBindings());
    }

    public function testUppercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'AND');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testLowercaseLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'and');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testCaseInsensitiveLeadingBooleansAreRemoved()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('users')->where('name', '=', 'Taylor', 'And');
        $this->assertEquals('select * from "users" where "name" = ?', $builder->toSql());
    }

    public function testChunkWithLastChunkComplete()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3', 'foo4']);
        $chunk3 = collect([]);
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(3, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkWithLastChunkPartial()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3']);
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkCanBeStoppedByReturningFalse()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect(['foo1', 'foo2']);
        $chunk2 = collect(['foo3']);
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->never()->with(2, 2);
        $builder->shouldReceive('get')->times(1)->andReturn($chunk1);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);

            return false;
        });

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkWithCountZero()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk = collect([]);
        $builder->shouldReceive('forPage')->once()->with(1, 0)->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunk(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkPaginatesUsingIdWithLastChunkComplete()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = collect([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
        $chunk3 = collect([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkPaginatesUsingIdWithArray()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([['someIdField' => 1], ['someIdField' => 2]]);
        $chunk2 = collect([['someIdField' => 10]]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkPaginatesUsingIdWithLastChunkPartial()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = collect([(object) ['someIdField' => 10]]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkPaginatesUsingIdWithCountZero()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk = collect([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(0, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunkById(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testChunkPaginatesUsingIdWithAlias()
    {
        $builder = $this->getMockQueryBuilder();
        $builder->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = collect([(object) ['table_id' => 1], (object) ['table_id' => 10]]);
        $chunk2 = collect([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'table.id')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 10, 'table.id')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'table.id', 'table_id');

        // Avoid 'This test did not perform any assertions' notice
        $this->assertTrue(true);
    }

    public function testSimplePaginate()
    {
        $perPage = 16;
        $columns = ['test'];
        $pageName = 'page-name';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('get')->once()->andReturn($results);

        Context::set('path', $path);
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', __DIR__);
        }

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->once()->andReturnUsing(function ($interface, $args) {
            return new Paginator($args['items'], $args['perPage'], $args['currentPage'], $args['options']);
        });
        ApplicationContext::setContainer($container);
        Paginator::currentPathResolver(function () {
            return Context::get('path');
        });

        $result = $builder->simplePaginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new Paginator($results, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginate()
    {
        $perPage = 16;
        $columns = ['test'];
        $pageName = 'page-name';
        $page = 1;
        $total = 10;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('get')->once()->andReturn($results);
        $builder->shouldReceive('getCountForPagination')->andReturn($total);

        Context::set('path', $path);
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', __DIR__);
        }

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->once()->andReturnUsing(function ($interface, $args) {
            return new LengthAwarePaginator($args['items'], $args['total'], $args['perPage'], $args['currentPage'], $args['options']);
        });
        ApplicationContext::setContainer($container);
        Paginator::currentPathResolver(function () {
            return Context::get('path');
        });

        $result = $builder->paginate($perPage, $columns, $pageName, $page);

        $this->assertEquals(new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testSimplePaginateWithDefaultArguments()
    {
        $perPage = 15;
        $columns = ['*'];
        $pageName = 'page';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('get')->once()->andReturn($results);

        Context::set('path', $path);
        Context::set('page', $page);
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', __DIR__);
        }

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->once()->andReturnUsing(function ($interface, $args) {
            return new Paginator($args['items'], $args['perPage'], $args['currentPage'], $args['options']);
        });
        ApplicationContext::setContainer($container);

        Paginator::currentPageResolver(function () {
            return Context::get('page');
        });

        Paginator::currentPathResolver(function () {
            return Context::get('path');
        });

        $result = $builder->simplePaginate();

        $this->assertEquals(new Paginator($results, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWithDefaultArguments()
    {
        $perPage = 15;
        $columns = ['*'];
        $pageName = 'page';
        $page = 1;
        $total = 10;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect([['test' => 'foo'], ['test' => 'bar']]);

        $builder->shouldReceive('get')->once()->andReturn($results);
        $builder->shouldReceive('getCountForPagination')->andReturn($total);

        Context::set('path', $path);
        Context::set('page', $page);
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', __DIR__);
        }

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->once()->andReturnUsing(function ($interface, $args) {
            return new LengthAwarePaginator($args['items'], $args['total'], $args['perPage'], $args['currentPage'], $args['options']);
        });
        ApplicationContext::setContainer($container);

        Paginator::currentPageResolver(function () {
            return Context::get('page');
        });

        Paginator::currentPathResolver(function () {
            return Context::get('path');
        });

        $result = $builder->paginate();

        $this->assertEquals(new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testSimplePaginateWhenNoResults()
    {
        $perPage = 15;
        $pageName = 'page';
        $page = 1;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect();

        $builder->shouldReceive('get')->once()->andReturn($results);

        Context::set('path', $path);
        Context::set('page', $page);
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', __DIR__);
        }

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->once()->andReturnUsing(function ($interface, $args) {
            /** @var Collection $items */
            $items = $args['items'];
            return new Paginator(collect($items), $args['perPage'], $args['currentPage'], $args['options']);
        });
        ApplicationContext::setContainer($container);

        Paginator::currentPageResolver(function () {
            return Context::get('page');
        });

        Paginator::currentPathResolver(function () {
            return Context::get('path');
        });

        $result = $builder->simplePaginate();

        $this->assertEquals(new Paginator($results, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testPaginateWhenNoResults()
    {
        $perPage = 15;
        $pageName = 'page';
        $page = 1;
        $total = 10;
        $builder = $this->getMockQueryBuilder();
        $path = 'http://foo.bar?page=3';

        $results = collect();

        $builder->shouldReceive('get')->once()->andReturn($results);
        $builder->shouldReceive('getCountForPagination')->andReturn($total);

        Context::set('path', $path);
        Context::set('page', $page);
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', __DIR__);
        }

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')->once()->andReturnUsing(function ($interface, $args) {
            /** @var Collection $items */
            $items = $args['items'];
            return new LengthAwarePaginator(collect($items), $args['total'], $args['perPage'], $args['currentPage'], $args['options']);
        });
        ApplicationContext::setContainer($container);

        Paginator::currentPageResolver(function () {
            return Context::get('page');
        });

        Paginator::currentPathResolver(function () {
            return Context::get('path');
        });

        $result = $builder->paginate();

        $this->assertEquals(new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => $path,
            'pageName' => $pageName,
        ]), $result);
    }

    public function testWhereRowValues()
    {
        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update', 'order_number'], '<', [1, 2]);
        $this->assertEquals('select * from "orders" where ("last_update", "order_number") < (?, ?)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->where('company_id', 1)->orWhereRowValues([
            'last_update',
            'order_number',
        ], '<', [1, 2]);
        $this->assertEquals('select * from "orders" where "company_id" = ? or ("last_update", "order_number") < (?, ?)', $builder->toSql());

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update', 'order_number'], '<', [1, new Raw('2')]);
        $this->assertEquals('select * from "orders" where ("last_update", "order_number") < (?, 2)', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereRowValuesArityMismatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The number of columns must match the number of values');

        $builder = $this->getBuilder();
        $builder->select('*')->from('orders')->whereRowValues(['last_update'], '<', [1, 2]);
    }

    public function testWhereJsonContainsMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonContains('options', ['en']);
        $this->assertEquals('select * from `users` where json_contains(`options`, ?)', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonContains('users.options->languages', ['en']);
        $this->assertEquals('select * from `users` where json_contains(`users`.`options`, ?, \'$."languages"\')', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonContains('options->languages', new Raw("'[\"en\"]'"));
        $this->assertEquals('select * from `users` where `id` = ? or json_contains(`options`, \'["en"]\', \'$."languages"\')', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereJsonDoesntContainMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonDoesntContain('options->languages', ['en']);
        $this->assertEquals('select * from `users` where not json_contains(`options`, ?, \'$."languages"\')', $builder->toSql());
        $this->assertEquals(['["en"]'], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->where('id', '=', 1)->orWhereJsonDoesntContain('options->languages', new Raw("'[\"en\"]'"));
        $this->assertEquals('select * from `users` where `id` = ? or not json_contains(`options`, \'["en"]\', \'$."languages"\')', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testWhereJsonLengthMySql()
    {
        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonLength('options', 0);
        $this->assertEquals('select * from `users` where json_length(`options`) = ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')->from('users')->whereJsonLength('users.options->languages', '>', 0);
        $this->assertEquals('select * from `users` where json_length(`users`.`options`, \'$."languages"\') > ?', $builder->toSql());
        $this->assertEquals([0], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1)
            ->orWhereJsonLength('options->languages', new Raw('0'));
        $this->assertEquals('select * from `users` where `id` = ? or json_length(`options`, \'$."languages"\') = 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());

        $builder = $this->getMySqlBuilder();
        $builder->select('*')
            ->from('users')
            ->where('id', '=', 1)
            ->orWhereJsonLength('options->languages', '>', new Raw('0'));
        $this->assertEquals('select * from `users` where `id` = ? or json_length(`options`, \'$."languages"\') > 0', $builder->toSql());
        $this->assertEquals([1], $builder->getBindings());
    }

    public function testFromSub()
    {
        $builder = $this->getBuilder();
        $builder->fromSub(function ($query) {
            $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions')->where('foo', '=', '1');
        }, 'sessions')->where('bar', '<', '10');
        $this->assertEquals('select * from (select max(last_seen_at) as last_seen_at from "user_sessions" where "foo" = ?) as "sessions" where "bar" < ?', $builder->toSql());
        $this->assertEquals(['1', '10'], $builder->getBindings());
    }

    public function testFromSubWithoutBindings()
    {
        $builder = $this->getBuilder();
        $builder->fromSub(function ($query) {
            $query->select(new Raw('max(last_seen_at) as last_seen_at'))->from('user_sessions');
        }, 'sessions');
        $this->assertEquals('select * from (select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"', $builder->toSql());
    }

    public function testFromRaw()
    {
        $builder = $this->getBuilder();
        $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"'));
        $this->assertEquals('select * from (select max(last_seen_at) as last_seen_at from "user_sessions") as "sessions"', $builder->toSql());
    }

    public function testFromRawWithWhereOnTheMainQuery()
    {
        $builder = $this->getBuilder();
        $builder->fromRaw(new Raw('(select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at"'))
            ->where('last_seen_at', '>', '1520652582');
        $this->assertEquals('select * from (select max(last_seen_at) as last_seen_at from "sessions") as "last_seen_at" where "last_seen_at" > ?', $builder->toSql());
        $this->assertEquals(['1520652582'], $builder->getBindings());
    }

    public function testBitWheres()
    {
        $type = 16;
        $flags = 32;
        $builder = $this->getBuilder();
        $clone = $builder->clone();

        $builder->select('*')->from('users')->whereBit('type', $type);
        $this->assertEquals('select * from "users" where type & ? = ?', $builder->toSql());
        $builder->select('*')->from('users')->orWhereBit('flags', $flags);
        $this->assertEquals('select * from "users" where type & ? = ? or flags & ? = ?', $builder->toSql());

        $clone->select('*')->from('users')->whereBitNot('type', $type);
        $this->assertEquals('select * from "users" where type & ? != ?', $clone->toSql());
        $clone->select('*')->from('users')->orWhereBitNot('flags', $flags);
        $this->assertEquals('select * from "users" where type & ? != ? or flags & ? != ?', $clone->toSql());
    }

    public function testBitWheresOr()
    {
        $type = 16;
        $flags = 32;
        $builder = $this->getBuilder();
        $clone = $builder->clone();

        $builder->select('*')->from('users')->whereBitOr('type', $type);
        $this->assertEquals('select * from "users" where type | ? = ?', $builder->toSql());
        $builder->select('*')->from('users')->orWhereBitOr('flags', $flags);
        $this->assertEquals('select * from "users" where type | ? = ? or flags | ? = ?', $builder->toSql());

        $clone->select('*')->from('users')->whereBitOrNot('type', $type);
        $this->assertEquals('select * from "users" where type | ? != ?', $clone->toSql());
        $clone->select('*')->from('users')->orWhereBitOrNot('flags', $flags);
        $this->assertEquals('select * from "users" where type | ? != ? or flags | ? != ?', $clone->toSql());
    }

    public function testBitWheresXor()
    {
        $type = 16;
        $flags = 32;
        $builder = $this->getBuilder();
        $clone = $builder->clone();

        $builder->select('*')->from('users')->whereBitXor('type', $type);
        $this->assertEquals('select * from "users" where type ^ ? = ?', $builder->toSql());
        $builder->select('*')->from('users')->orWhereBitXor('flags', $flags);
        $this->assertEquals('select * from "users" where type ^ ? = ? or flags ^ ? = ?', $builder->toSql());

        $clone->select('*')->from('users')->whereBitXorNot('type', $type);
        $this->assertEquals('select * from "users" where type ^ ? != ?', $clone->toSql());
        $clone->select('*')->from('users')->orWhereBitXorNot('flags', $flags);
        $this->assertEquals('select * from "users" where type ^ ? != ? or flags ^ ? != ?', $clone->toSql());
    }

    public function testClone()
    {
        $builder = $this->getBuilder();
        $clone = $builder->clone();
        $this->assertEquals($builder->toSql(), $clone->toSql());
        $this->assertEquals($builder->getBindings(), $clone->getBindings());
        $this->assertNotSame($builder, $clone);
    }

    public function testToRawSql()
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('prepareBindings')
            ->with(['foo'])
            ->andReturn(['foo']);
        $connection->shouldReceive('escape')->with('foo')->andReturn('\'foo\'');
        $grammar = Mockery::mock(Grammar::class)->makePartial();
        $builder = new Builder($connection, $grammar, Mockery::mock(Processor::class));
        $builder->select('*')->from('users')->where('email', 'foo');

        $this->assertSame('select * from "users" where "email" = \'foo\'', $builder->toRawSql());
    }

    public function testQueryBuilderInvalidOperator()
    {
        $class = new ReflectionClass(Builder::class);
        $method = $class->getMethod('invalidOperator');
        $call = $method->getClosure($this->getMySqlBuilderWithProcessor());

        $this->assertTrue(call_user_func($call, 1));
        $this->assertTrue(call_user_func($call, '1'));
        $this->assertFalse(call_user_func($call, '<>'));
        $this->assertFalse(call_user_func($call, '='));
        $this->assertTrue(call_user_func($call, '!'));
    }

    public function testExistsOr()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->doesntExistOr(function () {
            return 123;
        });
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->doesntExistOr(function () {
            throw new RuntimeException();
        });
        $this->assertTrue($results);
    }

    public function testDoesntExistsOr()
    {
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 0]]);
        $results = $builder->from('users')->existsOr(function () {
            return 123;
        });
        $this->assertSame(123, $results);
        $builder = $this->getBuilder();
        $builder->getConnection()->shouldReceive('select')->andReturn([['exists' => 1]]);
        $results = $builder->from('users')->existsOr(function () {
            throw new RuntimeException();
        });
        $this->assertTrue($results);
    }

    protected function getBuilderWithProcessor(): Builder
    {
        return new Builder(Mockery::mock(ConnectionInterface::class), new Grammar(), new Processor());
    }

    protected function getMySqlBuilderWithProcessor(): Builder
    {
        $grammar = new MySqlGrammar();
        $processor = new MySqlProcessor();

        return new Builder(Mockery::mock(ConnectionInterface::class), $grammar, $processor);
    }

    /**
     * @return Builder|MockInterface
     */
    protected function getMockQueryBuilder()
    {
        return Mockery::mock(Builder::class, [
            Mockery::mock(ConnectionInterface::class),
            new Grammar(),
            Mockery::mock(Processor::class),
        ])->makePartial();
    }
}
