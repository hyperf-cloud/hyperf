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

use HyperfTest\Database\Stubs\AttributeCastAddress;
use HyperfTest\Database\Stubs\TestModelWithAttributeCast;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\now;

/**
 * @internal
 * @coversNothing
 */
class DatabaseModelAttributeCastingTest extends TestCase
{
    public function testBasicCustomCasting()
    {
        $model = new TestModelWithAttributeCast();
        $model->uppercase = 'taylor';

        $this->assertSame('TAYLOR', $model->uppercase);
        $this->assertSame('TAYLOR', $model->getAttributes()['uppercase']);
        $this->assertSame('TAYLOR', $model->toArray()['uppercase']);

        $unserializedModel = unserialize(serialize($model));

        $this->assertSame('TAYLOR', $unserializedModel->uppercase);
        $this->assertSame('TAYLOR', $unserializedModel->getAttributes()['uppercase']);
        $this->assertSame('TAYLOR', $unserializedModel->toArray()['uppercase']);

        $model->syncOriginal();
        $model->uppercase = 'dries';
        $this->assertSame('TAYLOR', $model->getOriginal('uppercase'));

        $model = new TestModelWithAttributeCast();
        $model->uppercase = 'taylor';
        $model->syncOriginal();
        $model->uppercase = 'dries';
        $model->getOriginal();

        $this->assertSame('DRIES', $model->uppercase);

        $model = $model->setAttribute('uppercase', 'james');

        $this->assertInstanceOf(TestModelWithAttributeCast::class, $model);

        $model = new TestModelWithAttributeCast();

        $model->address = $address = new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House');
        $address->lineOne = '117 Spencer St.';
        $this->assertSame('117 Spencer St.', $model->getAttributes()['address_line_one']);

        $model = new TestModelWithAttributeCast();

        $model->setRawAttributes([
            'address_line_one' => '110 Kingsbrook St.',
            'address_line_two' => 'My Childhood House',
        ]);

        $this->assertSame('110 Kingsbrook St.', $model->address->lineOne);
        $this->assertSame('My Childhood House', $model->address->lineTwo);

        $this->assertSame('110 Kingsbrook St.', $model->toArray()['address_line_one']);
        $this->assertSame('My Childhood House', $model->toArray()['address_line_two']);

        $model->address->lineOne = '117 Spencer St.';

        $this->assertFalse(isset($model->toArray()['address']));
        $this->assertSame('117 Spencer St.', $model->toArray()['address_line_one']);
        $this->assertSame('My Childhood House', $model->toArray()['address_line_two']);

        $this->assertSame('117 Spencer St.', json_decode($model->toJson(), true)['address_line_one']);
        $this->assertSame('My Childhood House', json_decode($model->toJson(), true)['address_line_two']);

        $model->address = null;

        $this->assertNull($model->toArray()['address_line_one']);
        $this->assertNull($model->toArray()['address_line_two']);

        $model->options = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $model->options = ['foo' => 'bar'];
        $model->options = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $model->options);
        $this->assertEquals(['foo' => 'bar'], $model->options);

        $this->assertSame(json_encode(['foo' => 'bar']), $model->getAttributes()['options']);

        $model = new TestModelWithAttributeCast(['options' => []]);
        $model->syncOriginal();
        $model->options = ['foo' => 'bar'];
        $this->assertTrue($model->isDirty('options'));

