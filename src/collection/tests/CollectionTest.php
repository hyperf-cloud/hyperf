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

namespace HyperfTest\Collection;

use Exception;
use Hyperf\Codec\Json;
use Hyperf\Collection\Collection;
use Hyperf\Collection\ItemNotFoundException;
use Hyperf\Collection\LazyCollection;
use Hyperf\Collection\MultipleItemsFoundException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Hyperf\Collection\collect;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class CollectionTest extends TestCase
{
    public function testOperatorForWhere(): void
    {
        $col = new Collection([['id' => 1, 'name' => 'Hyperf'], ['id' => 2, 'name' => 'HyperfCloud']]);

        $res = $col->where('id', 1);
        $this->assertSame(1, $res->count());
        $this->assertSame(['id' => 1, 'name' => 'Hyperf'], $res->shift());

        $res = $col->where('id', '=', 2);
        $this->assertSame(1, $res->count());
        $this->assertSame(['id' => 2, 'name' => 'HyperfCloud'], $res->shift());
    }

    public function testRandom(): void
    {
        $col = new Collection([['id' => 1, 'name' => 'Hyperf'], ['id' => 2, 'name' => 'HyperfCloud']]);

        $res = $col->random();
        $this->assertIsArray($res);

        $res = $col->random(1);
        $this->assertInstanceOf(Collection::class, $res);
    }

    public function testFlatten(): void
    {
        $collection = new Collection([
            'item' => [
                'name' => 'Hyperf',
            ],
            'it' => [
                'id' => $uuid = uniqid('', true),
            ],
        ]);

        $this->assertSame(['Hyperf', $uuid], $collection->flatten()->toArray());
    }

    public function testCollectionAverage(): void
    {
        $col = new Collection([]);
        $this->assertNull($col->avg());
    }

    public function testContainsOneItem(): void
    {
        $this->assertFalse((new Collection([]))->containsOneItem());
        $this->assertTrue((new Collection([1]))->containsOneItem());
        $this->assertFalse((new Collection([1, 2]))->containsOneItem());
    }

    public function testDoesntContain(): void
    {
        $col = new Collection([1, 3, 5]);

        $this->assertFalse($col->doesntContain(1));
        $this->assertFalse($col->doesntContain('1'));
        $this->assertTrue($col->doesntContain(2));
        $this->assertTrue($col->doesntContain('2'));

        $col = new Collection(['1']);
        $this->assertFalse($col->doesntContain('1'));
        $this->assertFalse($col->doesntContain(1));

        $col = new Collection([null]);
        $this->assertFalse($col->doesntContain(false));
        $this->assertFalse($col->doesntContain(null));
        $this->assertFalse($col->doesntContain([]));
        $this->assertFalse($col->doesntContain(0));
        $this->assertFalse($col->doesntContain(''));

        $col = new Collection([0]);
        $this->assertFalse($col->doesntContain(0));
        $this->assertFalse($col->doesntContain('0'));
        $this->assertFalse($col->doesntContain(false));
        $this->assertFalse($col->doesntContain(null));

        $this->assertFalse($col->doesntContain(function ($value) {
            return $value < 5;
        }));
        $this->assertTrue($col->doesntContain(function ($value) {
            return $value > 5;
        }));

        $col = new Collection([['v' => 1], ['v' => 3], ['v' => 5]]);

        $this->assertFalse($col->doesntContain('v', 1));
        $this->assertTrue($col->doesntContain('v', 2));

        $col = new Collection(['date', 'class', (object) ['foo' => 50]]);

        $this->assertFalse($col->doesntContain('date'));
        $this->assertFalse($col->doesntContain('class'));
        $this->assertTrue($col->doesntContain('foo'));

        $col = new Collection([
            null, 1, 2,
        ]);

        $this->assertFalse($col->doesntContain(function ($value) {
            return is_null($value);
        }));
    }

    public function testDot(): void
    {
        $col = Collection::make([
            'name' => 'Hyperf',
            'meta' => [
                'foo' => 'bar',
                'baz' => 'boom',
                'bam' => [
                    'boom' => 'bip',
                ],
            ],
        ])->dot();
        $this->assertSame([
            'name' => 'Hyperf',
            'meta.foo' => 'bar',
            'meta.baz' => 'boom',
            'meta.bam.boom' => 'bip',
        ], $col->all());

        $col = Collection::make([
            'foo' => [
                'bar',
                'baz',
                'baz' => 'boom',
            ],
        ])->dot();
        $this->assertSame([
            'foo.0' => 'bar',
            'foo.1' => 'baz',
            'foo.baz' => 'boom',
        ], $col->all());
    }

    public function testHasAny(): void
    {
        $col = new Collection(['id' => 1, 'first' => 'Hello', 'second' => 'World']);

        $this->assertTrue($col->hasAny('first'));
        $this->assertFalse($col->hasAny('third'));
        $this->assertTrue($col->hasAny(['first', 'second']));
        $this->assertTrue($col->hasAny(['first', 'fourth']));
        $this->assertFalse($col->hasAny(['third', 'fourth']));
        $this->assertFalse($col->hasAny('third', 'fourth'));
        $this->assertFalse($col->hasAny([]));
    }

    public function testIntersectAssocWithNull(): void
    {
        $collection = new Collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);

        $this->assertEquals([], $collection->intersectAssoc(null)->all());
    }

    public function testIntersectAssocCollection(): void
    {
        $collectionOne = new Collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);
        $collectionTwo = new Collection(['a' => 'green', 'b' => 'yellow', 'blue', 'red']);

        $this->assertEquals(['a' => 'green'], $collectionOne->intersectAssoc($collectionTwo)->all());
    }

    public function testMapWithKeys(): void
    {
        $data = new Collection([
            ['name' => 'Blastoise', 'type' => 'Water', 'idx' => 9],
            ['name' => 'Charmander', 'type' => 'Fire', 'idx' => 4],
            ['name' => 'Dragonair', 'type' => 'Dragon', 'idx' => 148],
        ]);
        $data = $data->mapWithKeys(function ($pokemon) {
            return [$pokemon['name'] => $pokemon['type']];
        });
        $this->assertEquals(
            ['Blastoise' => 'Water', 'Charmander' => 'Fire', 'Dragonair' => 'Dragon'],
            $data->all()
        );
    }

    public function testMapWithKeysIntegerKeys(): void
    {
        $data = new Collection([
            ['id' => 1, 'name' => 'A'],
            ['id' => 3, 'name' => 'B'],
            ['id' => 2, 'name' => 'C'],
        ]);
        $data = $data->mapWithKeys(function ($item) {
            return [$item['id'] => $item];
        });
        $this->assertSame(
            [1, 3, 2],
            $data->keys()->all()
        );
    }

    public function testMapWithKeysMultipleRows(): void
    {
        $data = new Collection([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
            ['id' => 3, 'name' => 'C'],
        ]);
        $data = $data->mapWithKeys(function ($item) {
            return [$item['id'] => $item['name'], $item['name'] => $item['id']];
        });
        $this->assertSame(
            [
                1 => 'A',
                'A' => 1,
                2 => 'B',
                'B' => 2,
                3 => 'C',
                'C' => 3,
            ],
            $data->all()
        );
    }

    public function testMapWithKeysCallbackKey(): void
    {
        $data = new Collection([
            3 => ['id' => 1, 'name' => 'A'],
            5 => ['id' => 3, 'name' => 'B'],
            4 => ['id' => 2, 'name' => 'C'],
        ]);
        $data = $data->mapWithKeys(function ($item, $key) {
            return [$key => $item['id']];
        });
        $this->assertSame(
            [3, 5, 4],
            $data->keys()->all()
        );
    }

    public function testMergeRecursiveNull(): void
    {
        $col = new Collection(['name' => 'Hello']);
        $this->assertEquals(['name' => 'Hello'], $col->mergeRecursive(null)->all());
    }

    public function testMergeRecursiveArray(): void
    {
        $col = new Collection(['name' => 'Hello', 'id' => 1]);
        $this->assertEquals(['name' => 'Hello', 'id' => [1, 2]], $col->mergeRecursive(['id' => 2])->all());
    }

    public function testMergeRecursiveCollection(): void
    {
        $col = new Collection(['name' => 'Hello', 'id' => 1, 'meta' => ['tags' => ['a', 'b'], 'roles' => 'admin']]);
        $this->assertEquals(
            ['name' => 'Hello', 'id' => 1, 'meta' => ['tags' => ['a', 'b', 'c'], 'roles' => ['admin', 'editor']]],
            $col->mergeRecursive(new Collection(['meta' => ['tags' => ['c'], 'roles' => 'editor']]))->all()
        );
    }

    public function testForgetSingleKey(): void
    {
        $c = new Collection(['foo', 'bar']);
        $c = $c->forget(0)->all();
        $this->assertFalse(isset($c['foo']));
        $this->assertFalse(isset($c[0]));
        $this->assertTrue(isset($c[1]));
        $c = new Collection(['foo' => 'bar', 'baz' => 'qux']);
        $c = $c->forget('foo')->all();
        $this->assertFalse(isset($c['foo']));
        $this->assertTrue(isset($c['baz']));
    }

    public function testForgetArrayOfKeys(): void
    {
        $c = new Collection(['foo', 'bar', 'baz']);
        $c = $c->forget([0, 2])->all();
        $this->assertFalse(isset($c[0]));
        $this->assertFalse(isset($c[2]));
        $this->assertTrue(isset($c[1]));
        $c = new Collection(['name' => 'taylor', 'foo' => 'bar', 'baz' => 'qux']);
        $c = $c->forget(['foo', 'baz'])->all();
        $this->assertFalse(isset($c['foo']));
        $this->assertFalse(isset($c['baz']));
        $this->assertTrue(isset($c['name']));
    }

    public function testForgetCollectionOfKeys(): void
    {
        $c = new Collection(['foo', 'bar', 'baz']);
        $c = $c->forget(collect([0, 2]))->all();
        $this->assertFalse(isset($c[0]));
        $this->assertFalse(isset($c[2]));
        $this->assertTrue(isset($c[1]));

        $c = new Collection(['name' => 'taylor', 'foo' => 'bar', 'baz' => 'qux']);
        $c = $c->forget(collect(['foo', 'baz']))->all();
        $this->assertFalse(isset($c['foo']));
        $this->assertFalse(isset($c['baz']));
        $this->assertTrue(isset($c['name']));
    }

    public function testExcept(): void
    {
        $data = new Collection(['first' => 'Swoole', 'last' => 'Hyperf', 'email' => 'hyperf@gmail.com']);

        $this->assertEquals($data->all(), $data->except(null)->all());
        $this->assertEquals(['first' => 'Swoole'], $data->except(['last', 'email', 'missing'])->all());
        $this->assertEquals(['first' => 'Swoole'], $data->except('last', 'email', 'missing')->all());
        $this->assertEquals(['first' => 'Swoole'], $data->except(collect(['last', 'email', 'missing']))->all());

        $this->assertEquals(['first' => 'Swoole', 'email' => 'hyperf@gmail.com'], $data->except(['last'])->all());
        $this->assertEquals(['first' => 'Swoole', 'email' => 'hyperf@gmail.com'], $data->except('last')->all());
        $this->assertEquals(['first' => 'Swoole', 'email' => 'hyperf@gmail.com'], $data->except(collect(['last']))->all());
    }

    public function testRangeMethod(): void
    {
        $this->assertSame(
            [1, 2, 3, 4, 5],
            Collection::range(1, 5)->all()
        );

        $this->assertSame(
            [-2, -1, 0, 1, 2],
            Collection::range(-2, 2)->all()
        );

        $this->assertSame(
            [-4, -3, -2],
            Collection::range(-4, -2)->all()
        );

        $this->assertSame(
            [5, 4, 3, 2, 1],
            Collection::range(5, 1)->all()
        );

        $this->assertSame(
            [2, 1, 0, -1, -2],
            Collection::range(2, -2)->all()
        );

        $this->assertSame(
            [-2, -3, -4],
            Collection::range(-2, -4)->all()
        );
    }

    public function testReplaceNull(): void
    {
        $c = new Collection(['a', 'b', 'c']);
        $this->assertEquals(['a', 'b', 'c'], $c->replace(null)->all());
    }

    public function testReplaceArray(): void
    {
        $c = new Collection(['a', 'b', 'c']);
        $this->assertEquals(['a', 'd', 'e'], $c->replace([1 => 'd', 2 => 'e'])->all());

        $c = new Collection(['a', 'b', 'c']);
        $this->assertEquals(['a', 'd', 'e', 'f', 'g'], $c->replace([1 => 'd', 2 => 'e', 3 => 'f', 4 => 'g'])->all());

        $c = new Collection(['name' => 'amir', 'family' => 'otwell']);
        $this->assertEquals(['name' => 'taylor', 'family' => 'otwell', 'age' => 26], $c->replace(['name' => 'taylor', 'age' => 26])->all());
    }

    public function testReplaceCollection(): void
    {
        $c = new Collection(['a', 'b', 'c']);
        $this->assertEquals(
            ['a', 'd', 'e'],
            $c->replace(new Collection([1 => 'd', 2 => 'e']))->all()
        );

        $c = new Collection(['a', 'b', 'c']);
        $this->assertEquals(
            ['a', 'd', 'e', 'f', 'g'],
            $c->replace(new Collection([1 => 'd', 2 => 'e', 3 => 'f', 4 => 'g']))->all()
        );

        $c = new Collection(['name' => 'amir', 'family' => 'otwell']);
        $this->assertEquals(
            ['name' => 'taylor', 'family' => 'otwell', 'age' => 26],
            $c->replace(new Collection(['name' => 'taylor', 'age' => 26]))->all()
        );
    }

    public function testReplaceRecursiveNull(): void
    {
        $c = new Collection(['a', 'b', ['c', 'd']]);
        $this->assertEquals(['a', 'b', ['c', 'd']], $c->replaceRecursive(null)->all());
    }

    public function testReplaceRecursiveArray(): void
    {
        $c = new Collection(['a', 'b', ['c', 'd']]);
        $this->assertEquals(['z', 'b', ['c', 'e']], $c->replaceRecursive(['z', 2 => [1 => 'e']])->all());

        $c = new Collection(['a', 'b', ['c', 'd']]);
        $this->assertEquals(['z', 'b', ['c', 'e'], 'f'], $c->replaceRecursive(['z', 2 => [1 => 'e'], 'f'])->all());
    }

    public function testReplaceRecursiveCollection(): void
    {
        $c = new Collection(['a', 'b', ['c', 'd']]);
        $this->assertEquals(
            ['z', 'b', ['c', 'e']],
            $c->replaceRecursive(new Collection(['z', 2 => [1 => 'e']]))->all()
        );
    }

    public function testSkipMethod(): void
    {
        $data = new Collection([1, 2, 3, 4, 5, 6]);

        $this->assertSame([5, 6], $data->skip(4)->values()->all());
        $this->assertSame([], $data->skip(10)->values()->all());
    }

    public function testSliding(): void
    {
        $this->assertSame([], Collection::times(0)->sliding()->toArray());
        $this->assertSame([], Collection::times(1)->sliding()->toArray());
        $this->assertSame([[1, 2]], Collection::times(2)->sliding()->toArray());
        $this->assertSame(
            [[1, 2], [2, 3]],
            Collection::times(3)->sliding()->map->values()->toArray()
        );

        $this->assertSame([], Collection::times(1)->sliding(2, 3)->toArray());
        $this->assertSame([[1, 2]], Collection::times(2)->sliding(2, 3)->toArray());
        $this->assertSame([[1, 2]], Collection::times(3)->sliding(2, 3)->toArray());
        $this->assertSame([[1, 2]], Collection::times(4)->sliding(2, 3)->toArray());
        $this->assertSame(
            [[1, 2], [4, 5]],
            Collection::times(5)->sliding(2, 3)->map->values()->toArray()
        );

        $this->assertSame([], Collection::times(2)->sliding(3)->toArray());
        $this->assertSame([[1, 2, 3]], Collection::times(3)->sliding(3)->toArray());
        $this->assertSame(
            [[1, 2, 3], [2, 3, 4]],
            Collection::times(4)->sliding(3)->map->values()->toArray()
        );
        $this->assertSame(
            [[1, 2, 3], [2, 3, 4]],
            Collection::times(4)->sliding(3)->map->values()->toArray()
        );

        $this->assertSame([], Collection::times(2)->sliding(3, 2)->toArray());
        $this->assertSame([[1, 2, 3]], Collection::times(3)->sliding(3, 2)->toArray());
        $this->assertSame([[1, 2, 3]], Collection::times(4)->sliding(3, 2)->toArray());
        $this->assertSame(
            [[1, 2, 3], [3, 4, 5]],
            Collection::times(5)->sliding(3, 2)->map->values()->toArray()
        );
        $this->assertSame(
            [[1, 2, 3], [3, 4, 5]],
            Collection::times(6)->sliding(3, 2)->map->values()->toArray()
        );

        $chunks = Collection::times(3)->sliding();

        $this->assertSame([[0 => 1, 1 => 2], [1 => 2, 2 => 3]], $chunks->toArray());

        $this->assertInstanceOf(Collection::class, $chunks);
        $this->assertInstanceOf(Collection::class, $chunks->first());
        $this->assertInstanceOf(Collection::class, $chunks->skip(1)->first());
    }

    public function testSortDesc(): void
    {
        $data = (new Collection([5, 3, 1, 2, 4]))->sortDesc();
        $this->assertEquals([5, 4, 3, 2, 1], $data->values()->all());

        $data = (new Collection([-1, -3, -2, -4, -5, 0, 5, 3, 1, 2, 4]))->sortDesc();
        $this->assertEquals([5, 4, 3, 2, 1, 0, -1, -2, -3, -4, -5], $data->values()->all());

        $data = (new Collection(['bar-1', 'foo', 'bar-10']))->sortDesc();
        $this->assertEquals(['foo', 'bar-10', 'bar-1'], $data->values()->all());

        $data = (new Collection(['T2', 'T1', 'T10']))->sortDesc();
        $this->assertEquals(['T2', 'T10', 'T1'], $data->values()->all());

        $data = (new Collection(['T2', 'T1', 'T10']))->sortDesc(SORT_NATURAL);
        $this->assertEquals(['T10', 'T2', 'T1'], $data->values()->all());
    }

    public function testSortKeysUsing(): void
    {
        $data = new Collection(['B' => 'dayle', 'a' => 'taylor']);

        $this->assertSame(['a' => 'taylor', 'B' => 'dayle'], $data->sortKeysUsing('strnatcasecmp')->all());
    }

    public function testSplitIn(): void
    {
        $data = new Collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        $data = $data->splitIn(3);

        $this->assertInstanceOf(Collection::class, $data);
        $this->assertInstanceOf(Collection::class, $data->first());
        $this->assertCount(3, $data);
        $this->assertEquals([1, 2, 3, 4], $data->get(0)->values()->toArray());
        $this->assertEquals([5, 6, 7, 8], $data->get(1)->values()->toArray());
        $this->assertEquals([9, 10], $data->get(2)->values()->toArray());
    }

    public function testIsset()
    {
        $data = [null, 1];
        $c = new Collection($data);
        $this->assertFalse(isset($data[0]));
        $this->assertFalse(isset($c[0]));
        $this->assertTrue(isset($data[1]));
        $this->assertTrue(isset($c[1]));
    }

    public function testUnshiftWithOneItem()
    {
        $expected = [
            0 => 'Jonny from Laroe',
            1 => ['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe'],
            2 => ['a', 'b', 'c'],
            3 => 4,
            4 => 5,
            5 => 6,
        ];

        $data = new Collection([4, 5, 6]);
        $data->unshift(['a', 'b', 'c']);
        $data->unshift(['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe']);
        $actual = $data->unshift('Jonny from Laroe')->toArray();

        $this->assertSame($expected, $actual);
    }

    public function testUnshiftWithMultipleItems()
    {
        $expected = [
            0 => 'a',
            1 => 'b',
            2 => 'c',
            3 => 'Jonny',
            4 => 'from',
            5 => 'Laroe',
            6 => 'Jonny',
            7 => 'from',
            8 => 'Laroe',
            9 => 4,
            10 => 5,
            11 => 6,
        ];

        $data = new Collection([4, 5, 6]);
        $data->unshift('Jonny', 'from', 'Laroe');
        $data->unshift(...[11 => 'Jonny', 12 => 'from', 13 => 'Laroe']);
        $data->unshift(...collect(['a', 'b', 'c']));
        $actual = $data->unshift(...[])->toArray();

        $this->assertSame($expected, $actual);
    }

    public function testWhen()
    {
        $c = (new Collection([]))
            ->when(true, fn (Collection $collection) => $collection->push(1))
            ->when(false, fn (Collection $collection) => $collection->push(2))
            ->when(null, fn (Collection $collection) => $collection->push(3))
            ->when('', fn (Collection $collection) => $collection->push(4))
            ->when([], fn (Collection $collection) => $collection->push(5))
            ->when(0, fn (Collection $collection) => $collection->push(6));

        $this->assertSame([1], $c->all());
    }

    public function testWhenWithValueForCallback(): void
    {
        $callback = fn (Collection $collection, string $value) => $collection->push($value);

        $c = (new Collection([]))
            ->when('foo', $callback)
            ->when('', $callback);

        $this->assertSame(['foo'], $c->all());
    }

    public function testWhenValueOfClosure(): void
    {
        $callback = fn (Collection $collection, $value) => $collection->push($value);

        $c = (new Collection([]))->when(fn () => 'foo', $callback)->when(fn () => '', $callback);
        $this->assertSame(['foo'], $c->all());

        $c = (new Collection([1, 2]))->when(
            fn (Collection $collection) => $collection->shift(),
            $callback
        );
        $this->assertSame([2, 1], $c->all());
    }

    public function testWhenCallbackWithDefault(): void
    {
        $callback = fn (Collection $collection, $value) => $collection;
        $default = fn (Collection $collection, $value) => $collection->push($value);

        $c = (new Collection([]))->when('foo', $callback, $default)->when('', $callback, $default);
        $this->assertSame([''], $c->all());
    }

    public function testUnless()
    {
        $c = (new Collection([]))
            ->unless(true, fn (Collection $collection) => $collection->push(1))
            ->unless(false, fn (Collection $collection) => $collection->push(2))
            ->unless(null, fn (Collection $collection) => $collection->push(3))
            ->unless('', fn (Collection $collection) => $collection->push(4))
            ->unless([], fn (Collection $collection) => $collection->push(5))
            ->unless(0, fn (Collection $collection) => $collection->push(6));

        $this->assertSame([2, 3, 4, 5, 6], $c->all());
    }

    public function testUnlessWithValueForCallback(): void
    {
        $callback = fn (Collection $collection, string $value) => $collection->push($value);

        $c = (new Collection([]))
            ->unless('foo', $callback)
            ->unless('', $callback);

        $this->assertSame([''], $c->all());
    }

    public function testUnlessValueOfClosure(): void
    {
        $callback = fn (Collection $collection, $value) => $collection->push($value);

        $c = (new Collection([]))->unless(fn () => 'foo', $callback)->unless(fn () => '', $callback);
        $this->assertSame([''], $c->all());

        $c = (new Collection([1, 2]))->unless(
            fn (Collection $collection) => $collection->shift(),
            $callback
        );
        $this->assertSame([2], $c->all());
    }

    public function testUnlessCallbackWithDefault(): void
    {
        $callback = fn (Collection $collection, $value) => $collection;
        $default = fn (Collection $collection, $value) => $collection->push($value);

        $c = (new Collection([]))->unless('foo', $callback, $default)->unless('', $callback, $default);
        $this->assertSame(['foo'], $c->all());
    }

    /**
     * Provides each collection class, respectively.
     *
     * @return array
     */
    public static function collectionClassProvider()
    {
        return [
            [Collection::class],
            [LazyCollection::class],
        ];
    }

    #[DataProvider('collectionClassProvider')]
    public function testSoleReturnsFirstItemInCollectionIfOnlyOneExists($collection): void
    {
        $collection = new $collection([
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);

        $this->assertSame(['name' => 'foo'], $collection->where('name', 'foo')->sole());
        $this->assertSame(['name' => 'foo'], $collection->sole('name', '=', 'foo'));
        $this->assertSame(['name' => 'foo'], $collection->sole('name', 'foo'));
    }

    #[DataProvider('collectionClassProvider')]
    public function testSoleThrowsExceptionIfMoreThanOneItemExists($collection)
    {
        $this->expectExceptionObject(new MultipleItemsFoundException(2));

        $collection = new $collection([
            ['name' => 'foo'],
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);

        $collection->where('name', 'foo')->sole();
    }

    #[DataProvider('collectionClassProvider')]
    public function testSoleReturnsFirstItemInCollectionIfOnlyOneExistsWithCallback($collection)
    {
        $data = new $collection(['foo', 'bar', 'baz']);
        $result = $data->sole(function ($value) {
            return $value === 'bar';
        });
        $this->assertSame('bar', $result);
    }

    #[DataProvider('collectionClassProvider')]
    public function testSoleThrowsExceptionIfNoItemsExistWithCallback($collection)
    {
        $this->expectException(ItemNotFoundException::class);

        $data = new $collection(['foo', 'bar', 'baz']);

        $data->sole(function ($value) {
            return $value === 'invalid';
        });
    }

    #[DataProvider('collectionClassProvider')]
    public function testSoleThrowsExceptionIfMoreThanOneItemExistsWithCallback($collection)
    {
        $this->expectExceptionObject(new MultipleItemsFoundException(2));

        $data = new $collection(['foo', 'bar', 'bar']);

        $data->sole(function ($value) {
            return $value === 'bar';
        });
    }

    #[DataProvider('collectionClassProvider')]
    public function testFirstOrFailReturnsFirstItemInCollection($collection)
    {
        $collection = new $collection([
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);

        $this->assertSame(['name' => 'foo'], $collection->where('name', 'foo')->firstOrFail());
        $this->assertSame(['name' => 'foo'], $collection->firstOrFail('name', '=', 'foo'));
        $this->assertSame(['name' => 'foo'], $collection->firstOrFail('name', 'foo'));
    }

    #[DataProvider('collectionClassProvider')]
    public function testFirstOrFailThrowsExceptionIfNoItemsExist($collection)
    {
        $this->expectException(ItemNotFoundException::class);

        $collection = new $collection([
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);

        $collection->where('name', 'INVALID')->firstOrFail();
    }

    #[DataProvider('collectionClassProvider')]
    public function testFirstOrFailDoesntThrowExceptionIfMoreThanOneItemExists($collection)
    {
        $collection = new $collection([
            ['name' => 'foo'],
            ['name' => 'foo'],
            ['name' => 'bar'],
        ]);

        $this->assertSame(['name' => 'foo'], $collection->where('name', 'foo')->firstOrFail());
    }

    #[DataProvider('collectionClassProvider')]
    public function testFirstOrFailReturnsFirstItemInCollectionIfOnlyOneExistsWithCallback($collection)
    {
        $data = new $collection(['foo', 'bar', 'baz']);
        $result = $data->firstOrFail(function ($value) {
            return $value === 'bar';
        });
        $this->assertSame('bar', $result);
    }

    #[DataProvider('collectionClassProvider')]
    public function testFirstOrFailThrowsExceptionIfNoItemsExistWithCallback($collection)
    {
        $this->expectException(ItemNotFoundException::class);

        $data = new $collection(['foo', 'bar', 'baz']);

        $data->firstOrFail(function ($value) {
            return $value === 'invalid';
        });
    }

    #[DataProvider('collectionClassProvider')]
    public function testFirstOrFailDoesntThrowExceptionIfMoreThanOneItemExistsWithCallback($collection)
    {
        $data = new $collection(['foo', 'bar', 'bar']);

        $this->assertSame(
            'bar',
            $data->firstOrFail(function ($value) {
                return $value === 'bar';
            })
        );
    }

    #[DataProvider('collectionClassProvider')]
    public function testFirstOrFailStopsIteratingAtFirstMatch($collection)
    {
        $data = new $collection([
            function () {
                return false;
            },
            function () {
                return true;
            },
            function () {
                throw new Exception();
            },
        ]);

        $this->assertNotNull($data->firstOrFail(function ($callback) {
            return $callback();
        }));
    }

    #[DataProvider('collectionClassProvider')]
    public function testJoin($collection)
    {
        $this->assertSame('a, b, c', (new $collection(['a', 'b', 'c']))->join(', '));

        $this->assertSame('a, b and c', (new $collection(['a', 'b', 'c']))->join(', ', ' and '));

        $this->assertSame('a and b', (new $collection(['a', 'b']))->join(', ', ' and '));

        $this->assertSame('a', (new $collection(['a']))->join(', ', ' and '));

        $this->assertSame('', (new $collection([]))->join(', ', ' and '));
    }

    #[DataProvider('collectionClassProvider')]
    public function testCrossJoin($collection)
    {
        // Cross join with an array
        $this->assertEquals(
            [[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']],
            (new $collection([1, 2]))->crossJoin(['a', 'b'])->all()
        );

        // Cross join with a collection
        $this->assertEquals(
            [[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']],
            (new $collection([1, 2]))->crossJoin(new $collection(['a', 'b']))->all()
        );

        // Cross join with 2 collections
        $this->assertEquals(
            [
                [1, 'a', 'I'], [1, 'a', 'II'],
                [1, 'b', 'I'], [1, 'b', 'II'],
                [2, 'a', 'I'], [2, 'a', 'II'],
                [2, 'b', 'I'], [2, 'b', 'II'],
            ],
            (new $collection([1, 2]))->crossJoin(
                new $collection(['a', 'b']),
                new $collection(['I', 'II'])
            )->all()
        );
    }

    #[DataProvider('collectionClassProvider')]
    public function testIntersectAssocUsingWithNull($collection)
    {
        $array1 = new $collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);

        $this->assertEquals([], $array1->intersectAssocUsing(null, 'strcasecmp')->all());
    }

    #[DataProvider('collectionClassProvider')]
    public function testIntersectAssocUsingCollection($collection)
    {
        $array1 = new $collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);
        $array2 = new $collection(['a' => 'GREEN', 'B' => 'brown', 'yellow', 'red']);

        $this->assertEquals(['b' => 'brown'], $array1->intersectAssocUsing($array2, 'strcasecmp')->all());
    }

    #[DataProvider('collectionClassProvider')]
    public function testIntersectUsingWithNull($collection)
    {
        $collect = new $collection(['green', 'brown', 'blue']);

        $this->assertEquals([], $collect->intersectUsing(null, 'strcasecmp')->all());
    }

    #[DataProvider('collectionClassProvider')]
    public function testIntersectUsingCollection($collection)
    {
        $collect = new $collection(['green', 'brown', 'blue']);

        $this->assertEquals(['green', 'brown'], $collect->intersectUsing(new $collection(['GREEN', 'brown', 'yellow']), 'strcasecmp')->all());
    }

    #[DataProvider('collectionClassProvider')]
    public function testDuplicatesWithStrict($collection)
    {
        $duplicates = $collection::make([1, 2, 1, 'laravel', null, 'laravel', 'php', null])->duplicatesStrict()->all();
        $this->assertSame([2 => 1, 5 => 'laravel', 7 => null], $duplicates);

        // does strict comparison
        $duplicates = $collection::make([2, '2', [], null])->duplicatesStrict()->all();
        $this->assertSame([], $duplicates);

        // works with mix of primitives
        $duplicates = $collection::make([1, '2', ['laravel'], ['laravel'], null, '2'])->duplicatesStrict()->all();
        $this->assertSame([3 => ['laravel'], 5 => '2'], $duplicates);

        // works with mix of primitives, objects, and numbers
        $expected = new $collection(['laravel']);
        $duplicates = $collection::make([new $collection(['laravel']), $expected, $expected, [], '2', '2'])->duplicatesStrict()->all();
        $this->assertSame([2 => $expected, 5 => '2'], $duplicates);
    }

    public function testGetOrPut()
    {
        $data = new Collection(['name' => 'taylor', 'email' => 'foo']);

        $this->assertSame('taylor', $data->getOrPut('name', null));
        $this->assertSame('foo', $data->getOrPut('email', null));
        $this->assertSame('male', $data->getOrPut('gender', 'male'));

        $this->assertSame('taylor', $data->get('name'));
        $this->assertSame('foo', $data->get('email'));
        $this->assertSame('male', $data->get('gender'));

        $data = new Collection(['name' => 'taylor', 'email' => 'foo']);

        $this->assertSame('taylor', $data->getOrPut('name', function () {
            return null;
        }));

        $this->assertSame('foo', $data->getOrPut('email', function () {
            return null;
        }));

        $this->assertSame('male', $data->getOrPut('gender', function () {
            return 'male';
        }));

        $this->assertSame('taylor', $data->get('name'));
        $this->assertSame('foo', $data->get('email'));
        $this->assertSame('male', $data->get('gender'));
    }

    #[DataProvider('collectionClassProvider')]
    public function testBeforeReturnsItemBeforeTheGivenItem($collection)
    {
        $c = new $collection([1, 2, 3, 4, 5, 2, 5, 'name' => 'taylor', 'framework' => 'laravel']);

        $this->assertEquals(1, $c->before(2));
        $this->assertEquals(1, $c->before('2'));
        $this->assertEquals(5, $c->before('taylor'));
        $this->assertSame('taylor', $c->before('laravel'));
        $this->assertEquals(4, $c->before(function ($value) {
            return $value > 4;
        }));
        $this->assertEquals(5, $c->before(function ($value) {
            return ! is_numeric($value);
        }));
    }

    #[DataProvider('collectionClassProvider')]
    public function testBeforeInStrictMode($collection)
    {
        $c = new $collection([false, 0, 1, [], '']);
        $this->assertNull($c->before('false', true));
        $this->assertNull($c->before('1', true));
        $this->assertNull($c->before(false, true));
        $this->assertEquals(false, $c->before(0, true));
        $this->assertEquals(0, $c->before(1, true));
        $this->assertEquals(1, $c->before([], true));
        $this->assertEquals([], $c->before('', true));
    }

    #[DataProvider('collectionClassProvider')]
    public function testBeforeReturnsNullWhenItemIsNotFound($collection)
    {
        $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

        $this->assertNull($c->before(6));
        $this->assertNull($c->before('foo'));
        $this->assertNull($c->before(function ($value) {
            return $value < 1 && is_numeric($value);
        }));
        $this->assertNull($c->before(function ($value) {
            return $value === 'nope';
        }));
    }

    #[DataProvider('collectionClassProvider')]
    public function testBeforeReturnsNullWhenItemOnTheFirstitem($collection)
    {
        $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

        $this->assertNull($c->before(1));
        $this->assertNull($c->before(function ($value) {
            return $value < 2 && is_numeric($value);
        }));

        $c = new $collection(['foo' => 'bar', 1, 2, 3, 4, 5]);
        $this->assertNull($c->before('bar'));
    }

    #[DataProvider('collectionClassProvider')]
    public function testAfterReturnsItemAfterTheGivenItem($collection)
    {
        $c = new $collection([1, 2, 3, 4, 2, 5, 'name' => 'taylor', 'framework' => 'laravel']);

        $this->assertEquals(2, $c->after(1));
        $this->assertEquals(3, $c->after(2));
        $this->assertEquals(4, $c->after(3));
        $this->assertEquals(2, $c->after(4));
        $this->assertEquals('taylor', $c->after(5));
        $this->assertEquals('laravel', $c->after('taylor'));

        $this->assertEquals(4, $c->after(function ($value) {
            return $value > 2;
        }));
        $this->assertEquals('laravel', $c->after(function ($value) {
            return ! is_numeric($value);
        }));
    }

    #[DataProvider('collectionClassProvider')]
    public function testAfterInStrictMode($collection)
    {
        $c = new $collection([false, 0, 1, [], '']);

        $this->assertNull($c->after('false', true));
        $this->assertNull($c->after('1', true));
        $this->assertNull($c->after('', true));
        $this->assertEquals(0, $c->after(false, true));
        $this->assertEquals([], $c->after(1, true));
        $this->assertEquals('', $c->after([], true));
    }

    #[DataProvider('collectionClassProvider')]
    public function testAfterReturnsNullWhenItemIsNotFound($collection)
    {
        $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

        $this->assertNull($c->after(6));
        $this->assertNull($c->after('foo'));
        $this->assertNull($c->after(function ($value) {
            return $value < 1 && is_numeric($value);
        }));
        $this->assertNull($c->after(function ($value) {
            return $value === 'nope';
        }));
    }

    #[DataProvider('collectionClassProvider')]
    public function testAfterReturnsNullWhenItemOnTheLastItem($collection)
    {
        $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

        $this->assertNull($c->after('bar'));
        $this->assertNull($c->after(function ($value) {
            return $value > 4 && ! is_numeric($value);
        }));

        $c = new $collection(['foo' => 'bar', 1, 2, 3, 4, 5]);
        $this->assertNull($c->after(5));
    }

    #[DataProvider('collectionClassProvider')]
    public function testSortBy($collection)
    {
        $data = (new $collection(
            [
                ['id' => 5, 'name' => 'e'],
                ['id' => 4, 'name' => 'd'],
                ['id' => 3, 'name' => 'c'],
                ['id' => 2, 'name' => 'b'],
                ['id' => 1, 'name' => 'a'],
            ]
        ))->sortBy('id');
        $this->assertEquals(json_encode([
            4 => ['id' => 1, 'name' => 'a'],
            3 => ['id' => 2, 'name' => 'b'],
            2 => ['id' => 3, 'name' => 'c'],
            1 => ['id' => 4, 'name' => 'd'],
            0 => ['id' => 5, 'name' => 'e'],
        ]), (string) $data);

        $this->assertEquals(json_encode([
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
            ['id' => 3, 'name' => 'c'],
            ['id' => 4, 'name' => 'd'],
            ['id' => 5, 'name' => 'e'],
        ]), (string) $data->values());
        $dataMany = (new $collection(
            [
                ['id' => 5, 'name' => 'e'],
                ['id' => 4, 'name' => 'd'],
                ['id' => 3, 'name' => 'c'],
                ['id' => 2, 'name' => 'b'],
                ['id' => 1, 'name' => 'a'],
            ]
        ))->sortBy(['id', 'asc']);
        $this->assertEquals((string) $data->values(), (string) $dataMany);

        $data = (new $collection(
            [
                ['id' => 5, 'name' => '5a'],
                ['id' => 4, 'name' => '4b'],
                ['id' => 3, 'name' => 'c3'],
                ['id' => 2, 'name' => '2d'],
                ['id' => 1, 'name' => '1e'],
            ]
        ))->sortBy('name', SORT_NUMERIC);
        $this->assertEquals(json_encode([
            2 => ['id' => 3, 'name' => 'c3'],
            4 => ['id' => 1, 'name' => '1e'],
            3 => ['id' => 2, 'name' => '2d'],
            1 => ['id' => 4, 'name' => '4b'],
            0 => ['id' => 5, 'name' => '5a'],
        ]), (string) $data);
        $dataMany = (new $collection(
            [
                ['id' => 5, 'name' => '5a'],
                ['id' => 4, 'name' => '4b'],
                ['id' => 3, 'name' => 'c3'],
                ['id' => 2, 'name' => '2d'],
                ['id' => 1, 'name' => '1e'],
            ]
        ))->sortBy([['name', 'asc']], SORT_NUMERIC);
        $this->assertEquals((string) $data->values(), (string) $dataMany);

        $data = (new $collection(
            [
                ['id' => 5, 'name' => '5a'],
                ['id' => 4, 'name' => '4b'],
                ['id' => 3, 'name' => 'c3'],
                ['id' => 2, 'name' => '2d'],
                ['id' => 1, 'name' => '1e'],
            ]
        ))->sortBy('name', SORT_STRING);
        $this->assertEquals(json_encode([
            4 => ['id' => 1, 'name' => '1e'],
            3 => ['id' => 2, 'name' => '2d'],
            1 => ['id' => 4, 'name' => '4b'],
            0 => ['id' => 5, 'name' => '5a'],
            2 => ['id' => 3, 'name' => 'c3'],
        ]), (string) $data);
        $dataMany = (new $collection(
            [
                ['id' => 5, 'name' => '5a'],
                ['id' => 4, 'name' => '4b'],
                ['id' => 3, 'name' => 'c3'],
                ['id' => 2, 'name' => '2d'],
                ['id' => 1, 'name' => '1e'],
            ]
        ))->sortBy([['name', 'asc']], SORT_STRING);
        $this->assertEquals((string) $data->values(), (string) $dataMany);

        $data = (new $collection(
            [
                ['id' => 5, 'name' => 'a10'],
                ['id' => 4, 'name' => 'a4'],
                ['id' => 3, 'name' => 'a3'],
                ['id' => 2, 'name' => 'a2'],
                ['id' => 1, 'name' => 'a1'],
            ]
        ))->sortBy('name', SORT_NATURAL);
        $this->assertEquals(json_encode([
            4 => ['id' => 1, 'name' => 'a1'],
            3 => ['id' => 2, 'name' => 'a2'],
            2 => ['id' => 3, 'name' => 'a3'],
            1 => ['id' => 4, 'name' => 'a4'],
            0 => ['id' => 5, 'name' => 'a10'],
        ]), (string) $data);
        $dataMany = (new $collection(
            [
                ['id' => 5, 'name' => 'a10'],
                ['id' => 4, 'name' => 'a4'],
                ['id' => 3, 'name' => 'a3'],
                ['id' => 2, 'name' => 'a2'],
                ['id' => 1, 'name' => 'a1'],
            ]
        ))->sortBy([['name', 'asc']], SORT_NATURAL);
        $this->assertEquals((string) $data->values(), (string) $dataMany);

        setlocale(LC_COLLATE, 'en_US.utf8');
        $data = (new $collection(
            [
                ['id' => 5, 'name' => 'A'],
                ['id' => 4, 'name' => 'a'],
                ['id' => 3, 'name' => 'B'],
                ['id' => 2, 'name' => 'b'],
                ['id' => 1, 'name' => 'c'],
            ]
        ))->sortBy('name', SORT_LOCALE_STRING);
        $this->assertEquals(json_encode([
            1 => ['id' => 4, 'name' => 'a'],
            0 => ['id' => 5, 'name' => 'A'],
            3 => ['id' => 2, 'name' => 'b'],
            2 => ['id' => 3, 'name' => 'B'],
            4 => ['id' => 1, 'name' => 'c'],
        ]), (string) $data);
        $dataMany = (new $collection(
            [
                ['id' => 5, 'name' => 'A'],
                ['id' => 4, 'name' => 'a'],
                ['id' => 3, 'name' => 'B'],
                ['id' => 2, 'name' => 'b'],
                ['id' => 1, 'name' => 'c'],
            ]
        ))->sortBy([['name', 'asc']], SORT_LOCALE_STRING);
        $this->assertEquals((string) $data->values(), (string) $dataMany);

        $data = (new $collection(
            [
                ['id' => 1, 'name' => 'a'],
                ['id' => 2, 'name' => 'b'],
                ['id' => 3, 'name' => 'c'],
                ['id' => 4, 'name' => 'd'],
                ['id' => 5, 'name' => 'e'],
            ]
        ))->sortByDesc('id');
        $this->assertEquals(json_encode([
            4 => ['id' => 5, 'name' => 'e'],
            3 => ['id' => 4, 'name' => 'd'],
            2 => ['id' => 3, 'name' => 'c'],
            1 => ['id' => 2, 'name' => 'b'],
            0 => ['id' => 1, 'name' => 'a'],
        ]), (string) $data);
        $this->assertEquals(json_encode([
            ['id' => 5, 'name' => 'e'],
            ['id' => 4, 'name' => 'd'],
            ['id' => 3, 'name' => 'c'],
            ['id' => 2, 'name' => 'b'],
            ['id' => 1, 'name' => 'a'],
        ]), (string) $data->values());
        $dataMany = (new $collection(
            [
                ['id' => 1, 'name' => 'a'],
                ['id' => 2, 'name' => 'b'],
                ['id' => 3, 'name' => 'c'],
                ['id' => 4, 'name' => 'd'],
                ['id' => 5, 'name' => 'e'],
            ]
        ))->sortByDesc(['id']);
        $this->assertEquals((string) $data->values(), (string) $dataMany);

        $dataMany = (new $collection(
            [
                'a' => ['id' => 1, 'name' => 'a'],
                'b' => ['id' => 2, 'name' => 'b'],
                'c' => ['id' => 3, 'name' => 'c'],
                'd' => ['id' => 4, 'name' => 'd'],
                'e' => ['id' => 5, 'name' => 'e'],
            ]
        ))->sortByDesc(['id']);
        $this->assertEquals(Json::encode([
            'e' => ['id' => 5, 'name' => 'e'],
            'd' => ['id' => 4, 'name' => 'd'],
            'c' => ['id' => 3, 'name' => 'c'],
            'b' => ['id' => 2, 'name' => 'b'],
            'a' => ['id' => 1, 'name' => 'a'],
        ]), (string) $dataMany);

        $dataMany = (new $collection(
            [
                'e' => ['id' => 5, 'name' => 'e'],
                'd' => ['id' => 4, 'name' => 'd'],
                'c' => ['id' => 3, 'name' => 'c'],
                'b' => ['id' => 2, 'name' => 'b'],
                'a' => ['id' => 1, 'name' => 'a'],
            ]
        ))->sortBy(['id']);
        $this->assertEquals(Json::encode([
            'a' => ['id' => 1, 'name' => 'a'],
            'b' => ['id' => 2, 'name' => 'b'],
            'c' => ['id' => 3, 'name' => 'c'],
            'd' => ['id' => 4, 'name' => 'd'],
            'e' => ['id' => 5, 'name' => 'e'],
        ]), (string) $dataMany);

        $dataMany = (new $collection(
            [
                'e' => ['id' => 5, 'name' => 'e'],
                'd' => ['id' => 4, 'name' => 'd'],
                'c' => ['id' => 3, 'name' => 'c'],
                'b' => ['id' => 2, 'name' => 'b'],
                'a' => ['id' => 1, 'name' => 'a'],
            ]
        ))->sortBy('id');
        $this->assertEquals(Json::encode([
            'a' => ['id' => 1, 'name' => 'a'],
            'b' => ['id' => 2, 'name' => 'b'],
            'c' => ['id' => 3, 'name' => 'c'],
            'd' => ['id' => 4, 'name' => 'd'],
            'e' => ['id' => 5, 'name' => 'e'],
        ]), (string) $dataMany);
    }
}
