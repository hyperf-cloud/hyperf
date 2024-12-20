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

use Hyperf\Codec\Json;
use Hyperf\Collection\Collection as BaseCollection;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\ModelNotFoundException;
use Hyperf\Database\Model\Register;
use Hyperf\Database\Schema\Schema;
use Hyperf\Engine\Channel;
use Hyperf\Support\Fluent;
use HyperfTest\Database\Stubs\ContainerStub;
use HyperfTest\Database\Stubs\Model\Book;
use HyperfTest\Database\Stubs\ModelStub;
use JsonSerializable;
use LogicException;
use Mockery as m;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use stdClass;

use function Hyperf\Collection\collect;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class ModelCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        $this->channel = new Channel(999);
        $dispatcher = m::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->with(m::any())->andReturnUsing(function ($event) {
            $this->channel->push($event);
        });
        $container = ContainerStub::getContainer(function ($conn) use ($dispatcher) {
            $conn->setEventDispatcher($dispatcher);
        });
        $connectionResolverInterface = $container->get(ConnectionResolverInterface::class);
        Register::setConnectionResolver($connectionResolverInterface);
        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturn($dispatcher);
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        m::close();
        Schema::dropIfExists('users');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('comments');
    }

    public function testAddingItemsToCollection()
    {
        $c = new Collection(['foo']);
        $c->add('bar')->add('baz');
        $this->assertEquals(['foo', 'bar', 'baz'], $c->all());
    }

    public function testGettingMaxItemsFromCollection()
    {
        $c = new Collection([(object) ['foo' => 10], (object) ['foo' => 20]]);
        $this->assertEquals(20, $c->max('foo'));
    }

    public function testColumnsFromCollection()
    {
        $c = new Collection([(object) ['id' => 1, 'name' => 'Laravel', 'foo' => 10], (object) ['id' => 2, 'name' => 'Lumen', 'foo' => 20]]);
        $this->assertSame([10, 20], $c->columns('foo')->toArray());
        $this->assertSame([['name' => 'Laravel'], ['name' => 'Lumen']], $c->columns(['name'])->toArray());
        $this->assertSame([['id' => 1, 'name' => 'Laravel'], ['id' => 2, 'name' => 'Lumen']], $c->columns(['id', 'name'])->toArray());
        $c1 = $c->columns(['id', 'name']);
        $this->assertInstanceOf(BaseCollection::class, $c1);
        $this->assertSame('Laravel', $c1->first()['name']);
        $this->assertSame([['id' => 1, 'key' => null], ['id' => 2, 'key' => null]], $c->columns(['id', 'key'])->toArray());

        $fluent1 = new Fluent(['id' => 1, 'name' => 'Laravel']);
        $fluent2 = new Fluent(['id' => 2, 'name' => 'Lumen']);
        $c = new Collection([(object) ['id' => 1, 'fluent' => $fluent1], (object) ['id' => 1, 'fluent' => $fluent2]]);
        $this->assertSame([['id' => 1, 'name' => 'Laravel'], ['id' => 2, 'name' => 'Lumen']], $c->columns('fluent')->toArray());
        $this->assertSame([['fluent' => ['id' => 1, 'name' => 'Laravel']], ['fluent' => ['id' => 2, 'name' => 'Lumen']]], $c->columns(['fluent'])->toArray());
    }

    public function testGettingMinItemsFromCollection()
    {
        $c = new Collection([(object) ['foo' => 10], (object) ['foo' => 20]]);
        $this->assertEquals(10, $c->min('foo'));
    }

    public function testContainsWithMultipleArguments()
    {
        $c = new Collection([['id' => 1], ['id' => 2]]);

        $this->assertTrue($c->contains('id', 1));
        $this->assertTrue($c->contains('id', '>=', 2));
        $this->assertFalse($c->contains('id', '>', 2));
    }

    public function testContainsIndicatesIfModelInArray()
    {
        $mockModel = m::mock(Model::class);
        $mockModel->shouldReceive('is')->with($mockModel)->andReturn(true);
        $mockModel->shouldReceive('is')->andReturn(false);
        $mockModel2 = m::mock(Model::class);
        $mockModel2->shouldReceive('is')->with($mockModel2)->andReturn(true);
        $mockModel2->shouldReceive('is')->andReturn(false);
        $mockModel3 = m::mock(Model::class);
        $mockModel3->shouldReceive('is')->with($mockModel3)->andReturn(true);
        $mockModel3->shouldReceive('is')->andReturn(false);
        $c = new Collection([$mockModel, $mockModel2]);

        $this->assertTrue($c->contains($mockModel));
        $this->assertTrue($c->contains($mockModel2));
        $this->assertFalse($c->contains($mockModel3));
    }

    public function testCollectionAppends()
    {
        $m1 = new ModelStub();
        $m2 = new ModelStub();

        $col = new Collection([$m1, $m2]);

        $this->assertSame([], $m1->toArray());
        $this->assertSame([[], []], $col->toArray());

        $col->append(['password']);

        $this->assertSame(['password' => '******'], $m1->toArray());
        $this->assertSame(['password' => '******'], $m2->toArray());
        $this->assertSame([['password' => '******'], ['password' => '******']], $col->toArray());
    }

    public function testContainsIndicatesIfDifferentModelInArray()
    {
        $mockModelFoo = m::namedMock('Foo', Model::class);
        $mockModelFoo->shouldReceive('is')->with($mockModelFoo)->andReturn(true);
        $mockModelFoo->shouldReceive('is')->andReturn(false);
        $mockModelBar = m::namedMock('Bar', Model::class);
        $mockModelBar->shouldReceive('is')->with($mockModelBar)->andReturn(true);
        $mockModelBar->shouldReceive('is')->andReturn(false);
        $c = new Collection([$mockModelFoo]);

        $this->assertTrue($c->contains($mockModelFoo));
        $this->assertFalse($c->contains($mockModelBar));
    }

    public function testContainsIndicatesIfKeyedModelInArray()
    {
        $mockModel = m::mock(Model::class);
        $mockModel->shouldReceive('getKey')->andReturn('1');
        $c = new Collection([$mockModel]);
        $mockModel2 = m::mock(Model::class);
        $mockModel2->shouldReceive('getKey')->andReturn('2');
        $c->add($mockModel2);

        $this->assertTrue($c->contains(1));
        $this->assertTrue($c->contains(2));
        $this->assertFalse($c->contains(3));
    }

    public function testContainsKeyAndValueIndicatesIfModelInArray()
    {
        $mockModel1 = m::mock(Model::class);
        $mockModel1->shouldReceive('offsetExists')->with('name')->andReturn(true);
        $mockModel1->shouldReceive('offsetGet')->with('name')->andReturn('Taylor');
        $mockModel2 = m::mock(Model::class);
        $mockModel2->shouldReceive('offsetExists')->andReturn(true);
        $mockModel2->shouldReceive('offsetGet')->with('name')->andReturn('Abigail');
        $c = new Collection([$mockModel1, $mockModel2]);

        $this->assertTrue($c->contains('name', 'Taylor'));
        $this->assertTrue($c->contains('name', 'Abigail'));
        $this->assertFalse($c->contains('name', 'Dayle'));
    }

    public function testContainsClosureIndicatesIfModelInArray()
    {
        $mockModel1 = m::mock(Model::class);
        $mockModel1->shouldReceive('getKey')->andReturn(1);
        $mockModel2 = m::mock(Model::class);
        $mockModel2->shouldReceive('getKey')->andReturn(2);
        $c = new Collection([$mockModel1, $mockModel2]);

        $this->assertTrue($c->contains(function ($model) {
            return $model->getKey() < 2;
        }));
        $this->assertFalse($c->contains(function ($model) {
            return $model->getKey() > 2;
        }));
    }

    public function testFindMethodFindsModelById()
    {
        $mockModel = m::mock(Model::class);
        $mockModel->shouldReceive('getKey')->andReturn(1);
        $c = new Collection([$mockModel]);

        $this->assertSame($mockModel, $c->find(1));
        $this->assertSame('taylor', $c->find(2, 'taylor'));
    }

    public function testFindMethodFindsManyModelsById()
    {
        $model1 = (new TestEloquentCollectionModel())->forceFill(['id' => 1]);
        $model2 = (new TestEloquentCollectionModel())->forceFill(['id' => 2]);
        $model3 = (new TestEloquentCollectionModel())->forceFill(['id' => 3]);

        $c = new Collection();
        $this->assertInstanceOf(Collection::class, $c->find([]));
        $this->assertCount(0, $c->find([1]));

        $c->push($model1);
        $this->assertCount(1, $c->find([1]));
        $this->assertEquals(1, $c->find([1])->first()->id);
        $this->assertCount(0, $c->find([2]));

        $c->push($model2)->push($model3);
        $this->assertCount(1, $c->find([2]));
        $this->assertEquals(2, $c->find([2])->first()->id);
        $this->assertCount(2, $c->find([2, 3, 4]));
        $this->assertCount(2, $c->find(collect([2, 3, 4])));
        $this->assertEquals([2, 3], $c->find(collect([2, 3, 4]))->pluck('id')->all());
        $this->assertEquals([2, 3], $c->find([2, 3, 4])->pluck('id')->all());
    }

    public function testFindOrFailFindsModelById()
    {
        $mockModel = m::mock(Model::class);
        $mockModel->shouldReceive('getKey')->andReturn(1);
        $c = new Collection([$mockModel]);

        $this->assertSame($mockModel, $c->findOrFail(1));
    }

    public function testFindOrFailFindsManyModelsById()
    {
        $model1 = (new TestUserModel())->forceFill(['id' => 1]);
        $model2 = (new TestUserModel())->forceFill(['id' => 2]);

        $c = new Collection();
        $this->assertInstanceOf(Collection::class, $c->findOrFail([]));
        $this->assertCount(0, $c->findOrFail([]));

        $c->push($model1);
        $this->assertCount(1, $c->findOrFail([1]));
        $this->assertEquals(1, $c->findOrFail([1])->first()->id);

        $c->push($model2);
        $this->assertCount(2, $c->findOrFail([1, 2]));

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [HyperfTest\Database\TestUserModel] 3');

        $c->findOrFail([1, 2, 3]);
    }

    public function testFindOrFailThrowsExceptionWithMessageWhenOtherModelsArePresent()
    {
        $model = (new TestUserModel())->forceFill(['id' => 1]);

        $c = new Collection([$model]);

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model [HyperfTest\Database\TestUserModel] 2');

        $c->findOrFail(2);
    }

    public function testFindOrFailThrowsExceptionWithoutMessageWhenOtherModelsAreNotPresent()
    {
        $c = new Collection();

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('');

        $c->findOrFail(1);
    }

    public function testLoadMethodEagerLoadsGivenRelationships()
    {
        $c = $this->getMockBuilder(Collection::class)->onlyMethods(['first'])->setConstructorArgs([['foo']])->getMock();
        $mockItem = m::mock(stdClass::class);
        $c->expects($this->once())->method('first')->willReturn($mockItem);
        $mockItem->shouldReceive('newQueryWithoutRelationships')->once()->andReturn($mockItem);
        $mockItem->shouldReceive('with')->with(['bar', 'baz'])->andReturn($mockItem);
        $mockItem->shouldReceive('eagerLoadRelations')->once()->with(['foo'])->andReturn(['results']);
        $c->load('bar', 'baz');

        $this->assertEquals(['results'], $c->all());
    }

    public function testCollectionDictionaryReturnsModelKeys()
    {
        $one = m::mock(Model::class);
        $one->shouldReceive('getKey')->andReturn(1);

        $two = m::mock(Model::class);
        $two->shouldReceive('getKey')->andReturn(2);

        $three = m::mock(Model::class);
        $three->shouldReceive('getKey')->andReturn(3);

        $c = new Collection([$one, $two, $three]);

        $this->assertEquals([1, 2, 3], $c->modelKeys());
    }

    public function testCollectionMergesWithGivenCollection()
    {
        $one = m::mock(Model::class);
        $one->shouldReceive('getKey')->andReturn(1);

        $two = m::mock(Model::class);
        $two->shouldReceive('getKey')->andReturn(2);

        $three = m::mock(Model::class);
        $three->shouldReceive('getKey')->andReturn(3);

        $c1 = new Collection([$one, $two]);
        $c2 = new Collection([$two, $three]);

        $this->assertEquals(new Collection([$one, $two, $three]), $c1->merge($c2));
    }

    public function testMap()
    {
        $one = m::mock(Model::class);
        $two = m::mock(Model::class);

        $c = new Collection([$one, $two]);

        $cAfterMap = $c->map(function ($item) {
            return $item;
        });

        $this->assertEquals($c->all(), $cAfterMap->all());
        $this->assertInstanceOf(Collection::class, $cAfterMap);
    }

    public function testMappingToNonModelsReturnsABaseCollection()
    {
        $one = m::mock(Model::class);
        $two = m::mock(Model::class);

        $c = (new Collection([$one, $two]))->map(function ($item) {
            return 'not-a-model';
        });

        $this->assertInstanceOf(BaseCollection::class, $c);
    }

    public function testMapWithKeys()
    {
        $one = m::mock(Model::class);
        $two = m::mock(Model::class);

        $c = new Collection([$one, $two]);

        $key = 0;
        $cAfterMap = $c->mapWithKeys(function ($item) use (&$key) {
            return [$key++ => $item];
        });

        $this->assertEquals($c->all(), $cAfterMap->all());
        $this->assertInstanceOf(Collection::class, $cAfterMap);
    }

    public function testCollectionDiffsWithGivenCollection()
    {
        $one = m::mock(Model::class);
        $one->shouldReceive('getKey')->andReturn(1);

        $two = m::mock(Model::class);
        $two->shouldReceive('getKey')->andReturn(2);

        $three = m::mock(Model::class);
        $three->shouldReceive('getKey')->andReturn(3);

        $c1 = new Collection([$one, $two]);
        $c2 = new Collection([$two, $three]);

        $this->assertEquals(new Collection([$one]), $c1->diff($c2));
    }

    // public function testCollectionIntersectWithNull()
    // {
    //     $one = m::mock(Model::class);
    //     $one->shouldReceive('getKey')->andReturn(1);
    //
    //     $two = m::mock(Model::class);
    //     $two->shouldReceive('getKey')->andReturn(2);
    //
    //     $three = m::mock(Model::class);
    //     $three->shouldReceive('getKey')->andReturn(3);
    //
    //     $c1 = new Collection([$one, $two, $three]);
    //
    //     $this->assertEquals([], $c1->intersect(null)->all());
    // }

    public function testCollectionIntersectsWithGivenCollection()
    {
        $one = m::mock(Model::class);
        $one->shouldReceive('getKey')->andReturn(1);

        $two = m::mock(Model::class);
        $two->shouldReceive('getKey')->andReturn(2);

        $three = m::mock(Model::class);
        $three->shouldReceive('getKey')->andReturn(3);

        $c1 = new Collection([$one, $two]);
        $c2 = new Collection([$two, $three]);

        $this->assertEquals(new Collection([$two]), $c1->intersect($c2));
    }

    public function testCollectionReturnsUniqueItems()
    {
        $one = m::mock(Model::class);
        $one->shouldReceive('getKey')->andReturn(1);

        $two = m::mock(Model::class);
        $two->shouldReceive('getKey')->andReturn(2);

        $c = new Collection([$one, $two, $two]);

        $this->assertEquals(new Collection([$one, $two]), $c->unique());
    }

    public function testCollectionReturnsUniqueStrictBasedOnKeysOnly()
    {
        $one = new TestEloquentCollectionModel();
        $two = new TestEloquentCollectionModel();
        $three = new TestEloquentCollectionModel();
        $four = new TestEloquentCollectionModel();
        $one->id = 1;
        $one->someAttribute = '1';
        $two->id = 1;
        $two->someAttribute = '2';
        $three->id = 1;
        $three->someAttribute = '3';
        $four->id = 2;
        $four->someAttribute = '4';

        $uniques = Collection::make([$one, $two, $three, $four])->unique()->all();
        $this->assertSame([$three, $four], $uniques);

        $uniques = Collection::make([$one, $two, $three, $four])->unique(null, true)->all();
        $this->assertSame([$three, $four], $uniques);
    }

    public function testOnlyReturnsCollectionWithGivenModelKeys()
    {
        $one = m::mock(Model::class);
        $one->shouldReceive('getKey')->andReturn(1);

        $two = m::mock(Model::class);
        $two->shouldReceive('getKey')->andReturn(2);

        $three = m::mock(Model::class);
        $three->shouldReceive('getKey')->andReturn(3);

        $c = new Collection([$one, $two, $three]);

        $this->assertEquals($c, $c->only(null));
        $this->assertEquals(new Collection([$one]), $c->only(1));
        $this->assertEquals(new Collection([$two, $three]), $c->only([2, 3]));
    }

    public function testExceptReturnsCollectionWithoutGivenModelKeys()
    {
        $one = m::mock(Model::class);
        $one->shouldReceive('getKey')->andReturn(1);

        $two = m::mock(Model::class);
        $two->shouldReceive('getKey')->andReturn('2');

        $three = m::mock(Model::class);
        $three->shouldReceive('getKey')->andReturn(3);

        $c = new Collection([$one, $two, $three]);

        $this->assertEquals(new Collection([$one, $three]), $c->except(2));
        $this->assertEquals(new Collection([$one]), $c->except([2, 3]));
    }

    public function testMakeHiddenAddsHiddenOnEntireCollection()
    {
        $c = new Collection([new TestEloquentCollectionModel()]);
        $c = $c->makeHidden(['visible']);

        $this->assertEquals(['hidden', 'visible'], $c[0]->getHidden());
    }

    public function testSetVisibleReplacesVisibleOnEntireCollection()
    {
        $c = new Collection([new TestEloquentCollectionModel()]);
        $c = $c->setVisible(['hidden']);

        $this->assertEquals(['hidden'], $c[0]->getVisible());
    }

    public function testSetHiddenReplacesHiddenOnEntireCollection()
    {
        $c = new Collection([new TestEloquentCollectionModel()]);
        $c = $c->setHidden(['visible']);

        $this->assertEquals(['visible'], $c[0]->getHidden());
    }

    public function testMakeVisibleRemovesHiddenFromEntireCollection()
    {
        $c = new Collection([new TestEloquentCollectionModel()]);
        $c = $c->makeVisible(['hidden']);

        $this->assertEquals([], $c[0]->getHidden());
    }

    public function testNonModelRelatedMethods()
    {
        $a = new Collection([['foo' => 'bar'], ['foo' => 'baz']]);
        $b = new Collection(['a', 'b', 'c']);
        $this->assertInstanceOf(BaseCollection::class, $a->pluck('foo'));
        $this->assertInstanceOf(BaseCollection::class, $a->keys());
        $this->assertInstanceOf(BaseCollection::class, $a->collapse());
        $this->assertInstanceOf(BaseCollection::class, $a->flatten());
        $this->assertInstanceOf(BaseCollection::class, $a->zip(['a', 'b'], ['c', 'd']));
        $this->assertInstanceOf(BaseCollection::class, $b->flip());
    }

    public function testMakeVisibleRemovesHiddenAndIncludesVisible()
    {
        $c = new Collection([new TestEloquentCollectionModel()]);
        $c = $c->makeVisible('hidden');

        $this->assertEquals([], $c[0]->getHidden());
        $this->assertEquals(['visible', 'hidden'], $c[0]->getVisible());
    }

    public function testEmptyCollectionStayEmptyOnFresh()
    {
        $c = new Collection();
        $this->assertEquals($c, $c->fresh());
    }

    public function testConvertingEmptyCollectionToQueryThrowsException()
    {
        $this->expectException(LogicException::class);

        $c = new Collection();
        $c->toQuery();
    }

    public function testCollectionMapInto()
    {
        $container = ContainerStub::getContainer();
        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturnNull();

        $arr = [new Book(['id' => 1]), new Book(['id' => 2]), new Book(['id' => 3])];
        $collection = new Collection($arr);
        $col = $collection->mapInto(TestBookModelSchema::class);
        $this->assertSame('[{"no":1},{"no":2},{"no":3}]', Json::encode($col));
    }

    protected function createSchema()
    {
        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('email')->unique();
        });

        Schema::create('articles', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
        });

        Schema::create('comments', function ($table) {
            $table->increments('id');
            $table->integer('article_id');
            $table->string('content');
        });
    }

    /**
     * Helpers...
     */
    protected function seedData()
    {
        $user = TestUserModel::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        TestArticleModel::query()->insert([
            ['user_id' => 1, 'title' => 'Another title'],
            ['user_id' => 1, 'title' => 'Another title'],
            ['user_id' => 1, 'title' => 'Another title'],
        ]);

        TestCommentModel::query()->insert([
            ['article_id' => 1, 'content' => 'Another comment'],
            ['article_id' => 2, 'content' => 'Another comment'],
        ]);
    }
}

class TestBookModelSchema implements JsonSerializable
{
    public function __construct(public Book $book)
    {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'no' => $this->book->id,
        ];
    }
}

class TestEloquentCollectionModel extends Model
{
    protected array $visible = ['visible'];

    protected array $hidden = ['hidden'];

    public function getTestAttribute()
    {
        return 'test';
    }
}

class TestUserModel extends Model
{
    public bool $timestamps = false;

    protected ?string $table = 'users';

    protected array $guarded = [];

    public function articles()
    {
        return $this->hasMany(TestArticleModel::class, 'user_id');
    }
}

class TestArticleModel extends Model
{
    public bool $timestamps = false;

    protected ?string $table = 'articles';

    protected array $guarded = [];

    public function comments()
    {
        return $this->hasMany(TestCommentModel::class, 'article_id');
    }
}
class TestCommentModel extends Model
{
    public bool $timestamps = false;

    protected ?string $table = 'comments';

    protected array $guarded = [];
}
