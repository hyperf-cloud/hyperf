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

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionResolver;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Connectors\ConnectionFactory;
use Hyperf\Database\Connectors\MySqlConnector;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Register;
use Hyperf\Database\Schema\Builder;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Container;
use HyperfTest\Database\Stubs\ContainerStub;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 * @coversNothing
 */
class DatabaseIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $container = Mockery::mock(Container::class);
        ApplicationContext::setContainer($container);

        $container->shouldReceive('has')->andReturn(true);
        $container->shouldReceive('has')->with(StdoutLoggerInterface::class)->andReturnFalse();
        $container->shouldReceive('has')->with(EventDispatcherInterface::class)->andReturnFalse();
        $container->shouldReceive('get')->with('db.connector.mysql')->andReturn(new MySqlConnector());
        $connector = new ConnectionFactory($container);

        $dbConfig = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'hyperf',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ];

        $db2Config = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'hyperf2',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ];

        $connection = $connector->make($dbConfig, 'default');
        $connection2 = $connector->make($db2Config, 'second_connection');

        $resolver = new ConnectionResolver(['default' => $connection, 'second_connection' => $connection2]);

        $container->shouldReceive('get')->with(ConnectionResolverInterface::class)->andReturn($resolver);
        $container = ContainerStub::getContainer();
        $db = new Db($container);
        $container->shouldReceive('get')->with(Db::class)->andReturn($db);
        Register::setConnectionResolver($resolver);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        foreach (['default', 'second_connection'] as $connection) {
            $this->schema($connection)->drop('users');
            $this->schema($connection)->drop('unique_users');
            $this->schema($connection)->drop('friends');
            $this->schema($connection)->drop('posts');
        }
    }

    public function testUpdateOrCreateOnDifferentConnection(): void
    {
        ModelTestUser::create(['email' => 'taylorotwell@gmail.com']);

        ModelTestUser::on('second_connection')->updateOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        ModelTestUser::on('second_connection')->updateOrCreate(
            ['email' => 'themsaid@gmail.com'],
            ['name' => 'Mohamed Said']
        );

        $this->assertEquals(1, ModelTestUser::count());
        $this->assertEquals(2, ModelTestUser::on('second_connection')->count());
    }

    public function testCheckAndCreateMethodsOnMultiConnections(): void
    {
        ModelTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        ModelTestUser::on('second_connection')->find(
            ModelTestUser::on('second_connection')->insert(['id' => 2, 'email' => 'themsaid@gmail.com'])
        );

        $user1 = ModelTestUser::on('second_connection')->findOrNew(1);
        $user2 = ModelTestUser::on('second_connection')->findOrNew(2);
        $this->assertFalse($user1->exists);
        $this->assertTrue($user2->exists);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());

        $user1 = ModelTestUser::on('second_connection')->firstOrNew(['email' => 'taylorotwell@gmail.com']);
        $user2 = ModelTestUser::on('second_connection')->firstOrNew(['email' => 'themsaid@gmail.com']);
        $this->assertFalse($user1->exists);
        $this->assertTrue($user2->exists);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());
        $this->assertEquals(1, ModelTestUser::on('second_connection')->count());

        $user1 = ModelTestUser::on('second_connection')->firstOrCreate(['email' => 'taylorotwell@gmail.com']);
        $user2 = ModelTestUser::on('second_connection')->firstOrCreate(['email' => 'themsaid@gmail.com']);
        $this->assertSame('second_connection', $user1->getConnectionName());
        $this->assertSame('second_connection', $user2->getConnectionName());
        $this->assertEquals(2, ModelTestUser::on('second_connection')->count());
    }

    public function testCreateOrFirst(): void
    {
        $user1 = ModelTestUniqueUser::createOrFirst(['email' => 'taylorotwell@gmail.com']);
        $this->assertSame('taylorotwell@gmail.com', $user1->email);

        $user2 = ModelTestUniqueUser::createOrFirst(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );
        $this->assertEquals($user1->id, $user2->id);
        $this->assertSame('taylorotwell@gmail.com', $user2->email);
        $this->assertNull($user2->name);

        $user3 = ModelTestUniqueUser::createOrFirst(
            ['email' => 'abigailotwell@gmail.com'],
            ['name' => 'Abigail Otwell']
        );
        $this->assertNotEquals($user3->id, $user1->id);
        $this->assertSame('abigailotwell@gmail.com', $user3->email);
        $this->assertSame('Abigail Otwell', $user3->name);

        $user4 = ModelTestUniqueUser::createOrFirst(
            ['name' => 'Dries Vints'],
            ['name' => 'Nuno Maduro', 'email' => 'nuno@laravel.com']
        );

        $this->assertSame('Nuno Maduro', $user4->name);
    }

    public function testWithWhereHasOnNestedSelfReferencingBelongsToManyRelationship()
    {
        $user = ModelTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = ModelTestUser::withWhereHas('friends.friends', function ($query) {
            $query->where('email', 'foo@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
        $this->assertTrue($results->first()->relationLoaded('friends'));
        $this->assertSame($results->first()->friends->pluck('email')->unique()->toArray(), ['abigailotwell@gmail.com']);
        $this->assertSame($results->first()->friends->pluck('friends')->flatten()->pluck('email')->unique()->toArray(), ['foo@gmail.com']);
    }

    public function testWithWhereHasOnSelfReferencingBelongsToManyRelationship()
    {
        $user = ModelTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = ModelTestUser::withWhereHas('friends', function ($query) {
            $query->where('email', 'abigailotwell@gmail.com');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('taylorotwell@gmail.com', $results->first()->email);
        $this->assertTrue($results->first()->relationLoaded('friends'));
        $this->assertSame($results->first()->friends->pluck('email')->unique()->toArray(), ['abigailotwell@gmail.com']);
    }

    public function testWithWhereHasOnSelfReferencingBelongsToRelationship()
    {
        $parentPost = ModelTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        ModelTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = ModelTestPost::withWhereHas('parentPost', function ($query) {
            $query->where('name', 'Parent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->name, 'Parent Post');
    }

    public function testWithWhereHasOnNestedSelfReferencingBelongsToRelationship()
    {
        $grandParentPost = ModelTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = ModelTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        ModelTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = ModelTestPost::withWhereHas('parentPost.parentPost', function ($query) {
            $query->where('name', 'Grandparent Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Child Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->name, 'Parent Post');
        $this->assertTrue($results->first()->parentPost->relationLoaded('parentPost'));
        $this->assertSame($results->first()->parentPost->parentPost->name, 'Grandparent Post');
    }

    public function testWithWhereHasOnSelfReferencingHasManyRelationship()
    {
        $parentPost = ModelTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        ModelTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = ModelTestPost::withWhereHas('childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Parent Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('childPosts'));
        $this->assertSame($results->first()->childPosts->pluck('name')->unique()->toArray(), ['Child Post']);
    }

    public function testWithWhereHasOnNestedSelfReferencingHasManyRelationship()
    {
        $grandParentPost = ModelTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = ModelTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        ModelTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = ModelTestPost::withWhereHas('childPosts.childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertCount(1, $results);
        $this->assertSame('Grandparent Post', $results->first()->name);
        $this->assertTrue($results->first()->relationLoaded('childPosts'));
        $this->assertSame($results->first()->childPosts->pluck('name')->unique()->toArray(), ['Parent Post']);
        $this->assertSame($results->first()->childPosts->pluck('childPosts')->flatten()->pluck('name')->unique()->toArray(), ['Child Post']);
    }

    protected function createSchema(): void
    {
        foreach (['default', 'second_connection'] as $connection) {
            $this->schema($connection)->create('users', function ($table) {
                $table->increments('id');
                $table->string('email')->nullable();
                $table->string('name')->nullable();
                $table->string('value')->nullable();
                $table->timestamp('birthday')->nullable();
                $table->timestamps();
            });
            $this->schema($connection)->create('unique_users', function ($table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->timestamp('birthday', 6)->nullable();
                $table->timestamps();
            });
            $this->schema($connection)->create('friends', function ($table) {
                $table->integer('user_id');
                $table->integer('friend_id');
                $table->integer('friend_level_id')->nullable();
            });
            $this->schema($connection)->create('posts', function ($table) {
                $table->increments('id');
                $table->integer('user_id');
                $table->integer('parent_id')->nullable();
                $table->string('name');
                $table->timestamps();
            });
        }
    }

    protected function connection($connection = 'default'): Connection
    {
        return Register::getConnectionResolver()->connection($connection);
    }

    protected function schema($connection = 'default'): Builder
    {
        return $this->connection($connection)->getSchemaBuilder();
    }
}

class ModelTestUser extends Model
{
    protected ?string $table = 'users';

    protected array $casts = ['birthday' => 'datetime'];

    protected array $guarded = [];

    public function friends()
    {
        return $this->belongsToMany(self::class, 'friends', 'user_id', 'friend_id');
    }
}

class ModelTestUniqueUser extends Model
{
    protected ?string $table = 'unique_users';

    protected array $casts = ['birthday' => 'datetime'];

    protected array $guarded = [];
}

class ModelTestPost extends Model
{
    protected ?string $table = 'posts';

    protected array $guarded = [];

    public function user()
    {
        return $this->belongsTo(ModelTestUser::class, 'user_id');
    }

    public function childPosts()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parentPost()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
