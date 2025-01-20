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
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Hyperf\Collection\Collection as BaseCollection;
use Hyperf\Database\Connection;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\ConnectionResolver;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Exception\MultipleRecordsFoundException;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\ModelNotFoundException;
use Hyperf\Database\Model\Register;
use Hyperf\Database\Model\RelationNotFoundException;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\Database\Query\Builder as BaseBuilder;
use Hyperf\Database\Query\Grammars\Grammar;
use Hyperf\Database\Query\Processors\Processor;
use HyperfTest\Database\Stubs\ModelStub;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

use function Hyperf\Collection\collect;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class ModelBuilderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        Register::setConnectionResolver(new ConnectionResolver(['default' => new Connection(Mockery::mock(PDO::class))]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testFindMethod()
    {
        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($this->getMockModel());
        $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
        $builder->shouldReceive('first')->with(['column'])->andReturn('baz');

        $result = $builder->find('bar', ['column']);
        $this->assertEquals('baz', $result);
    }

    public function testFindOrNewMethodModelFound()
    {
        $model = $this->getMockModel();
        $model->shouldReceive('findOrNew')->once()->andReturn('baz');

        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($model);
        $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
        $builder->shouldReceive('first')->with(['column'])->andReturn('baz');

        $expected = $model->findOrNew('bar', ['column']);
        $result = $builder->find('bar', ['column']);
        $this->assertEquals($expected, $result);
    }

    public function testFindOrMethod()
    {
        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $model = $this->getMockModel();
        $model->shouldReceive('getKeyType')->andReturn('int');
        $builder->setModel($model);
        $builder->getQuery()->shouldReceive('where')->with('foo_table.foo', '=', 1)->twice();
        $builder->getQuery()->shouldReceive('where')->with('foo_table.foo', '=', 2)->once();
        $builder->shouldReceive('first')->andReturn($model)->once();
        $builder->shouldReceive('first')->with(['column'])->andReturn($model)->once();
        $builder->shouldReceive('first')->andReturn(null)->once();

        $this->assertSame($model, $builder->findOr(1, fn () => 'callback result'));
        $this->assertSame($model, $builder->findOr(1, ['column'], fn () => 'callback result'));
        $this->assertSame('callback result', $builder->findOr(2, fn () => 'callback result'));
    }

    public function testFindOrMethodWithMany()
    {
        $builder = Mockery::mock(Builder::class . '[get]', [$this->getMockQueryBuilder()]);
        $model1 = $this->getMockModel();
        $model2 = $this->getMockModel();
        $model1->shouldReceive('getKeyType')->andReturn('int');
        $model2->shouldReceive('getKeyType')->andReturn('int');
        $builder->setModel($model1);
        $builder->getQuery()->shouldReceive('whereIn')->with('foo_table.foo', [1, 2])->twice();
        $builder->getQuery()->shouldReceive('whereIn')->with('foo_table.foo', [1, 2, 3])->once();
        $builder->shouldReceive('get')->andReturn(new Collection([$model1, $model2]))->once();
        $builder->shouldReceive('get')->with(['column'])->andReturn(new Collection([$model1, $model2]))->once();
        $builder->shouldReceive('get')->andReturn(null)->once();

        $result = $builder->findOr([1, 2], fn () => 'callback result');
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame($model1, $result[0]);
        $this->assertSame($model2, $result[1]);

        $result = $builder->findOr([1, 2], ['column'], fn () => 'callback result');
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame($model1, $result[0]);
        $this->assertSame($model2, $result[1]);

        $result = $builder->findOr([1, 2, 3], fn () => 'callback result');
        $this->assertSame('callback result', $result);
    }

    public function testFindOrMethodWithManyUsingCollection()
    {
        $builder = Mockery::mock(Builder::class . '[get]', [$this->getMockQueryBuilder()]);
        $model1 = $this->getMockModel();
        $model2 = $this->getMockModel();
        $model1->shouldReceive('getKeyType')->andReturn('int');
        $model2->shouldReceive('getKeyType')->andReturn('int');
        $builder->setModel($model1);
        $arg1 = Collection::make([1, 2]);
        $arg2 = Collection::make([1, 2, 3]);
        $builder->getQuery()->shouldReceive('whereIn')->with('foo_table.foo', $arg1)->twice();
        $builder->getQuery()->shouldReceive('whereIn')->with('foo_table.foo', $arg2)->once();
        $builder->shouldReceive('get')->andReturn(new Collection([$model1, $model2]))->once();
        $builder->shouldReceive('get')->with(['column'])->andReturn(new Collection([$model1, $model2]))->once();
        $builder->shouldReceive('get')->andReturn(null)->once();

        $result = $builder->findOr($arg1, fn () => 'callback result');
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame($model1, $result[0]);
        $this->assertSame($model2, $result[1]);

        $result = $builder->findOr($arg1, ['column'], fn () => 'callback result');
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame($model1, $result[0]);
        $this->assertSame($model2, $result[1]);

        $result = $builder->findOr($arg2, fn () => 'callback result');
        $this->assertSame('callback result', $result);
    }

    public function testFindOrNewMethodModelNotFound()
    {
        $model = $this->getMockModel();
        $model->shouldReceive('findOrNew')->once()->andReturn(Mockery::mock(Model::class));

        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($model);
        $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
        $builder->shouldReceive('first')->with(['column'])->andReturn(null);

        $result = $model->findOrNew('bar', ['column']);
        $findResult = $builder->find('bar', ['column']);
        $this->assertNull($findResult);
        $this->assertInstanceOf(Model::class, $result);
    }

    public function testFindOrFailMethodThrowsModelNotFoundException()
    {
        $this->expectException(ModelNotFoundException::class);

        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($this->getMockModel());
        $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
        $builder->shouldReceive('first')->with(['column'])->andReturn(null);
        $builder->findOrFail('bar', ['column']);
    }

    public function testFindOrFailMethodWithManyThrowsModelNotFoundException()
    {
        $this->expectException(ModelNotFoundException::class);

        $builder = Mockery::mock(Builder::class . '[get]', [$this->getMockQueryBuilder()]);
        $builder->setModel($this->getMockModel());
        $builder->getQuery()->shouldReceive('whereIn')->once()->with('foo_table.foo', [1, 2]);
        $builder->shouldReceive('get')->with(['column'])->andReturn(new Collection([1]));
        $builder->findOrFail([1, 2], ['column']);
    }

    public function testFirstOrFailMethodThrowsModelNotFoundException()
    {
        $this->expectException(ModelNotFoundException::class);

        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $builder->setModel($this->getMockModel());
        $builder->shouldReceive('first')->with(['column'])->andReturn(null);
        $builder->firstOrFail(['column']);
    }

    public function testFindWithMany()
    {
        $builder = Mockery::mock(Builder::class . '[get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->shouldReceive('whereIn')->once()->with('foo_table.foo', [1, 2]);
        $builder->setModel($this->getMockModel());
        $builder->shouldReceive('get')->with(['column'])->andReturn('baz');

        $result = $builder->find([1, 2], ['column']);
        $this->assertEquals('baz', $result);
    }

    public function testFindWithManyUsingCollection()
    {
        $ids = collect([1, 2]);
        $builder = Mockery::mock(Builder::class . '[get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->shouldReceive('whereIn')->once()->with('foo_table.foo', $ids);
        $builder->setModel($this->getMockModel());
        $builder->shouldReceive('get')->with(['column'])->andReturn('baz');

        $result = $builder->find($ids, ['column']);
        $this->assertEquals('baz', $result);
    }

    public function testFirstMethod()
    {
        $builder = Mockery::mock(Builder::class . '[get,take]', [$this->getMockQueryBuilder()]);
        $builder->shouldReceive('take')->with(1)->andReturnSelf();
        $builder->shouldReceive('get')->with(['*'])->andReturn(new Collection(['bar']));

        $result = $builder->first();
        $this->assertEquals('bar', $result);
    }

    public function testQualifyColumn()
    {
        $builder = new Builder(Mockery::mock(BaseBuilder::class));
        $builder->shouldReceive('from')->with('stub');

        $builder->setModel(new ModelStub());

        $this->assertEquals('stub.column', $builder->qualifyColumn('column'));
    }

    public function testGetMethodLoadsModelsAndHydratesEagerRelations()
    {
        $builder = Mockery::mock(Builder::class . '[getModels,eagerLoadRelations]', [$this->getMockQueryBuilder()]);
        $builder->shouldReceive('applyScopes')->andReturnSelf();
        $builder->shouldReceive('getModels')->with(['foo'])->andReturn(['bar']);
        $builder->shouldReceive('eagerLoadRelations')->with(['bar'])->andReturn(['bar', 'baz']);
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('newCollection')->with(['bar', 'baz'])->andReturn(new Collection(['bar', 'baz']));

        $results = $builder->get(['foo']);
        $this->assertEquals(['bar', 'baz'], $results->all());
    }

    public function testGetMethodDoesntHydrateEagerRelationsWhenNoResultsAreReturned()
    {
        $builder = Mockery::mock(Builder::class . '[getModels,eagerLoadRelations]', [$this->getMockQueryBuilder()]);
        $builder->shouldReceive('applyScopes')->andReturnSelf();
        $builder->shouldReceive('getModels')->with(['foo'])->andReturn([]);
        $builder->shouldReceive('eagerLoadRelations')->never();
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('newCollection')->with([])->andReturn(new Collection([]));

        $results = $builder->get(['foo']);
        $this->assertEquals([], $results->all());
    }

    public function testValueMethodWithModelFound()
    {
        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $mockModel = new stdClass();
        $mockModel->name = 'foo';
        $builder->shouldReceive('first')->with(['name'])->andReturn($mockModel);

        $this->assertEquals('foo', $builder->value('name'));
    }

    public function testValueMethodWithModelNotFound()
    {
        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $builder->shouldReceive('first')->with(['name'])->andReturn(null);

        $this->assertNull($builder->value('name'));
    }

    public function testChunkWithLastChunkComplete()
    {
        $builder = Mockery::mock(Builder::class . '[forPage,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = new Collection(['foo1', 'foo2']);
        $chunk2 = new Collection(['foo3', 'foo4']);
        $chunk3 = new Collection([]);
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
    }

    public function testChunkWithLastChunkPartial()
    {
        $builder = Mockery::mock(Builder::class . '[forPage,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = new Collection(['foo1', 'foo2']);
        $chunk2 = new Collection(['foo3']);
        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunk(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkCanBeStoppedByReturningFalse()
    {
        $builder = Mockery::mock(Builder::class . '[forPage,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = new Collection(['foo1', 'foo2']);
        $chunk2 = new Collection(['foo3']);
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
    }

    public function testChunkWithCountZero()
    {
        $builder = Mockery::mock(Builder::class . '[forPage,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk = new Collection([]);
        $builder->shouldReceive('forPage')->once()->with(1, 0)->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunk(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        });
    }

    public function testChunkPaginatesUsingIdWithLastChunkComplete()
    {
        $builder = Mockery::mock(Builder::class . '[forPageAfterId,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = new Collection([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
        $chunk3 = new Collection([]);
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
    }

    public function testChunkPaginatesUsingIdWithLastChunkPartial()
    {
        $builder = Mockery::mock(Builder::class . '[forPageAfterId,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = new Collection([(object) ['someIdField' => 10]]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
        $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

        $builder->chunkById(2, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testChunkPaginatesUsingIdWithCountZero()
    {
        $builder = Mockery::mock(Builder::class . '[forPageAfterId,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk = new Collection([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(0, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(1)->andReturn($chunk);

        $callbackAssertor = Mockery::mock(stdClass::class);
        $callbackAssertor->shouldReceive('doSomething')->never();

        $builder->chunkById(0, function ($results) use ($callbackAssertor) {
            $callbackAssertor->doSomething($results);
        }, 'someIdField');
    }

    public function testPluckReturnsTheMutatedAttributesOfAModel()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('pluck')->with('name', '')->andReturn(new BaseCollection(['bar', 'baz']));
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('hasGetMutator')->with('name')->andReturn(true);
        $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'bar'])->andReturn(new ModelBuilderTestPluckStub(['name' => 'bar']));
        $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'baz'])->andReturn(new ModelBuilderTestPluckStub(['name' => 'baz']));

        $this->assertEquals(['foo_bar', 'foo_baz'], $builder->pluck('name')->all());
    }

    public function testPluckReturnsTheCastedAttributesOfAModel()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('pluck')->with('name', '')->andReturn(new BaseCollection(['bar', 'baz']));
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('hasGetMutator')->with('name')->andReturn(false);
        $builder->getModel()->shouldReceive('hasCast')->with('name')->andReturn(true);
        $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'bar'])->andReturn(new ModelBuilderTestPluckStub(['name' => 'bar']));
        $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'baz'])->andReturn(new ModelBuilderTestPluckStub(['name' => 'baz']));

        $this->assertEquals(['foo_bar', 'foo_baz'], $builder->pluck('name')->all());
    }

    public function testPluckReturnsTheDateAttributesOfAModel()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('pluck')->with('created_at', '')->andReturn(new BaseCollection(['2010-01-01 00:00:00', '2011-01-01 00:00:00']));
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('hasGetMutator')->with('created_at')->andReturn(false);
        $builder->getModel()->shouldReceive('hasCast')->with('created_at')->andReturn(false);
        $builder->getModel()->shouldReceive('getDates')->andReturn(['created_at']);
        $builder->getModel()->shouldReceive('newFromBuilder')->with(['created_at' => '2010-01-01 00:00:00'])->andReturn(new ModelBuilderTestPluckDatesStub(['created_at' => '2010-01-01 00:00:00']));
        $builder->getModel()->shouldReceive('newFromBuilder')->with(['created_at' => '2011-01-01 00:00:00'])->andReturn(new ModelBuilderTestPluckDatesStub(['created_at' => '2011-01-01 00:00:00']));

        [$date1, $date2] = $builder->pluck('created_at')->all();
        $this->assertEquals(['2010-01-01 00:00:00', '2011-01-01 00:00:00'], [$date1->toDateTimeString(), $date2->toDateTimeString()]);
    }

    public function testPluckWithoutModelGetterJustReturnsTheAttributesFoundInDatabase()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('pluck')->with('name', '')->andReturn(new BaseCollection(['bar', 'baz']));
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('hasGetMutator')->with('name')->andReturn(false);
        $builder->getModel()->shouldReceive('hasCast')->with('name')->andReturn(false);
        $builder->getModel()->shouldReceive('getDates')->andReturn(['created_at']);

        $this->assertEquals(['bar', 'baz'], $builder->pluck('name')->all());
    }

    public function testLocalMacrosAreCalledOnBuilder()
    {
        unset($_SERVER['__test.builder']);
        $builder = new Builder(new BaseBuilder(
            Mockery::mock(ConnectionInterface::class),
            Mockery::mock(Grammar::class),
            Mockery::mock(Processor::class)
        ));
        $builder->macro('fooBar', function ($builder) {
            $_SERVER['__test.builder'] = $builder;

            return $builder;
        });
        $result = $builder->fooBar();

        $this->assertEquals($builder, $result);
        $this->assertEquals($builder, $_SERVER['__test.builder']);
        unset($_SERVER['__test.builder']);
    }

    public function testGlobalMacrosAreCalledOnBuilder()
    {
        Builder::macro('foo', function ($bar) {
            return $bar;
        });

        Builder::macro('bam', [Builder::class, 'getQuery']);

        $builder = $this->getBuilder();

        $this->assertEquals($builder->foo('bar'), 'bar');
        $this->assertEquals($builder->bam(), $builder->getQuery());
    }

    public function testMissingStaticMacrosThrowsProperException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method Hyperf\Database\Model\Builder::missingMacro()');
        Builder::missingMacro();
    }

    public function testGetModelsProperlyHydratesModels()
    {
        $builder = Mockery::mock(Builder::class . '[get]', [$this->getMockQueryBuilder()]);
        $records[] = ['name' => 'taylor', 'age' => 26];
        $records[] = ['name' => 'dayle', 'age' => 28];
        $builder->getQuery()->shouldReceive('get')->once()->with(['foo'])->andReturn(new BaseCollection($records));
        $model = Mockery::mock(Model::class . '[getTable,hydrate]');
        $model->shouldReceive('getTable')->once()->andReturn('foo_table');
        $builder->setModel($model);
        $model->shouldReceive('hydrate')->once()->with($records)->andReturn(new Collection(['hydrated']));
        $models = $builder->getModels(['foo']);

        $this->assertEquals($models, ['hydrated']);
    }

    public function testEagerLoadRelationsLoadTopLevelRelationships()
    {
        $builder = Mockery::mock(Builder::class . '[eagerLoadRelation]', [$this->getMockQueryBuilder()]);
        $nop1 = function () {
        };
        $nop2 = function () {
        };
        $builder->setEagerLoads(['foo' => $nop1, 'foo.bar' => $nop2]);
        $builder->shouldAllowMockingProtectedMethods()->shouldReceive('eagerLoadRelation')->with(['models'], 'foo', $nop1)->andReturn(['foo']);

        $results = $builder->eagerLoadRelations(['models']);
        $this->assertEquals(['foo'], $results);
    }

    public function testRelationshipEagerLoadProcess()
    {
        $builder = Mockery::mock(Builder::class . '[getRelation]', [$this->getMockQueryBuilder()]);
        $builder->setEagerLoads(['orders' => function ($query) {
            $_SERVER['__eloquent.constrain'] = $query;
        }]);
        $relation = Mockery::mock(stdClass::class);
        $relation->shouldReceive('addEagerConstraints')->once()->with(['models']);
        $relation->shouldReceive('initRelation')->once()->with(['models'], 'orders')->andReturn(['models']);
        $relation->shouldReceive('getEager')->once()->andReturn(['results']);
        $relation->shouldReceive('match')->once()->with(['models'], ['results'], 'orders')->andReturn(['models.matched']);
        $builder->shouldReceive('getRelation')->once()->with('orders')->andReturn($relation);
        $results = $builder->eagerLoadRelations(['models']);

        $this->assertEquals(['models.matched'], $results);
        $this->assertEquals($relation, $_SERVER['__eloquent.constrain']);
        unset($_SERVER['__eloquent.constrain']);
    }

    public function testGetRelationProperlySetsNestedRelationships()
    {
        $builder = $this->getBuilder();
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('newInstance->orders')->once()->andReturn($relation = Mockery::mock(stdClass::class));
        $relationQuery = Mockery::mock(stdClass::class);
        $relation->shouldReceive('getQuery')->andReturn($relationQuery);
        $relationQuery->shouldReceive('with')->once()->with(['lines' => null, 'lines.details' => null]);
        $builder->setEagerLoads(['orders' => null, 'orders.lines' => null, 'orders.lines.details' => null]);

        $builder->getRelation('orders');
    }

    public function testGetRelationProperlySetsNestedRelationshipsWithSimilarNames()
    {
        $builder = $this->getBuilder();
        $builder->setModel($this->getMockModel());
        $builder->getModel()->shouldReceive('newInstance->orders')->once()->andReturn($relation = Mockery::mock(stdClass::class));
        $builder->getModel()->shouldReceive('newInstance->ordersGroups')->once()->andReturn($groupsRelation = Mockery::mock(stdClass::class));

        $relationQuery = Mockery::mock(stdClass::class);
        $relation->shouldReceive('getQuery')->andReturn($relationQuery);

        $groupRelationQuery = Mockery::mock(stdClass::class);
        $groupsRelation->shouldReceive('getQuery')->andReturn($groupRelationQuery);
        $groupRelationQuery->shouldReceive('with')->once()->with(['lines' => null, 'lines.details' => null]);

        $builder->setEagerLoads(['orders' => null, 'ordersGroups' => null, 'ordersGroups.lines' => null, 'ordersGroups.lines.details' => null]);

        $builder->getRelation('orders');
        $builder->getRelation('ordersGroups');
    }

    public function testGetRelationThrowsException()
    {
        $this->expectException(RelationNotFoundException::class);

        $builder = $this->getBuilder();
        $builder->setModel($this->getMockModel());

        $builder->getRelation('invalid');
    }

    public function testEagerLoadParsingSetsProperRelationships()
    {
        $builder = $this->getBuilder();
        $builder->with(['orders', 'orders.lines']);
        $eagers = $builder->getEagerLoads();

        $this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
        $this->assertInstanceOf(Closure::class, $eagers['orders']);
        $this->assertInstanceOf(Closure::class, $eagers['orders.lines']);

        $builder = $this->getBuilder();
        $builder->with('orders', 'orders.lines');
        $eagers = $builder->getEagerLoads();

        $this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
        $this->assertInstanceOf(Closure::class, $eagers['orders']);
        $this->assertInstanceOf(Closure::class, $eagers['orders.lines']);

        $builder = $this->getBuilder();
        $builder->with(['orders.lines']);
        $eagers = $builder->getEagerLoads();

        $this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
        $this->assertInstanceOf(Closure::class, $eagers['orders']);
        $this->assertInstanceOf(Closure::class, $eagers['orders.lines']);

        $builder = $this->getBuilder();
        $builder->with(['orders' => function () {
            return 'foo';
        }]);
        $eagers = $builder->getEagerLoads();

        $this->assertEquals('foo', $eagers['orders']());

        $builder = $this->getBuilder();
        $builder->with(['orders.lines' => function () {
            return 'foo';
        }]);
        $eagers = $builder->getEagerLoads();

        $this->assertInstanceOf(Closure::class, $eagers['orders']);
        $this->assertNull($eagers['orders']());
        $this->assertEquals('foo', $eagers['orders.lines']());
    }

    public function testQueryPassThru()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('foobar')->once()->andReturn('foo');

        $this->assertInstanceOf(Builder::class, $builder->foobar());

        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('insertOrIgnoreUsing')->once()->with(['bar'], 'baz')->andReturn(1);
        $this->assertSame(1, $builder->insertOrIgnoreUsing(['bar'], 'baz'));

        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('insert')->once()->with(['bar'])->andReturn('foo');
        $this->assertEquals('foo', $builder->insert(['bar']));
    }

    public function testQueryScopes()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('from');
        $builder->getQuery()->shouldReceive('where')->once()->with('foo', 'bar');
        $builder->setModel($model = new ModelBuilderTestScopeStub());
        $result = $builder->approved();

        $this->assertEquals($builder, $result);
    }

    public function testNestedWhere()
    {
        $nestedQuery = Mockery::mock(Builder::class);
        $nestedRawQuery = $this->getMockQueryBuilder();
        $nestedQuery->shouldReceive('getQuery')->once()->andReturn($nestedRawQuery);
        $model = $this->getMockModel()->makePartial();
        $model->shouldReceive('newModelQuery')->once()->andReturn($nestedQuery);
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('from');
        $builder->setModel($model);
        $builder->getQuery()->shouldReceive('addNestedWhereQuery')->once()->with($nestedRawQuery, 'and');
        $nestedQuery->shouldReceive('foo')->once();

        $result = $builder->where(function ($query) {
            $query->foo();
        });
        $this->assertEquals($builder, $result);
    }

    public function testRealNestedWhereWithScopes()
    {
        $model = new ModelBuilderTestNestedStub();
        $this->mockConnectionForModel($model, 'MySql');
        $query = $model->newQuery()->where('foo', '=', 'bar')->where(function ($query) {
            $query->where('baz', '>', 9000);
        });
        $this->assertEquals('select * from `table` where `foo` = ? and (`baz` > ?) and `table`.`deleted_at` is null', $query->toSql());
        $this->assertEquals(['bar', 9000], $query->getBindings());
    }

    public function testRealNestedWhereWithMultipleScopesAndOneDeadScope()
    {
        $model = new ModelBuilderTestNestedStub();
        $this->mockConnectionForModel($model, 'MySql');
        $query = $model->newQuery()->empty()->where('foo', '=', 'bar')->empty()->where(function ($query) {
            $query->empty()->where('baz', '>', 9000);
        });
        $this->assertEquals('select * from `table` where `foo` = ? and (`baz` > ?) and `table`.`deleted_at` is null', $query->toSql());
        $this->assertEquals(['bar', 9000], $query->getBindings());
    }

    public function testSimpleWhere()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('where')->once()->with('foo', '=', 'bar');
        $result = $builder->where('foo', '=', 'bar');
        $this->assertEquals($result, $builder);
    }

    public function testPostgresOperatorsWhere()
    {
        $builder = $this->getBuilder();
        $builder->getQuery()->shouldReceive('where')->once()->with('foo', '@>', 'bar');
        $result = $builder->where('foo', '@>', 'bar');
        $this->assertEquals($result, $builder);
    }

    public function testDeleteOverride()
    {
        $builder = $this->getBuilder();
        $builder->onDelete(function ($builder) {
            return ['foo' => $builder];
        });
        $this->assertEquals(['foo' => $builder], $builder->delete());
    }

    public function testWithCount()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->withCount('foo');

        $this->assertEquals('select "model_builder_test_model_parent_stubs".*, (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id") as "foo_count" from "model_builder_test_model_parent_stubs"', $builder->toSql());
    }

    public function testWithMax()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->withMax('foo', 'id');

        $this->assertEquals('select "model_builder_test_model_parent_stubs".*, (select max("model_builder_test_model_close_related_stubs"."id") from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id") as "foo_max_id" from "model_builder_test_model_parent_stubs"', $builder->toSql());
    }

    public function testWithCountAndSelect()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->select('id')->withCount('foo');

        $this->assertEquals('select "id", (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id") as "foo_count" from "model_builder_test_model_parent_stubs"', $builder->toSql());
    }

    public function testWithCountAndMergedWheres()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->select('id')->withCount(['activeFoo' => function ($q) {
            $q->where('bam', '>', 'qux');
        }]);

        $this->assertEquals('select "id", (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" and "bam" > ? and "active" = ?) as "active_foo_count" from "model_builder_test_model_parent_stubs"', $builder->toSql());
        $this->assertEquals(['qux', true], $builder->getBindings());
    }

    public function testWithCountAndGlobalScope()
    {
        $model = new ModelBuilderTestModelParentStub();
        ModelBuilderTestModelCloseRelatedStub::addGlobalScope('withCount', function ($query) {
            return $query->addSelect('id');
        });

        $builder = $model->select('id')->withCount(['foo']);

        // Remove the global scope so it doesn't interfere with any other tests
        ModelBuilderTestModelCloseRelatedStub::addGlobalScope('withCount', function ($query) {
        });

        $this->assertEquals('select "id", (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id") as "foo_count" from "model_builder_test_model_parent_stubs"', $builder->toSql());
    }

    public function testWithCountAndConstraintsAndHaving()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->where('bar', 'baz');
        $builder->withCount(['foo' => function ($q) {
            $q->where('bam', '>', 'qux');
        }])->having('foo_count', '>=', 1);

        $this->assertEquals('select "model_builder_test_model_parent_stubs".*, (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" and "bam" > ?) as "foo_count" from "model_builder_test_model_parent_stubs" where "bar" = ? having "foo_count" >= ?', $builder->toSql());
        $this->assertEquals(['qux', 'baz', 1], $builder->getBindings());
    }

    public function testWithCountAndRename()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->withCount('foo as foo_bar');

        $this->assertEquals('select "model_builder_test_model_parent_stubs".*, (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id") as "foo_bar" from "model_builder_test_model_parent_stubs"', $builder->toSql());
    }

    public function testWithCountMultipleAndPartialRename()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->withCount(['foo as foo_bar', 'foo']);

        $this->assertEquals('select "model_builder_test_model_parent_stubs".*, (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id") as "foo_bar", (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id") as "foo_count" from "model_builder_test_model_parent_stubs"', $builder->toSql());
    }

    public function testHasWithConstraintsAndHavingInSubquery()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->where('bar', 'baz');
        $builder->whereHas('foo', function ($q) {
            $q->having('bam', '>', 'qux');
        })->where('quux', 'quuux');

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where "bar" = ? and exists (select * from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" having "bam" > ?) and "quux" = ?', $builder->toSql());
        $this->assertEquals(['baz', 'qux', 'quuux'], $builder->getBindings());
    }

    public function testHasWithConstraintsWithOrWhereAndHavingInSubquery()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->where('name', 'larry');
        $builder->whereHas('address', function ($q) {
            $q->where('zipcode', '90210');
            $q->orWhere('zipcode', '90220');
            $q->having('street', '=', 'fooside dr');
        })->where('age', 29);

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where "name" = ? and exists (select * from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" and ("zipcode" = ? or "zipcode" = ?) having "street" = ?) and "age" = ?', $builder->toSql());
        $this->assertEquals(['larry', '90210', '90220', 'fooside dr', 29], $builder->getBindings());
    }

    public function testHasWithContraintsAndJoinAndHavingInSubquery()
    {
        $model = new ModelBuilderTestModelParentStub();
        $builder = $model->where('bar', 'baz');
        $builder->whereHas('foo', function ($q) {
            $q->join('quuuux', function ($j) {
                $j->where('quuuuux', '=', 'quuuuuux');
            });
            $q->having('bam', '>', 'qux');
        })->where('quux', 'quuux');

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where "bar" = ? and exists (select * from "model_builder_test_model_close_related_stubs" inner join "quuuux" on "quuuuux" = ? where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" having "bam" > ?) and "quux" = ?', $builder->toSql());
        $this->assertEquals(['baz', 'quuuuuux', 'qux', 'quuux'], $builder->getBindings());
    }

    public function testHasWithConstraintsAndHavingInSubqueryWithCount()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->where('bar', 'baz');
        $builder->whereHas('foo', function ($q) {
            $q->having('bam', '>', 'qux');
        }, '>=', 2)->where('quux', 'quuux');

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where "bar" = ? and (select count(*) from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" having "bam" > ?) >= 2 and "quux" = ?', $builder->toSql());
        $this->assertEquals(['baz', 'qux', 'quuux'], $builder->getBindings());
    }

    public function testHasNestedWithConstraints()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->whereHas('foo', function ($q) {
            $q->whereHas('bar', function ($q) {
                $q->where('baz', 'bam');
            });
        })->toSql();

        $result = $model->whereHas('foo.bar', function ($q) {
            $q->where('baz', 'bam');
        })->toSql();

        $this->assertEquals($builder, $result);
    }

    public function testHasNested()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->whereHas('foo', function ($q) {
            $q->has('bar');
        });

        $result = $model->has('foo.bar')->toSql();

        $this->assertEquals($builder->toSql(), $result);
    }

    public function testOrHasNested()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->whereHas('foo', function ($q) {
            $q->has('bar');
        })->orWhereHas('foo', function ($q) {
            $q->has('baz');
        });

        $result = $model->has('foo.bar')->orHas('foo.baz')->toSql();

        $this->assertEquals($builder->toSql(), $result);
    }

    public function testSelfHasNested()
    {
        $model = new ModelBuilderTestModelSelfRelatedStub();

        $nestedSql = $model->whereHas('parentFoo', function ($q) {
            $q->has('childFoo');
        })->toSql();

        $dotSql = $model->has('parentFoo.childFoo')->toSql();

        // alias has a dynamic hash, so replace with a static string for comparison
        $alias = 'self_alias_hash';
        $aliasRegex = '/\b(hyperf_reserved_\d)(\b|$)/i';

        $nestedSql = preg_replace($aliasRegex, $alias, $nestedSql);
        $dotSql = preg_replace($aliasRegex, $alias, $dotSql);

        $this->assertEquals($nestedSql, $dotSql);
    }

    public function testSelfHasNestedUsesAlias()
    {
        $model = new ModelBuilderTestModelSelfRelatedStub();

        $sql = $model->has('parentFoo.childFoo')->toSql();

        // alias has a dynamic hash, so replace with a static string for comparison
        $alias = 'self_alias_hash';
        $aliasRegex = '/\b(hyperf_reserved_\d)(\b|$)/i';

        $sql = preg_replace($aliasRegex, $alias, $sql);

        $this->assertStringContainsString('"self_alias_hash"."id" = "self_related_stubs"."parent_id"', $sql);
    }

    public function testDoesntHave()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->doesntHave('foo');

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where not exists (select * from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id")', $builder->toSql());
    }

    public function testDoesntHaveNested()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->doesntHave('foo.bar');

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where not exists (select * from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" and exists (select * from "model_builder_test_model_far_related_stubs" where "model_builder_test_model_close_related_stubs"."id" = "model_builder_test_model_far_related_stubs"."model_builder_test_model_close_related_stub_id"))', $builder->toSql());
    }

    public function testOrDoesntHave()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->where('bar', 'baz')->orDoesntHave('foo');

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where "bar" = ? or not exists (select * from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id")', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testWhereDoesntHave()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->whereDoesntHave('foo', function ($query) {
            $query->where('bar', 'baz');
        });

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where not exists (select * from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" and "bar" = ?)', $builder->toSql());
        $this->assertEquals(['baz'], $builder->getBindings());
    }

    public function testOrWhereDoesntHave()
    {
        $model = new ModelBuilderTestModelParentStub();

        $builder = $model->where('bar', 'baz')->orWhereDoesntHave('foo', function ($query) {
            $query->where('qux', 'quux');
        });

        $this->assertEquals('select * from "model_builder_test_model_parent_stubs" where "bar" = ? or not exists (select * from "model_builder_test_model_close_related_stubs" where "model_builder_test_model_parent_stubs"."foo_id" = "model_builder_test_model_close_related_stubs"."id" and "qux" = ?)', $builder->toSql());
        $this->assertEquals(['baz', 'quux'], $builder->getBindings());
    }

    public function testWhereKeyMethodWithInt()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);
        $keyName = $model->getQualifiedKeyName();

        $int = 1;

        $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '=', $int);

        $builder->whereKey($int);
    }

    public function testWhereKeyMethodWithArray()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);
        $keyName = $model->getQualifiedKeyName();

        $array = [1, 2, 3];

        $builder->getQuery()->shouldReceive('whereIn')->once()->with($keyName, $array);

        $builder->whereKey($array);
    }

    public function testWhereKeyMethodWithCollection()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);
        $keyName = $model->getQualifiedKeyName();

        $collection = new Collection([1, 2, 3]);

        $builder->getQuery()->shouldReceive('whereIn')->once()->with($keyName, $collection);

        $builder->whereKey($collection);
    }

    public function testWhereKeyNotMethodWithInt()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);
        $keyName = $model->getQualifiedKeyName();

        $int = 1;

        $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '!=', $int);

        $builder->whereKeyNot($int);
    }

    public function testWhereKeyNotMethodWithArray()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);
        $keyName = $model->getQualifiedKeyName();

        $array = [1, 2, 3];

        $builder->getQuery()->shouldReceive('whereNotIn')->once()->with($keyName, $array);

        $builder->whereKeyNot($array);
    }

    public function testWhereKeyNotMethodWithCollection()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);
        $keyName = $model->getQualifiedKeyName();

        $collection = new Collection([1, 2, 3]);

        $builder->getQuery()->shouldReceive('whereNotIn')->once()->with($keyName, $collection);

        $builder->whereKeyNot($collection);
    }

    public function testWhereIn()
    {
        $model = new ModelBuilderTestNestedStub();
        $this->mockConnectionForModel($model, '');
        $query = $model->newQuery()->withoutGlobalScopes()->whereIn('foo', $model->newQuery()->select('id'));
        $expected = 'select * from "table" where "foo" in (select "id" from "table" where "table"."deleted_at" is null)';
        $this->assertEquals($expected, $query->toSql());
    }

    public function testLatestWithoutColumnWithCreatedAt()
    {
        $model = $this->getMockModel();
        $model->shouldReceive('getCreatedAtColumn')->andReturn('foo');
        $builder = $this->getBuilder()->setModel($model);

        $builder->getQuery()->shouldReceive('latest')->once()->with('foo');

        $builder->latest();
    }

    public function testLatestWithoutColumnWithoutCreatedAt()
    {
        $model = $this->getMockModel();
        $model->shouldReceive('getCreatedAtColumn')->andReturn(null);
        $builder = $this->getBuilder()->setModel($model);

        $builder->getQuery()->shouldReceive('latest')->once()->with('created_at');

        $builder->latest();
    }

    public function testLatestWithColumn()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);

        $builder->getQuery()->shouldReceive('latest')->once()->with('foo');

        $builder->latest('foo');
    }

    public function testOldestWithoutColumnWithCreatedAt()
    {
        $model = $this->getMockModel();
        $model->shouldReceive('getCreatedAtColumn')->andReturn('foo');
        $builder = $this->getBuilder()->setModel($model);

        $builder->getQuery()->shouldReceive('oldest')->once()->with('foo');

        $builder->oldest();
    }

    public function testOldestWithoutColumnWithoutCreatedAt()
    {
        $model = $this->getMockModel();
        $model->shouldReceive('getCreatedAtColumn')->andReturn(null);
        $builder = $this->getBuilder()->setModel($model);

        $builder->getQuery()->shouldReceive('oldest')->once()->with('created_at');

        $builder->oldest();
    }

    public function testOldestWithColumn()
    {
        $model = $this->getMockModel();
        $builder = $this->getBuilder()->setModel($model);

        $builder->getQuery()->shouldReceive('oldest')->once()->with('foo');

        $builder->oldest('foo');
    }

    public function testUpdate()
    {
        Carbon::setTestNow($now = '2017-10-10 10:10:10');

        $query = new BaseBuilder(Mockery::mock(ConnectionInterface::class), new Grammar(), Mockery::mock(Processor::class));
        $builder = new Builder($query);
        $model = new ModelBuilderTestStub();
        $this->mockConnectionForModel($model, '');
        $builder->setModel($model);
        $builder->getConnection()->shouldReceive('update')->once()
            ->with('update "table" set "foo" = ?, "table"."updated_at" = ?', ['bar', $now])->andReturn(1);

        $result = $builder->update(['foo' => 'bar']);
        $this->assertEquals(1, $result);

        Carbon::setTestNow(null);
    }

    public function testUpdateWithTimestampValue()
    {
        $query = new BaseBuilder(Mockery::mock(ConnectionInterface::class), new Grammar(), Mockery::mock(Processor::class));
        $builder = new Builder($query);
        $model = new ModelBuilderTestStub();
        $this->mockConnectionForModel($model, '');
        $builder->setModel($model);
        $builder->getConnection()->shouldReceive('update')->once()
            ->with('update "table" set "foo" = ?, "table"."updated_at" = ?', ['bar', null])->andReturn(1);

        $result = $builder->update(['foo' => 'bar', 'updated_at' => null]);
        $this->assertEquals(1, $result);
    }

    public function testUpdateWithoutTimestamp()
    {
        $query = new BaseBuilder(Mockery::mock(ConnectionInterface::class), new Grammar(), Mockery::mock(Processor::class));
        $builder = new Builder($query);
        $model = new ModelBuilderTestStubWithoutTimestamp();
        $this->mockConnectionForModel($model, '');
        $builder->setModel($model);
        $builder->getConnection()->shouldReceive('update')->once()
            ->with('update "table" set "foo" = ?', ['bar'])->andReturn(1);

        $result = $builder->update(['foo' => 'bar']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateWithAlias()
    {
        Carbon::setTestNow($now = '2017-10-10 10:10:10');

        $query = new BaseBuilder(Mockery::mock(ConnectionInterface::class), new Grammar(), Mockery::mock(Processor::class));
        $builder = new Builder($query);
        $model = new ModelBuilderTestStub();
        $this->mockConnectionForModel($model, '');
        $builder->setModel($model);
        $builder->getConnection()->shouldReceive('update')->once()
            ->with('update "table" as "alias" set "foo" = ?, "alias"."updated_at" = ?', ['bar', $now])->andReturn(1);

        $result = $builder->from('table as alias')->update(['foo' => 'bar']);
        $this->assertEquals(1, $result);

        Carbon::setTestNow(null);
    }

    public function testWithCastsMethod()
    {
        $builder = new Builder($this->getMockQueryBuilder());
        $model = $this->getMockModel();
        $builder->setModel($model);

        $model->shouldReceive('mergeCasts')->with(['foo' => 'bar'])->once();
        $builder->withCasts(['foo' => 'bar']);
    }

    public function testMixin()
    {
        Builder::macro('testAbc', fn () => 'abc');
        $this->assertEquals('abc', ModelStub::testAbc());

        Builder::mixin(new UserMixin());

        $this->assertInstanceOf(Builder::class, ModelStub::whereFoo());
        $this->assertInstanceOf(Builder::class, ModelStub::whereBar());
    }

    public function testClone()
    {
        $builder = ModelStub::query();
        $clone = $builder->clone();

        $clone->select(['id']);

        $this->assertNotEquals($builder->toSql(), $clone->toSql());
        $this->assertSame('select * from "stub"', $builder->toSql());
        $this->assertSame('select "id" from "stub"', $clone->toSql());
    }

    public function testToRawSql()
    {
        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('toRawSql')
            ->andReturn('select * from "users" where "email" = \'foo\'');

        $builder = new Builder($query);

        $this->assertSame('select * from "users" where "email" = \'foo\'', $builder->toRawSql());
    }

    public function testWithAggregateAndSelfRelationConstrain()
    {
        ModelBuilderTestStub::resolveRelationUsing('children', function ($model) {
            return $model->hasMany(ModelBuilderTestStub::class, 'parent_id', 'id')->where('enum_value', new stdClass());
        });

        ModelBuilderTestStub::resolveRelationUsing('customer', function ($model) {
            return $model->belongsTo(ModelBuilderTestStub::class, 'customer_id');
        });

        $model = new ModelBuilderTestStub();
        $this->mockConnectionForModel($model, '');
        $relationHash = $model->children()->getRelationCountHash(false);
        $relationHash2 = $model->customer()->getRelationCountHash(false);

        $builder = $model->withCount('children');
        $builder2 = $model->has('customer');

        $this->assertSame(vsprintf('select "table".*, (select count(*) from "table" as "%s" where "table"."id" = "%s"."parent_id" and "enum_value" = ?) as "children_count" from "table"', [$relationHash, $relationHash]), $builder->toSql());
        $this->assertSame(vsprintf('select * from "table" where exists (select * from "table" as "%s" where "%s"."id" = "table"."customer_id")', [$relationHash2, $relationHash2]), $builder2->toSql());
    }

    public function testTouch()
    {
        Carbon::setTestNow($now = '2017-10-10 10:10:10');

        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
        $query->from = 'foo_table';

        $builder = new Builder($query);
        $model = new ModelBuilderTestStubStringPrimaryKey();
        $builder->setModel($model);

        $query->shouldReceive('update')->once()->with(['updated_at' => $now])->andReturn(2);

        $result = $builder->touch();

        $this->assertEquals(2, $result);
    }

    public function testSole()
    {
        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
        $query->shouldReceive('take')->with(2)->andReturnSelf();
        $result = [
            'id' => 1,
            'name' => 'Hyperf',
        ];
        $query->shouldReceive('get')->with(['*'])->andReturn(new Collection([$result]));
        $query->from = 'foo_table';
        $builder = new Builder($query);
        $model = new ModelBuilderTestStubStringPrimaryKey();
        $builder->setModel($model);
        $this->assertEquals($builder->sole()->toArray(), $result);
    }

    public function testSoleNotFound()
    {
        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
        $query->shouldReceive('take')->with(2)->andReturnSelf();
        $query->shouldReceive('get')->with(['1'])->andReturn(new Collection());
        $query->from = 'foo_table';
        $builder = new Builder($query);
        $model = new ModelBuilderTestStubStringPrimaryKey();
        $builder->setModel($model);
        $this->expectException(ModelNotFoundException::class);
        $builder->sole(['1']);
    }

    public function testSoleMultipleRecordsFoundException()
    {
        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
        $query->shouldReceive('take')->with(2)->andReturnSelf();
        $result = [
            'id' => 1,
            'name' => 'Hyperf',
        ];
        $query->shouldReceive('get')->with(['*'])->andReturn(new Collection([$result, $result]));
        $query->from = 'foo_table';
        $builder = new Builder($query);
        $model = new ModelBuilderTestStubStringPrimaryKey();
        $builder->setModel($model);
        $this->expectException(MultipleRecordsFoundException::class);
        $builder->sole();
    }

    public function testTouchWithCustomColumn()
    {
        Carbon::setTestNow($now = '2017-10-10 10:10:10');

        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
        $query->from = 'foo_table';

        $builder = new Builder($query);
        $model = new ModelBuilderTestStubStringPrimaryKey();
        $builder->setModel($model);

        $query->shouldReceive('update')->once()->with(['published_at' => $now])->andReturn(2);

        $result = $builder->touch('published_at');

        $this->assertEquals(2, $result);
    }

    public function testTouchWithoutUpdatedAtColumn()
    {
        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('from')->with('table')->andReturn('table');
        $query->from = 'table';

        $builder = new Builder($query);
        $model = new ModelBuilderTestStubWithoutTimestamp();
        $builder->setModel($model);

        $query->shouldNotReceive('update');

        $result = $builder->touch();

        $this->assertFalse($result);
    }

    public function testQualifyColumns()
    {
        $builder = new Builder(Mockery::mock(BaseBuilder::class));
        $builder->shouldReceive('from')->with('foo_table');

        $builder->setModel(new ModelBuilderTestStubStringPrimaryKey());

        $this->assertEquals(['foo_table.column', 'foo_table.name'], $builder->qualifyColumns(['column', 'name']));
    }

    public function testValueOrFailMethodWithModelFound()
    {
        $builder = Mockery::mock(Builder::class . '[first]', [$this->getMockQueryBuilder()]);
        $mockModel = new stdClass();
        $mockModel->name = 'foo';
        $builder->shouldReceive('first')->with(['name'])->andReturn($mockModel);
        $builder->shouldReceive('first')->with(['test'])->andReturn(null);
        $builder->setModel(new ModelBuilderTestStubStringPrimaryKey());
        $this->assertSame('foo', $builder->valueOrFail('name'));
        $this->expectException(ModelNotFoundException::class);
        $builder->valueOrFail('test');
    }

    public function testLazyWithLastChunkComplete()
    {
        $builder = Mockery::mock(Builder::class . '[forPage,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(3, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn(
            new Collection(['foo1', 'foo2']),
            new Collection(['foo3', 'foo4']),
            new Collection([])
        );

        $this->assertEquals(
            ['foo1', 'foo2', 'foo3', 'foo4'],
            $builder->lazy(2)->all()
        );
    }

    public function testLazyWithLastChunkPartial()
    {
        $builder = Mockery::mock(Builder::class . '[forPage,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn(
            new Collection(['foo1', 'foo2']),
            new Collection(['foo3'])
        );

        $this->assertEquals(
            ['foo1', 'foo2', 'foo3'],
            $builder->lazy(2)->all()
        );
    }

    public function testLazyIsLazy()
    {
        $builder = Mockery::mock(Builder::class . '[forPage,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(new Collection(['foo1', 'foo2']));

        $this->assertEquals(['foo1', 'foo2'], $builder->lazy(2)->take(2)->all());
    }

    public function testLazyByIdWithLastChunkComplete()
    {
        $builder = Mockery::mock(Builder::class . '[forPageAfterId,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = new Collection([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
        $chunk3 = new Collection([]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

        $this->assertEquals(
            [
                (object) ['someIdField' => 1],
                (object) ['someIdField' => 2],
                (object) ['someIdField' => 10],
                (object) ['someIdField' => 11],
            ],
            $builder->lazyById(2, 'someIdField')->all()
        );
    }

    public function testLazyByIdWithLastChunkPartial()
    {
        $builder = Mockery::mock(Builder::class . '[forPageAfterId,get]', [$this->getMockQueryBuilder()]);
        $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

        $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
        $chunk2 = new Collection([(object) ['someIdField' => 10]]);
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
        $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

        $this->assertEquals(
            [
                (object) ['someIdField' => 1],
                (object) ['someIdField' => 2],
                (object) ['someIdField' => 10],
            ],
            $builder->lazyById(2, 'someIdField')->all()
        );
    }

    protected function mockConnectionForModel($model, $database)
    {
        $grammarClass = 'Hyperf\Database\Query\Grammars\\' . $database . 'Grammar';
        $processorClass = 'Hyperf\Database\Query\Processors\\' . $database . 'Processor';
        $grammar = new $grammarClass();
        $processor = new $processorClass();
        $connection = Mockery::mock(ConnectionInterface::class, ['getQueryGrammar' => $grammar, 'getPostProcessor' => $processor]);
        $resolver = Mockery::mock(ConnectionResolverInterface::class, ['connection' => $connection]);
        Register::setConnectionResolver($resolver);
    }

    protected function getBuilder()
    {
        return new Builder($this->getMockQueryBuilder());
    }

    protected function getMockModel()
    {
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getKeyName')->andReturn('foo');
        $model->shouldReceive('getTable')->andReturn('foo_table');
        $model->shouldReceive('getQualifiedKeyName')->andReturn('foo_table.foo');

        return $model;
    }

    protected function getMockQueryBuilder()
    {
        $query = Mockery::mock(BaseBuilder::class);
        $query->shouldReceive('from')->with('foo_table');

        return $query;
    }
}
class ModelBuilderTestStubStringPrimaryKey extends Model
{
    public bool $incrementing = false;

    protected ?string $table = 'foo_table';

    protected string $keyType = 'string';
}

class UserMixin
{
    public function whereFoo()
    {
        return fn () => $this;
    }

    public function whereBar()
    {
        return fn () => $this;
    }
}

class ModelBuilderTestStub extends Model
{
    protected ?string $table = 'table';
}

class ModelBuilderTestScopeStub extends Model
{
    public function scopeApproved($query)
    {
        $query->where('foo', 'bar');
    }
}

class ModelBuilderTestNestedStub extends Model
{
    use SoftDeletes;

    protected ?string $table = 'table';

    public function scopeEmpty($query)
    {
        return $query;
    }
}

class ModelBuilderTestPluckStub
{
    protected array $attributes;

    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    public function __get($key)
    {
        return 'foo_' . $this->attributes[$key];
    }
}

class ModelBuilderTestPluckDatesStub extends Model
{
    protected array $attributes;

    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    protected function asDateTime(mixed $value): CarbonInterface
    {
        return Carbon::make($value);
    }
}

class ModelBuilderTestModelParentStub extends Model
{
    public function foo()
    {
        return $this->belongsTo(ModelBuilderTestModelCloseRelatedStub::class);
    }

    public function address()
    {
        return $this->belongsTo(ModelBuilderTestModelCloseRelatedStub::class, 'foo_id');
    }

    public function activeFoo()
    {
        return $this->belongsTo(ModelBuilderTestModelCloseRelatedStub::class, 'foo_id')->where('active', true);
    }
}

class ModelBuilderTestModelCloseRelatedStub extends Model
{
    public function bar()
    {
        return $this->hasMany(ModelBuilderTestModelFarRelatedStub::class);
    }

    public function baz()
    {
        return $this->hasMany(ModelBuilderTestModelFarRelatedStub::class);
    }
}

class ModelBuilderTestModelFarRelatedStub extends Model
{
}

class ModelBuilderTestModelSelfRelatedStub extends Model
{
    protected ?string $table = 'self_related_stubs';

    public function parentFoo()
    {
        return $this->belongsTo(ModelBuilderTestModelSelfRelatedStub::class, 'parent_id', 'id', 'parent');
    }

    public function childFoo()
    {
        return $this->hasOne(ModelBuilderTestModelSelfRelatedStub::class, 'parent_id', 'id');
    }

    public function childFoos()
    {
        return $this->hasMany(ModelBuilderTestModelSelfRelatedStub::class, 'parent_id', 'id', 'children');
    }

    public function parentBars()
    {
        return $this->belongsToMany(ModelBuilderTestModelSelfRelatedStub::class, 'self_pivot', 'child_id', 'parent_id', 'parent_bars');
    }

    public function childBars()
    {
        return $this->belongsToMany(ModelBuilderTestModelSelfRelatedStub::class, 'self_pivot', 'parent_id', 'child_id', 'child_bars');
    }

    public function bazes()
    {
        return $this->hasMany(ModelBuilderTestModelFarRelatedStub::class, 'foreign_key', 'id', 'bar');
    }
}

class ModelBuilderTestStubWithoutTimestamp extends Model
{
    public const UPDATED_AT = null;

    protected ?string $table = 'table';
}
