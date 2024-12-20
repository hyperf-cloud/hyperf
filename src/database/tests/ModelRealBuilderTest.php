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

use Carbon\Carbon;
use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\LengthAwarePaginatorInterface;
use Hyperf\Contract\PaginatorInterface;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\ConnectionResolver;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Connectors\ConnectionFactory;
use Hyperf\Database\Connectors\MySqlConnector;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Database\Exception\QueryException;
use Hyperf\Database\Exception\UniqueConstraintViolationException;
use Hyperf\Database\Model\EnumCollector;
use Hyperf\Database\Model\Events\Saved;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Register;
use Hyperf\Database\MySqlBitConnection;
use Hyperf\Database\Query\Builder as QueryBuilder;
use Hyperf\Database\Query\Expression;
use Hyperf\Database\Query\Grammars\Grammar as QueryGrammar;
use Hyperf\Database\Query\Processors\Processor;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Column;
use Hyperf\Database\Schema\MySqlBuilder;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Container;
use Hyperf\Engine\Channel;
use Hyperf\Paginator\LengthAwarePaginator;
use Hyperf\Paginator\Paginator;
use Hyperf\Stringable\Str;
use Hyperf\Support\Reflection\ClassInvoker;
use HyperfTest\Database\Stubs\ContainerStub;
use HyperfTest\Database\Stubs\IntegerStatus;
use HyperfTest\Database\Stubs\Model\Book;
use HyperfTest\Database\Stubs\Model\Gender;
use HyperfTest\Database\Stubs\Model\TestModel;
use HyperfTest\Database\Stubs\Model\TestVersionModel;
use HyperfTest\Database\Stubs\Model\User;
use HyperfTest\Database\Stubs\Model\UserBit;
use HyperfTest\Database\Stubs\Model\UserEnum;
use HyperfTest\Database\Stubs\Model\UserExt;
use HyperfTest\Database\Stubs\Model\UserExtCamel;
use HyperfTest\Database\Stubs\Model\UserRole;
use HyperfTest\Database\Stubs\Model\UserRoleMorphPivot;
use HyperfTest\Database\Stubs\Model\UserRolePivot;
use HyperfTest\Database\Stubs\StringStatus;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class ModelRealBuilderTest extends TestCase
{
    /**
     * @var array
     */
    protected $channel;

    protected function setUp(): void
    {
        $this->channel = new Channel(999);
    }

    protected function tearDown(): void
    {
        $container = $this->getContainer();
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionResolverInterface::class)->connection();
        $conn->statement('DROP TABLE IF EXISTS `test`;');
        $conn->statement('DROP TABLE IF EXISTS `test_full_text_index`;');
        $conn->statement('DROP TABLE IF EXISTS `test_enum_cast`;');
        $conn->statement('DROP TABLE IF EXISTS `users`;');
        $conn->statement('DROP TABLE IF EXISTS `posts`;');
        Mockery::close();
    }

    public function testPivot()
    {
        $this->getContainer();

        $user = User::query()->find(1);
        $role = $user->roles->first();
        $this->assertSame(1, $role->id);
        $this->assertSame('author', $role->name);

        $this->assertInstanceOf(UserRolePivot::class, $role->pivot);
        $this->assertSame(1, $role->pivot->user_id);
        $this->assertSame(1, $role->pivot->role_id);

        $role->pivot->updated_at = $now = Carbon::now()->toDateTimeString();
        $role->pivot->save();

        $pivot = UserRole::query()->find(1);
        $this->assertSame($now, $pivot->updated_at->toDateTimeString());

        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof Saved) {
                $this->assertSame($event->getModel(), $role->pivot);
                $hit = true;
            }
        }

        $this->assertTrue($hit);
    }

    public function testModelEnum()
    {
        $this->getContainer();

        /** @var UserEnum $user */
        $user = UserEnum::find(1);
        $this->assertTrue($user->gender instanceof Gender);
        $this->assertSame(Gender::MALE, $user->gender);

        $user->gender = Gender::FEMALE;
        $user->save();

        $sqls = [
            ['select * from `user` where `user`.`id` = ? limit 1', [1]],
            ['update `user` set `gender` = ?, `user`.`updated_at` = ? where `id` = ?', [Gender::FEMALE->value, Carbon::now()->toDateTimeString(), 1]],
        ];

        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }

        $user->gender = Gender::MALE;
        $user->save();

        $this->assertTrue(EnumCollector::has(Gender::class));
    }

    public function testForPageBeforeId()
    {
        $this->getContainer();

        User::query()->forPageBeforeId(2)->get();
        User::query()->forPageBeforeId(2, null)->get();
        User::query()->forPageBeforeId(2, 1)->get();

        $sqls = [
            ['select * from `user` where `id` < ? order by `id` desc limit 2', [0]],
            ['select * from `user` order by `id` desc limit 2', []],
            ['select * from `user` where `id` < ? order by `id` desc limit 2', [1]],
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }
    }

    public function testOrderByModelBuilder()
    {
        $this->getContainer();

        $sql = User::query()->orderBy(User::query()->select('id')->limit(1))->toSql();

        $this->assertSame('select * from `user` order by (select `id` from `user` limit 1) asc', $sql);
    }

    public function testForPageAfterId()
    {
        $this->getContainer();

        User::query()->forPageAfterId(2)->get();
        User::query()->forPageAfterId(2, null)->get();
        User::query()->forPageAfterId(2, 1)->get();

        $sqls = [
            ['select * from `user` where `id` > ? order by `id` asc limit 2', [0]],
            ['select * from `user` order by `id` asc limit 2', []],
            ['select * from `user` where `id` > ? order by `id` asc limit 2', [1]],
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }
    }

    public function testUserWhereBit()
    {
        $this->getContainer();

        $query = User::query()->whereBit('gender', 1);
        $res = $query->get();
        $this->assertTrue($res->count() > 0);

        $sqls = [
            ['select * from `user` where gender & ? = ?', [1, 1]],
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }
    }

    public function testForceIndexes()
    {
        $this->getContainer();

        User::query()->get();
        User::query()->forceIndexes(['PRIMARY'])->where('id', '>', 1)->get();

        $sqls = [
            ['select * from `user`', []],
            ['select * from `user` force index (`PRIMARY`) where `id` > ?', [1]],
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }
    }

    public function testIncrement()
    {
        $this->getContainer();

        /** @var UserExt $ext */
        $ext = UserExt::query()->find(1);
        $ext->timestamps = false;

        $this->assertFalse($ext->isDirty());

        $ext->increment('count', 1);
        $this->assertFalse($ext->isDirty());
        $this->assertArrayHasKey('count', $ext->getChanges());
        $this->assertSame(1, count($ext->getChanges()));

        $ext->increment('count', 1, [
            'str' => uniqid(),
        ]);
        $this->assertTrue($ext->save());
        $this->assertFalse($ext->isDirty());
        $this->assertArrayHasKey('str', $ext->getChanges());
        $this->assertArrayHasKey('count', $ext->getChanges());
        $this->assertSame(2, count($ext->getChanges()));

        // Don't effect.
        $ext->str = uniqid();
        $this->assertTrue($ext->isDirty('str'));

        $ext->increment('count', 1, [
            'float_num' => (string) ($ext->float_num + 1),
        ]);
        $this->assertTrue($ext->isDirty('str'));
        $this->assertArrayHasKey('count', $ext->getChanges());
        $this->assertArrayHasKey('float_num', $ext->getChanges());

        $this->assertSame(2, count($ext->getChanges()));
        $this->assertTrue($ext->save());
        $this->assertArrayHasKey('str', $ext->getChanges());
        $this->assertSame(1, count($ext->getChanges()));

        $ext->float_num = (string) ($ext->float_num + 1);
        $this->assertTrue($ext->save());
        $this->assertArrayHasKey('float_num', $ext->getChanges());
        $this->assertSame(1, count($ext->getChanges()));

        $sqls = [
            'select * from `user_ext` where `user_ext`.`id` = ? limit 1',
            'update `user_ext` set `count` = `count` + 1 where `id` = ?',
            'update `user_ext` set `count` = `count` + 1, `str` = ? where `id` = ?',
            'update `user_ext` set `count` = `count` + 1, `float_num` = ? where `id` = ?',
            'update `user_ext` set `str` = ? where `id` = ?',
            'update `user_ext` set `float_num` = ? where `id` = ?',
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame($event->sql, array_shift($sqls));
            }
        }
    }

    public function testCamelCaseGetModel()
    {
        $this->getContainer();

        /** @var UserExtCamel $ext */
        $ext = UserExtCamel::query()->find(1);
        $this->assertArrayHasKey('floatNum', $ext->toArray());
        $this->assertArrayHasKey('createdAt', $ext->toArray());
        $this->assertIsString($ext->updatedAt);
        $this->assertIsString($ext->toArray()['updatedAt']);

        $this->assertIsString($number = $ext->floatNum);

        $ext->increment('float_num', 1);

        $model = UserExtCamel::query()->find(1);
        $this->assertSame($ext->floatNum, $model->floatNum);

        $model->fill([
            'floatNum' => '1.20',
        ]);
        $model->save();

        $sqls = [
            'select * from `user_ext` where `user_ext`.`id` = ? limit 1',
            'update `user_ext` set `float_num` = `float_num` + 1, `user_ext`.`updated_at` = ? where `id` = ?',
            'select * from `user_ext` where `user_ext`.`id` = ? limit 1',
            'update `user_ext` set `float_num` = ?, `user_ext`.`updated_at` = ? where `id` = ?',
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame($event->sql, array_shift($sqls));
            }
        }
    }

    public function testSaveMorphPivot()
    {
        $this->getContainer();
        $pivot = UserRoleMorphPivot::query()->find(1);
        $pivot->created_at = $now = Carbon::now();
        $pivot->save();

        $sqls = [
            ['select * from `user_role` where `user_role`.`id` = ? limit 1', [1]],
            ['update `user_role` set `created_at` = ?, `user_role`.`updated_at` = ? where `id` = ?', [$now->toDateTimeString(), $now->toDateTimeString(), 1]],
        ];

        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame([$event->sql, $event->bindings], array_shift($sqls));
            }
        }
    }

    public function testGetColumnListing()
    {
        $container = $this->getContainer();
        $connection = $container->get(ConnectionResolverInterface::class)->connection();
        /** @var MySqlBuilder $builder */
        $builder = $connection->getSchemaBuilder('default');
        $columns = $builder->getColumnListing('user_ext');
        foreach ($columns as $column) {
            $this->assertSame($column, strtolower($column));
        }
    }

    public function testGetColumnTypeListing()
    {
        $container = $this->getContainer();
        $connection = $container->get(ConnectionResolverInterface::class)->connection();
        /** @var MySqlBuilder $builder */
        $builder = $connection->getSchemaBuilder('default');
        $columns = $builder->getColumnTypeListing('user_ext');
        $column = $columns[0];
        foreach ($column as $key => $value) {
            $this->assertSame($key, strtolower($key));
        }
    }

    public function testGetColumns()
    {
        $container = $this->getContainer();
        $connection = $container->get(ConnectionResolverInterface::class)->connection();
        /** @var MySqlBuilder $builder */
        $builder = $connection->getSchemaBuilder('default');
        $columns = $builder->getColumns();
        foreach ($columns as $column) {
            if ($column->getTable() === 'book') {
                break;
            }
        }
        $this->assertInstanceOf(Column::class, $column);
        $this->assertSame('hyperf', $column->getSchema());
        $this->assertSame('book', $column->getTable());
        $this->assertSame('id', $column->getName());
        $this->assertSame(1, $column->getPosition());
        $this->assertSame(null, $column->getDefault());
        $this->assertSame(false, $column->isNullable());
        $this->assertSame('bigint', $column->getType());
        $this->assertSame('', $column->getComment());
    }

    public function testUpsert()
    {
        $container = $this->getContainer();
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionResolverInterface::class)->connection();
        $conn->statement('CREATE TABLE `test` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `uid` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $res = TestModel::query()->insert(['user_id' => 1, 'uid' => 1]);
        $this->assertTrue($res);

        try {
            $res = TestModel::query()->insert(['user_id' => 1, 'uid' => 1]);
        } catch (UniqueConstraintViolationException $exception) {
            // check if the exception is instance of QueryException
            $this->assertInstanceOf(QueryException::class, $exception);
            $this->assertStringContainsString('Duplicate entry \'1\' for key', $exception->getMessage());
        }

        $model = TestModel::query()->find(1);
        $this->assertSame(1, $model->uid);

        $res = TestModel::query()->upsert(['user_id' => 1, 'uid' => 2], []);
        $this->assertSame(2, $res);

        $model = TestModel::query()->find(1);
        $this->assertSame(2, $model->uid);
    }

    public function testToRawSql()
    {
        $container = $this->getContainer();

        $sql = TestModel::query()->toRawSql();
        $this->assertSame('select * from `test`', $sql);

        $sql = TestModel::query()->where('user_id', 1)->toRawSql();
        $this->assertSame('select * from `test` where `user_id` = 1', $sql);
    }

    public function testGetRawQueryLog()
    {
        $container = $this->getContainer();
        /** @var Connection $conn */
        $conn = $container->get(ConnectionResolverInterface::class)->connection();
        $conn->statement('CREATE TABLE `test` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `uid` bigint(20) unsigned NOT NULL,
            `version` bigint(20) unsigned NOT NULL,
            `created_at` datetime DEFAULT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY (`user_id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $conn->enableQueryLog();
        $conn->select('select * from `test` where `user_id` = ?', [1]);
        $logs = $conn->getRawQueryLog();
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertSame('select * from `test` where `user_id` = 1', $logs[0]['raw_query']);
    }

    public function testMySQLSetNull()
    {
        $container = $this->getContainer();
        /** @var Connection $conn */
        $conn = $container->get(ConnectionResolverInterface::class)->connection();
        $conn->statement('CREATE TABLE `test` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `uid` bigint(20) unsigned NOT NULL,
            `version` bigint(20) unsigned NOT NULL,
            `str_value` varchar(32) NULL DEFAULT NULL,
            `int_value` bigint(20) unsigned NULL DEFAULT NULL,
            `created_at` datetime DEFAULT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY (`user_id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $conn->enableQueryLog();
        $model = new TestModel();
        $model->user_id = 1;
        $model->uid = 1;
        $model->version = 1;
        $model->str_value = null;
        $model->int_value = null;
        $model->save();

        $model = TestModel::query()->where('user_id', 1)->first();
        $this->assertNull($model->str_value);
        $this->assertNull($model->int_value);
    }

    public function testRewriteSetKeysForSaveQuery()
    {
        $container = $this->getContainer();
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionResolverInterface::class)->connection();
        $conn->statement('CREATE TABLE `test` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `uid` bigint(20) unsigned NOT NULL,
  `version` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $res = TestVersionModel::query()->insert(['user_id' => 1, 'uid' => 1, 'version' => 2]);
        $this->assertTrue($res);

        /** @var TestVersionModel $model */
        $model = TestVersionModel::query()->first();
        $model->user_id = 2;
        $model->version = 1;
        $model->save();

        $this->assertSame(2, TestVersionModel::query()->first()->user_id);

        $model->mustVersion = true;
        $model->user_id = 3;
        $model->version = 0;
        $model->save();

        $this->assertSame(2, TestVersionModel::query()->first()->user_id);

        $model->user_id = 4;
        $model->version = 2;
        $model->save();

        $this->assertSame(4, TestVersionModel::query()->first()->user_id);

        $sqls = [
            'update `test` set `user_id` = ?, `version` = ?, `test`.`updated_at` = ? where `id` = ?',
            'update `test` set `user_id` = ?, `version` = ?, `test`.`updated_at` = ? where `id` = ? and `version` <= ?',
            'update `test` set `user_id` = ?, `version` = ?, `test`.`updated_at` = ? where `id` = ? and `version` <= ?',
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted && str_starts_with($event->sql, 'update')) {
                $this->assertSame($event->sql, array_shift($sqls));
            }
        }
    }

    public function testBigIntInsertAndGet()
    {
        $container = $this->getContainer();
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionResolverInterface::class)->connection();
        $conn->statement('CREATE TABLE `test` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `uid` bigint(20) unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $sql = 'INSERT INTO test(`user_id`, `uid`) VALUES (?,?)';
        $this->assertTrue($conn->insert($sql, [PHP_INT_MAX, 1]));

        $binds = [
            [PHP_INT_MAX, 1],
            [(string) PHP_INT_MAX, 1],
            [PHP_INT_MAX, (string) 1],
            [(string) PHP_INT_MAX, (string) 1],
        ];
        $sql = 'SELECT * FROM test WHERE user_id = ? AND uid = ?';
        foreach ($binds as $bind) {
            $res = $conn->select($sql, $bind);
            $this->assertNotEmpty($res);
        }

        $binds = [
            [1, PHP_INT_MAX],
            [1, (string) PHP_INT_MAX],
            [(string) 1, PHP_INT_MAX],
            [(string) 1, (string) PHP_INT_MAX],
        ];
        $sql = 'SELECT * FROM test WHERE uid = ? AND user_id = ?';
        foreach ($binds as $bind) {
            $res = $conn->select($sql, $bind);
            $this->assertNotEmpty($res);
        }
    }

    public function testSimplePaginate()
    {
        $container = $this->getContainer();
        $container->shouldReceive('make')->with(PaginatorInterface::class, Mockery::any())->andReturnUsing(function ($_, $args) {
            return new Paginator(...array_values($args));
        });
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));
        $res = Db::table('user')->simplePaginate(1);
        $this->assertTrue($res->hasMorePages());
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame('select * from `user` limit 2 offset 0', $event->sql);
            }
        }

        $res = User::query()->simplePaginate(1);
        $this->assertTrue($res->hasMorePages());
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame('select * from `user` limit 2 offset 0', $event->sql);
            }
        }
    }

    public function testChunkById()
    {
        $container = $this->getContainer();
        $container->shouldReceive('make')->with(PaginatorInterface::class, Mockery::any())->andReturnUsing(function ($_, $args) {
            return new Paginator(...array_values($args));
        });
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));
        Context::set($key = 'chunk.by.id.' . uniqid(), 0);
        Db::table('user')->chunkById(2, function ($data) use ($key) {
            $id = $data->first()->id;
            $this->assertNotSame($id, Context::get($key));
            Context::set($key, $id);
        });
    }

    public function testChunkByIdButNotFound()
    {
        $container = $this->getContainer();
        $container->shouldReceive('make')->with(PaginatorInterface::class, Mockery::any())->andReturnUsing(function ($_, $args) {
            return new Paginator(...array_values($args));
        });
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));

        $this->expectException(RuntimeException::class);

        Db::table('user')->chunkById(1, fn () => 1, 'created_at');
    }

    public function testPaginationCountQuery()
    {
        $container = $this->getContainer();
        $container->shouldReceive('make')->with(LengthAwarePaginatorInterface::class, Mockery::any())->andReturnUsing(function ($_, $args) {
            return new LengthAwarePaginator(...array_values($args));
        });
        User::query()->select('gender')->groupBy('gender')->paginate(10, ['*'], 'page', 0);
        $sqls = [
            'select count(*) as aggregate from (select `gender` from `user` group by `gender`) as `aggregate_table`',
            'select `gender` from `user` group by `gender` limit 10 offset 0',
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame($event->sql, array_shift($sqls));
            }
        }
    }

    public function testSaveBitValue()
    {
        $container = Mockery::mock(Container::class);
        ApplicationContext::setContainer($container);

        $container->shouldReceive('has')->andReturn(true);
        $container->shouldReceive('get')->with('db.connector.mysql.bit')->andReturn(new MySqlConnector());
        $connector = new ConnectionFactory($container);

        Connection::resolverFor('mysql.bit', static function ($connection, $database, $prefix, $config) {
            return new MySqlBitConnection($connection, $database, $prefix, $config);
        });

        $dbConfig = [
            'driver' => 'mysql.bit',
            'host' => '127.0.0.1',
            'database' => 'hyperf',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ];

        $connection = $connector->make($dbConfig);

        $resolver = new ConnectionResolver(['default' => $connection]);

        $container->shouldReceive('get')->with(ConnectionResolverInterface::class)->andReturn($resolver);
        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturn(null);

        /** @var UserBit $model */
        $model = UserBit::query()->find(1);
        $model->bit = (int) (! $model->bit);
        $this->assertTrue($model->save());

        $model->bit = ! $model->bit;
        $this->assertTrue($model->save());
    }

    public function testSaveExpression()
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));

        /** @var UserExt $ext */
        $ext = UserExt::query()->find(1);
        $ext->timestamps = false;

        $this->assertFalse($ext->isDirty());
        $ext->count = Db::raw('`count` + 1');
        $this->assertTrue($ext->isDirty('count'));
        $this->assertTrue($ext->save());

        $this->assertFalse($ext->isDirty());
        $ext->float_num = Db::raw('`float_num` + 0.1');
        $this->assertTrue($ext->isDirty('float_num'));
        $this->assertTrue($ext->save());

        $this->assertFalse($ext->isDirty());
        $ext->str = Db::raw('concat(`str`, \'t\')');
        $this->assertTrue($ext->isDirty('str'));
        $this->assertTrue($ext->save());

        $this->assertFalse($ext->isDirty());
        $ext->count = Db::raw('`count` + 1');
        $ext->float_num = Db::raw('`float_num` + 0.1');
        $ext->str = Db::raw('concat(`str`, \'e\')');
        $this->assertTrue($ext->isDirty());
        $this->assertTrue($ext->save());

        $sqls = [
            'select * from `user_ext` where `user_ext`.`id` = ? limit 1',
            'update `user_ext` set `count` = `count` + 1 where `id` = ?',
            'update `user_ext` set `float_num` = `float_num` + 0.1 where `id` = ?',
            'update `user_ext` set `str` = concat(`str`, \'t\') where `id` = ?',
            'update `user_ext` set `count` = `count` + 1, `float_num` = `float_num` + 0.1, `str` = concat(`str`, \'e\') where `id` = ?',
        ];
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame($event->sql, array_shift($sqls));
            }
        }
    }

    public function testSelectForBindingIntegerWhenUsingVarcharIndex()
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));
        $res = Db::select('EXPLAIN SELECT * FROM `user` WHERE `name` = ?;', ['1']);
        $this->assertSame('ref', $res[0]->type);
        $res = Db::select('EXPLAIN SELECT * FROM `user` WHERE `name` = ?;', [1]);
        $this->assertSame('ref', $res[0]->type);
    }

    public function testBeforeExecuting()
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));

        $res = Db::selectOne('SELECT * FROM `user` WHERE id = ?;', [1]);
        $this->assertSame('Hyperf', $res->name);

        try {
            $chan = new Channel(2);
            Db::beforeExecuting(function (string $sql, array $bindings, Connection $connection) use ($chan) {
                $this->assertSame(null, $connection->getConfig('name'));
                $chan->push(1);
            });
            Db::beforeExecuting(function (string $sql, array $bindings, Connection $connection) use ($chan) {
                $this->assertSame('SELECT * FROM `user` WHERE id = ?;', $sql);
                $this->assertSame([1], $bindings);
                $chan->push(2);
            });

            $res = Db::selectOne('SELECT * FROM `user` WHERE id = ?;', [1]);
            $this->assertSame('Hyperf', $res->name);
            $this->assertSame(1, $chan->pop(1));
            $this->assertSame(2, $chan->pop(1));
        } finally {
            Connection::clearBeforeExecutingCallbacks();
        }
    }

    public function testModelBuilderValue()
    {
        $this->getContainer();

        $res = User::query()->join('book', 'user.id', '=', 'book.user_id')->value('book.title');

        $this->assertNotEmpty($res);

        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame($event->sql, 'select `book`.`title` from `user` inner join `book` on `user`.`id` = `book`.`user_id` limit 1');
            }
        }
    }

    public function testWhereFullText()
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));

        Schema::create('test_full_text_index', function (Blueprint $table) {
            $table->id('id');
            $table->string('title', 200);
            $table->text('body');
            $table->fullText(['title', 'body']);
        });

        Db::table('test_full_text_index')->insert([
            ['title' => 'MySQL Tutorial', 'body' => 'DBMS stands for DataBase ...'],
            ['title' => 'How To Use MySQL Well', 'body' => 'After you went through a ...'],
            ['title' => 'Optimizing MySQL', 'body' => 'In this tutorial, we show ...'],
            ['title' => '1001 MySQL Tricks', 'body' => '1. Never run mysqld as root. 2. ...'],
            ['title' => 'MySQL vs. YourSQL', 'body' => 'In the following database comparison ...'],
            ['title' => 'MySQL Security', 'body' => 'When configured properly, MySQL ...'],
        ]);

        $result = Db::table('test_full_text_index')->whereFullText(['title', 'body'], 'database')->get();
        $this->assertCount(2, $result);
        $this->assertSame('MySQL Tutorial', $result[0]->title);
        $this->assertSame('MySQL vs. YourSQL', $result[1]->title);

        // boolean mode
        $result = Db::table('test_full_text_index')->whereFullText(['title', 'body'], '+MySQL -YourSQL', ['mode' => 'boolean'])->get();
        $this->assertCount(5, $result);

        // expanded query
        $result = Db::table('test_full_text_index')->whereFullText(['title', 'body'], 'database', ['expanded' => true])->get();
        $this->assertCount(6, $result);
    }

    public function testJoinLateral(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('id');
            $table->string('name');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id('id');
            $table->string('title');
            $table->integer('rating');
            $table->unsignedBigInteger('user_id');
        });

        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));

        $mySqlVersion = Db::select('select version()')[0]->{'version()'} ?? '';

        if (version_compare($mySqlVersion, '8.0.14', '<')) {
            $this->markTestSkipped('Lateral joins are not supported on MySQL < 8.0.14' . __CLASS__);
        }
        Db::table('users')->insert([
            ['name' => Str::random()],
            ['name' => Str::random()],
        ]);

        Db::table('posts')->insert([
            ['title' => Str::random(), 'rating' => 1, 'user_id' => 1],
            ['title' => Str::random(), 'rating' => 3, 'user_id' => 1],
            ['title' => Str::random(), 'rating' => 7, 'user_id' => 1],
        ]);
        $subquery = Db::table('posts')
            ->select('title as best_post_title', 'rating as best_post_rating')
            ->whereColumn('user_id', 'users.id')
            ->orderBy('rating', 'desc')
            ->limit(2);

        $userWithPosts = Db::table('users')
            ->where('id', 1)
            ->joinLateral($subquery, 'best_post')
            ->get();

        $this->assertCount(2, $userWithPosts);
        $this->assertEquals(7, $userWithPosts[0]->best_post_rating);
        $this->assertEquals(3, $userWithPosts[1]->best_post_rating);

        $userWithoutPosts = Db::table('users')
            ->where('id', 2)
            ->joinLateral($subquery, 'best_post')
            ->get();

        $this->assertCount(0, $userWithoutPosts);

        $subquery = Db::table('posts')
            ->select('title as best_post_title', 'rating as best_post_rating')
            ->whereColumn('user_id', 'users.id')
            ->orderBy('rating', 'desc')
            ->limit(2);

        $userWithPosts = Db::table('users')
            ->where('id', 1)
            ->leftJoinLateral($subquery, 'best_post')
            ->get();

        $this->assertCount(2, $userWithPosts);
        $this->assertEquals(7, $userWithPosts[0]->best_post_rating);
        $this->assertEquals(3, $userWithPosts[1]->best_post_rating);

        $userWithoutPosts = Db::table('users')
            ->where('id', 2)
            ->leftJoinLateral($subquery, 'best_post')
            ->get();

        $this->assertCount(1, $userWithoutPosts);
        $this->assertNull($userWithoutPosts[0]->best_post_title);
        $this->assertNull($userWithoutPosts[0]->best_post_rating);
    }

    public function testChunkMap()
    {
        Schema::dropIfExists('posts');
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->timestamps();
        });
        $container = $this->getContainer();

        $db = new Db($container);
        $container->shouldReceive('get')->with(Db::class)->andReturn($db);
        Db::table('posts')->whereRaw('1=1')->delete();
        Db::table('posts')->insert([
            ['title' => 'Foo Post', 'content' => 'Lorem Ipsum.', 'created_at' => new Carbon('2017-11-12 13:14:15')],
            ['title' => 'Bar Post', 'content' => 'Lorem Ipsum.', 'created_at' => new Carbon('2018-01-02 03:04:05')],
        ]);
        $results = Db::table('posts')->orderBy('id')->chunkMap(function ($post) {
            return $post->title;
        }, 1);
        $this->assertCount(2, $results);
        $this->assertSame('Foo Post', $results[0]);
        $this->assertSame('Bar Post', $results[1]);
    }

    public function testEnumCast()
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));

        Schema::create('test_enum_cast', function (Blueprint $table) {
            $table->id();
            $table->string('string_status', 64);
            $table->integer('integer_status');
        });

        // test insert with enum
        Db::table('test_enum_cast')->insert([
            'string_status' => StringStatus::Active,
            'integer_status' => IntegerStatus::Active,
        ]);

        // test select with enum
        $record = Db::table('test_enum_cast')->where('string_status', StringStatus::Active)->first();

        $this->assertNotNull($record);
        $this->assertEquals('active', $record->string_status);
        $this->assertEquals(1, $record->integer_status);

        // test update with enum
        Db::table('test_enum_cast')->where('id', $record->id)->update([
            'string_status' => StringStatus::Inactive,
            'integer_status' => IntegerStatus::Inactive,
        ]);

        $record2 = Db::table('test_enum_cast')->where('id', $record->id)->first();

        $this->assertNotNull($record2);
        $this->assertEquals('inactive', $record2->string_status);
        $this->assertEquals(2, $record2->integer_status);
    }

    public function testCleanBindings()
    {
        $query = new QueryBuilder(
            Mockery::mock(ConnectionInterface::class),
            Mockery::mock(QueryGrammar::class),
            Mockery::mock(Processor::class)
        );

        $invoker = new ClassInvoker($query);
        $res = $invoker->cleanBindings([0, 2, '2', '']);
        $this->assertSame([0, 2, '2', ''], $res);

        $res = $invoker->cleanBindings([0, 2, new Expression('1'), '2', '']);
        $this->assertSame([0, 2, '2', ''], $res);

        $res = $invoker->cleanBindings([new Expression('1')]);
        $this->assertSame([], $res);

        $res = $invoker->cleanBindings([StringStatus::Active, IntegerStatus::Active, new Expression('1')]);
        $this->assertSame(['active', 1], $res);
    }

    public function testAddSelectObjects()
    {
        $this->getContainer();
        $models = User::query()->addSelect([
            'book_id' => Book::query()
                ->select('id')
                ->whereColumn('book.user_id', 'user.id')
                ->limit(1),
        ])->get();
        $this->assertTrue($models->isNotEmpty());
        while ($event = $this->channel->pop(0.001)) {
            if ($event instanceof QueryExecuted) {
                $this->assertSame($event->sql, 'select `user`.*, (select `id` from `book` where `book`.`user_id` = `user`.`id` limit 1) as `book_id` from `user`');
            }
        }
    }

    public function testIncrementEach()
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));
        Schema::create('accounting', function (Blueprint $table) {
            $table->increments('id');
            $table->float('wallet_1');
            $table->float('wallet_2');
            $table->integer('user_id');
            $table->string('name', 20);
        });

        Db::table('accounting')->insert([
            [
                'wallet_1' => 100,
                'wallet_2' => 200,
                'user_id' => 1,
                'name' => 'Taylor',
            ],
            [
                'wallet_1' => 15,
                'wallet_2' => 300,
                'user_id' => 2,
                'name' => 'Otwell',
            ],
        ]);
        $connection = Db::table('accounting')->getConnection();
        $connection->enableQueryLog();

        Db::table('accounting')->where('user_id', 2)->incrementEach([
            'wallet_1' => 10,
            'wallet_2' => -20,
        ], ['name' => 'foo']);

        $queryLogs = $connection->getQueryLog();
        $this->assertCount(1, $queryLogs);

        $rows = Db::table('accounting')->get();

        $this->assertCount(2, $rows);
        // other rows are not affected.
        $this->assertEquals([
            'id' => 1,
            'wallet_1' => 100,
            'wallet_2' => 200,
            'user_id' => 1,
            'name' => 'Taylor',
        ], (array) $rows[0]);

        $this->assertEquals([
            'id' => 2,
            'wallet_1' => 15 + 10,
            'wallet_2' => 300 - 20,
            'user_id' => 2,
            'name' => 'foo',
        ], (array) $rows[1]);

        // without the second argument.
        $affectedRowsCount = Db::table('accounting')->where('user_id', 2)->incrementEach([
            'wallet_1' => 20,
            'wallet_2' => 20,
        ]);

        $this->assertEquals(1, $affectedRowsCount);

        $rows = Db::table('accounting')->get();

        $this->assertEquals([
            'id' => 2,
            'wallet_1' => 15 + (10 + 20),
            'wallet_2' => 300 + (-20 + 20),
            'user_id' => 2,
            'name' => 'foo',
        ], (array) $rows[1]);

        // Test Can affect multiple rows at once.
        $affectedRowsCount = Db::table('accounting')->incrementEach([
            'wallet_1' => 31.5,
            'wallet_2' => '-32.5',
        ]);

        $this->assertEquals(2, $affectedRowsCount);

        $rows = Db::table('accounting')->get();
        $this->assertEquals([
            'id' => 1,
            'wallet_1' => 100 + 31.5,
            'wallet_2' => 200 - 32.5,
            'user_id' => 1,
            'name' => 'Taylor',
        ], (array) $rows[0]);

        $this->assertEquals([
            'id' => 2,
            'wallet_1' => 15 + (10 + 20 + 31.5),
            'wallet_2' => 300 + (-20 + 20 - 32.5),
            'user_id' => 2,
            'name' => 'foo',
        ], (array) $rows[1]);

        // In case of a conflict, the second argument wins and sets a fixed value:
        $affectedRowsCount = Db::table('accounting')->incrementEach([
            'wallet_1' => 3000,
        ], ['wallet_1' => 1.5]);

        $this->assertEquals(2, $affectedRowsCount);

        $rows = Db::table('accounting')->get();

        $this->assertEquals(1.5, $rows[0]->wallet_1);
        $this->assertEquals(1.5, $rows[1]->wallet_1);

        Schema::drop('accounting');
    }

    public function testDecrementEach()
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));
        Schema::create('accounting_test', function (Blueprint $table) {
            $table->increments('id');
            $table->float('wallet_1');
            $table->float('wallet_2');
            $table->integer('user_id');
            $table->string('name', 20);
        });

        Db::table('accounting_test')->insert([
            [
                'wallet_1' => 100,
                'wallet_2' => 200,
                'user_id' => 1,
                'name' => 'Taylor',
            ],
            [
                'wallet_1' => 15,
                'wallet_2' => 300,
                'user_id' => 2,
                'name' => 'Otwell',
            ],
        ]);
        $connection = Db::table('accounting_test')->getConnection();
        $connection->enableQueryLog();

        Db::table('accounting_test')->where('user_id', 2)->decrementEach([
            'wallet_1' => 10,
            'wallet_2' => 20,
        ], ['name' => 'foo']);

        $queryLogs = $connection->getQueryLog();
        $this->assertCount(1, $queryLogs);

        $rows = Db::table('accounting_test')->get();

        $this->assertCount(2, $rows);
        // other rows are not affected.
        $this->assertEquals([
            'id' => 1,
            'wallet_1' => 100,
            'wallet_2' => 200,
            'user_id' => 1,
            'name' => 'Taylor',
        ], (array) $rows[0]);

        $this->assertEquals([
            'id' => 2,
            'wallet_1' => 15 - 10,
            'wallet_2' => 300 - 20,
            'user_id' => 2,
            'name' => 'foo',
        ], (array) $rows[1]);

        // without the second argument.
        $affectedRowsCount = Db::table('accounting_test')->where('user_id', 2)->decrementEach([
            'wallet_1' => 20,
            'wallet_2' => 20,
        ]);

        $this->assertEquals(1, $affectedRowsCount);

        $rows = Db::table('accounting_test')->get();

        $this->assertEquals([
            'id' => 2,
            'wallet_1' => 15 - (10 + 20),
            'wallet_2' => 300 - (20 + 20),
            'user_id' => 2,
            'name' => 'foo',
        ], (array) $rows[1]);

        // Test Can affect multiple rows at once.
        $affectedRowsCount = Db::table('accounting_test')->decrementEach([
            'wallet_1' => 31.5,
            'wallet_2' => '32.5',
        ]);

        $this->assertEquals(2, $affectedRowsCount);

        $rows = Db::table('accounting_test')->get();
        $this->assertEquals([
            'id' => 1,
            'wallet_1' => 100 - 31.5,
            'wallet_2' => 200 - 32.5,
            'user_id' => 1,
            'name' => 'Taylor',
        ], (array) $rows[0]);

        $this->assertEquals([
            'id' => 2,
            'wallet_1' => 15 - (10 + 20 + 31.5),
            'wallet_2' => 300 - (20 + 20 + 32.5),
            'user_id' => 2,
            'name' => 'foo',
        ], (array) $rows[1]);

        // In case of a conflict, the second argument wins and sets a fixed value:
        $affectedRowsCount = Db::table('accounting_test')->decrementEach([
            'wallet_1' => 3000,
        ], ['wallet_1' => 1.5]);

        $this->assertEquals(2, $affectedRowsCount);

        $rows = Db::table('accounting_test')->get();

        $this->assertSame(1.5, $rows[0]->wallet_1);
        $this->assertSame(1.5, $rows[1]->wallet_1);

        Schema::drop('accounting_test');
    }

    public function testOrderedLazyById(): void
    {
        $container = $this->getContainer();
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));
        Schema::create('lazy_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        $now = Carbon::now();
        Db::table('lazy_users')->insert([
            ['name' => 'Hyperf1', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hyperf2', 'created_at' => $now->addMinutes(), 'updated_at' => $now->addMinutes()],
            ['name' => 'Hyperf3', 'created_at' => $now->addMinutes(2), 'updated_at' => $now->addMinutes(2)],
            ['name' => 'Hyperf4', 'created_at' => $now->addMinutes(3), 'updated_at' => $now->addMinutes(3)],
            ['name' => 'Hyperf5', 'created_at' => $now->addMinutes(4), 'updated_at' => $now->addMinutes(4)],
            ['name' => 'Hyperf6', 'created_at' => $now->addMinutes(5), 'updated_at' => $now->addMinutes(5)],
            ['name' => 'Hyperf7', 'created_at' => $now->addMinutes(6), 'updated_at' => $now->addMinutes(6)],
            ['name' => 'Hyperf8', 'created_at' => $now->addMinutes(7), 'updated_at' => $now->addMinutes(7)],
            ['name' => 'Hyperf9', 'created_at' => $now->addMinutes(8), 'updated_at' => $now->addMinutes(8)],
            ['name' => 'Hyperf10', 'created_at' => $now->addMinutes(9), 'updated_at' => $now->addMinutes(9)],
        ]);
        $results = LazyUserModel::query()->lazyById(10);
        $this->assertCount(10, $results);
        foreach ($results as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        $dbResults = Db::table('lazy_users')->lazyById(10);
        $this->assertCount(10, $dbResults);
        foreach ($dbResults as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        $results = LazyUserModel::query()->lazyById(5);
        $dbResults = Db::table('lazy_users')->lazyById(5);
        $this->assertCount(10, $results);
        foreach ($results as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        $this->assertCount(10, $dbResults);
        foreach ($dbResults as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        $results = LazyUserModel::query()->lazyByIdDesc(10);
        $this->assertCount(10, $results);
        foreach ($results as $index => $value) {
            $this->assertSame('Hyperf' . (10 - $index), $value->name);
        }
        $dbResults = Db::table('lazy_users')->lazyByIdDesc(10);
        $this->assertCount(10, $dbResults);
        foreach ($dbResults as $index => $value) {
            $this->assertSame('Hyperf' . (10 - $index), $value->name);
        }
        $results = LazyUserModel::query()->lazyByIdDesc(5);
        $dbResults = Db::table('lazy_users')->lazyByIdDesc(5);
        $this->assertCount(10, $dbResults);
        foreach ($dbResults as $index => $value) {
            $this->assertSame('Hyperf' . (10 - $index), $value->name);
        }
        $this->assertCount(10, $results);
        foreach ($results as $index => $value) {
            $this->assertSame('Hyperf' . (10 - $index), $value->name);
        }
        $results = LazyUserModel::query()->select(['id', 'name', 'created_at as create_date', 'updated_at'])->lazyByIdDesc(10, 'created_at', 'create_date');
        $dbResults = Db::table('lazy_users')->select(['id', 'name', 'created_at as create_date', 'updated_at'])->lazyByIdDesc(10, 'created_at', 'create_date');
        $this->assertCount(10, $results);
        foreach ($results as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        $this->assertCount(10, $dbResults);
        foreach ($dbResults as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        $results = LazyUserModel::query()->select(['id', 'name', 'created_at as create_date', 'updated_at'])->lazyById(10, 'created_at', 'create_date');
        $dbResults = Db::table('lazy_users')->select(['id', 'name', 'created_at as create_date', 'updated_at'])->lazyById(10, 'created_at', 'create_date');
        $this->assertCount(10, $results);
        foreach ($results as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        $this->assertCount(10, $dbResults);
        foreach ($dbResults as $index => $value) {
            $this->assertSame('Hyperf' . ($index + 1), $value->name);
        }
        Schema::dropIfExists('lazy_users');
    }

    public function testUpdateOrFail(): void
    {
        $container = $this->getContainer();
        Register::setConnectionResolver($container->get(ConnectionResolverInterface::class));
        $container->shouldReceive('get')->with(Db::class)->andReturn(new Db($container));

        Schema::create('update_or_fail', function (Blueprint $table) {
            $table->id();
            $table->string('name', 5);
            $table->timestamps();
        });
        $model = UpdateOrFail::create([
            'name' => Str::random(5),
        ]);

        try {
            $model->updateOrFail([
                'name' => Str::random(6),
            ]);
        } catch (Exception $e) {
            $this->assertInstanceOf(QueryException::class, $e);
        }

        $this->assertFalse((new UpdateOrFail())->updateOrFail([]));
        $name = Str::random(4);
        $model->updateOrFail([
            'name' => $name,
        ]);
        $this->assertSame($name, $model->name);
        Schema::drop('update_or_fail');
    }

    protected function getContainer()
    {
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->with(Mockery::any())->andReturnUsing(function ($event) {
            $this->channel->push($event);
        });
        $container = ContainerStub::getContainer(function ($conn) use ($dispatcher) {
            $conn->setEventDispatcher($dispatcher);
        });
        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturn($dispatcher);

        return $container;
    }
}

class LazyUserModel extends Model
{
    protected ?string $table = 'lazy_users';
}

class UpdateOrFail extends Model
{
    protected ?string $table = 'update_or_fail';

    protected array $guarded = [];
}