        $model = new TestModelWithAttributeCast();
        $model->birthday_at = now();
        $this->assertIsString($model->toArray()['birthday_at']);
    }

    public function testGetOriginalWithCastValueObjects()
    {
        $model = new TestModelWithAttributeCast([
            'address' => new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House'),
        ]);

        $model->syncOriginal();

        $model->address = new AttributeCastAddress('117 Spencer St.', 'Another house.');

        $this->assertSame('117 Spencer St.', $model->address->lineOne);
        $this->assertSame('110 Kingsbrook St.', $model->getOriginal('address')->lineOne);
        $this->assertSame('117 Spencer St.', $model->address->lineOne);

        $model = new TestModelWithAttributeCast([
            'address' => new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House'),
        ]);

        $model->syncOriginal();

        $model->address = new AttributeCastAddress('117 Spencer St.', 'Another house.');

        $this->assertSame('117 Spencer St.', $model->address->lineOne);
        $this->assertSame('110 Kingsbrook St.', $model->getOriginal()['address_line_one']);
        $this->assertSame('117 Spencer St.', $model->address->lineOne);
        $this->assertSame('110 Kingsbrook St.', $model->getOriginal()['address_line_one']);

        $model = new TestModelWithAttributeCast([
            'address' => new AttributeCastAddress('110 Kingsbrook St.', 'My Childhood House'),
        ]);

        $model->syncOriginal();

        $model->address = null;

        $this->assertNull($model->address);
        $this->assertInstanceOf(AttributeCastAddress::class, $model->getOriginal('address'));
        $this->assertNull($model->address);
    }

    public function testOneWayCasting()
    {
        $model = new TestModelWithAttributeCast();

        $this->assertNull($model->password);

        $model->password = 'secret';

        $this->assertEquals(hash('sha256', 'secret'), $model->password);
        $this->assertEquals(hash('sha256', 'secret'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret'), $model->password);

        $model->password = 'secret2';

        $this->assertEquals(hash('sha256', 'secret2'), $model->password);
        $this->assertEquals(hash('sha256', 'secret2'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret2'), $model->getAttributes()['password']);
        $this->assertEquals(hash('sha256', 'secret2'), $model->password);
    }

    public function testSettingRawAttributesClearsTheCastCache()
    {
        $model = new TestModelWithAttributeCast();

        $model->setRawAttributes([
            'address_line_one' => '110 Kingsbrook St.',
            'address_line_two' => 'My Childhood House',
        ]);

        $this->assertSame('110 Kingsbrook St.', $model->address->lineOne);

        $model->setRawAttributes([
            'address_line_one' => '117 Spencer St.',
            'address_line_two' => 'My Childhood House',
        ]);
        $this->assertSame('117 Spencer St.', $model->address->lineOne);
    }

    public function testCastsThatOnlyHaveGetterDoNotPeristAnythingToModelOnSave()
    {
        $model = new TestModelWithAttributeCast();

        $model->virtual;

        $model->getAttributes();

        $this->assertEmpty($model->getDirty());
    }

    public function testCastsThatOnlyHaveGetterThatReturnsPrimitivesAreNotCached()
    {
        $model = new TestModelWithAttributeCast();

        $previous = null;

        foreach (range(0, 10) as $ignored) {
            $this->assertNotSame($previous, $previous = $model->virtualString);
        }
    }

    public function testAttributesCanCacheStrings()
    {
        $model = new TestModelWithAttributeCast();

        $previous = $model->virtual_string_cached;

        $this->assertIsString($previous);

        $this->assertSame($previous, $model->virtual_string_cached);
    }

    public function testAttributesCanCacheBooleans()
    {
        $model = new TestModelWithAttributeCast();

        $first = $model->virtual_boolean_cached;

        $this->assertIsBool($first);

        foreach (range(0, 10) as $ignored) {
            $this->assertSame($first, $model->virtual_boolean_cached);
        }
    }

    public function testAttributesCanCacheNull()
    {
        $model = new TestModelWithAttributeCast();

        $this->assertSame(0, $model->virtualNullCalls);

        $first = $model->virtual_null_cached;

        $this->assertNull($first);

        $this->assertSame(1, $model->virtualNullCalls);

        foreach (range(0, 10) as $ignored) {
            $this->assertSame($first, $model->virtual_null_cached);
        }

        $this->assertSame(1, $model->virtualNullCalls);
    }

    public function testAttributesByDefaultDontCacheBooleans()
    {
        $model = new TestModelWithAttributeCast();

        $first = $model->virtual_boolean;

        $this->assertIsBool($first);

        foreach (range(0, 50) as $ignored) {
            $current = $model->virtual_boolean;

            $this->assertIsBool($current);

            if ($first !== $current) {
                return;
            }
        }

        $this->fail('"virtual_boolean" seems to be cached.');
    }

    public function testCastsThatOnlyHaveGetterThatReturnsObjectAreCached()
    {
        $model = new TestModelWithAttributeCast();

        $previous = $model->virtualObject;

        foreach (range(0, 10) as $ignored) {
            $this->assertSame($previous, $previous = $model->virtualObject);
        }
    }

    public function testCastsThatOnlyHaveGetterThatReturnsDateTimeAreCached()
    {
        $model = new TestModelWithAttributeCast();

        $previous = $model->virtualDateTime;

        foreach (range(0, 10) as $ignored) {
            $this->assertSame($previous, $previous = $model->virtualDateTime);
        }
    }

    public function testCastsThatOnlyHaveGetterThatReturnsObjectAreNotCached()
    {
        $model = new TestModelWithAttributeCast();

        $previous = $model->virtualObjectWithoutCaching;

        foreach (range(0, 10) as $ignored) {
            $this->assertNotSame($previous, $previous = $model->virtualObjectWithoutCaching);
        }
    }

    public function testCastsThatOnlyHaveGetterThatReturnsDateTimeAreNotCached()
    {
        $model = new TestModelWithAttributeCast();

        $previous = $model->virtualDateTimeWithoutCaching;

        foreach (range(0, 10) as $ignored) {
            $this->assertNotSame($previous, $previous = $model->virtualDateTimeWithoutCaching);
        }
    }

    public function testCastsThatOnlyHaveGetterThatReturnsObjectAreNotCachedFluent()
    {
        $model = new TestModelWithAttributeCast();

        $previous = $model->virtualObjectWithoutCachingFluent;

        foreach (range(0, 10) as $ignored) {
            $this->assertNotSame($previous, $previous = $model->virtualObjectWithoutCachingFluent);
        }
    }

    public function testCastsThatOnlyHaveGetterThatReturnsDateTimeAreNotCachedFluent()
    {
        $model = new TestModelWithAttributeCast();

        $previous = $model->virtualDateTimeWithoutCachingFluent;

        foreach (range(0, 10) as $ignored) {
            $this->assertNotSame($previous, $previous = $model->virtualDateTimeWithoutCachingFluent);
        }
    }
}
