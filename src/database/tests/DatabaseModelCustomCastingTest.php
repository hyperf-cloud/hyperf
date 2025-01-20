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

use Hyperf\Collection\Arr;
use Hyperf\Contract\Castable;
use Hyperf\Contract\CastsAttributes;
use Hyperf\Contract\CastsInboundAttributes;
use Hyperf\Database\Exception\InvalidCastException;
use Hyperf\Database\Model\CastsValue;
use Hyperf\Database\Model\Model;
use HyperfTest\Database\Stubs\ContainerStub;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class DatabaseModelCustomCastingTest extends TestCase
{
    protected function setUp(): void
    {
        ContainerStub::unsetContainer();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        UserInfoCaster::$setCount = 0;
        UserInfoCaster::$getCount = 0;
    }

    public function testBasicCustomCasting()
    {
        $model = new TestModelWithCustomCast();
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
        $this->assertEquals('TAYLOR', $model->getOriginal('uppercase'));

        $model = new TestModelWithCustomCast();
        $model->uppercase = 'taylor';
        $model->syncOriginal();
        $model->uppercase = 'dries';
        $model->getOriginal();

        $this->assertEquals('DRIES', $model->uppercase);

        $model = new TestModelWithCustomCast();

        $model->address = $address = new Address('110 Kingsbrook St.', 'My Childhood House');
        $address->lineOne = '117 Spencer St.';
        $this->assertSame('117 Spencer St.', $model->syncAttributes()->getAttributes()['address_line_one']);

        $model = new TestModelWithCustomCast();

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

        $this->assertEquals(json_encode(['foo' => 'bar']), $model->getAttributes()['options']);

        $model = new TestModelWithCustomCast(['options' => []]);
        $model->syncOriginal();
        $model->options = ['foo' => 'bar'];
        $this->assertTrue($model->isDirty('options'));
    }

    public function testOneWayCasting()
    {
        // CastsInboundAttributes is used for casting that is unidirectional... only use case I can think of is one-way hashing...
        $model = new TestModelWithCustomCast();

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
        $model = new TestModelWithCustomCast();

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

    public function testWithCastableInterface()
    {
        $model = new TestModelWithCustomCast();

        $model->setRawAttributes([
            'value_object_with_caster' => serialize(new ValueObject('hello')),
        ]);

        $this->assertInstanceOf(ValueObject::class, $model->value_object_with_caster);

        $model->setRawAttributes([
            'value_object_caster_with_argument' => null,
        ]);

        $this->assertEquals('argument', $model->value_object_caster_with_argument);

        $model->setRawAttributes([
            'value_object_caster_with_caster_instance' => serialize(new ValueObject('hello')),
        ]);

        $this->assertInstanceOf(ValueObject::class, $model->value_object_caster_with_caster_instance);
    }

    public function testGetAttribute()
    {
        $model = new TestModelWithCustomCast();
        $model->mergeCasts([
            'mockery' => MockeryAttribute::class,
        ]);
        $mockery = Mockery::mock(CastsAttributes::class);
        $mockery->shouldReceive('get')->withAnyArgs()->andReturn(function ($_, $key, $value, $attributes) {
            $obj = new stdClass();
            $obj->value = $attributes[$key . '_origin'] - 1;

            return $obj;
        });
        $mockery->shouldReceive('set')->withAnyArgs()->andReturnUsing(function ($_, $key, $value, $attributes) {
            return [
                $key . '_origin' => $value->value + 1,
            ];
        });
        MockeryAttribute::$attribute = $mockery;

        $std = new stdClass();
        $std->value = 1;
        $model->mockery = $std;

        $this->assertSame(1, $model->mockery->value);
    }

    public function testResolveCasterClass()
    {
        $model = new TestModelWithCustomCast();
        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('resolveCasterClass');
        CastUsing::$castsAttributes = UppercaseCaster::class;
        $this->assertNotSame($method->invokeArgs($model, ['cast_using']), $method->invokeArgs($model, ['cast_using']));

        CastUsing::$castsAttributes = new UppercaseCaster();
        $this->assertSame($method->invokeArgs($model, ['cast_using']), $method->invokeArgs($model, ['cast_using']));
    }

    public function testIsSynchronized()
    {
        $model = new TestModelWithCustomCast();
        $model->user = $user = new UserInfo($model, ['name' => 'Hyperf', 'gender' => 1]);
        $model->syncOriginal();

        $attributes = $model->getAttributes();
        $this->assertSame(['name' => 'Hyperf', 'gender' => 1], Arr::only($attributes, ['name', 'gender']));

        $user->name = 'Nano';
        $attributes = $model->getAttributes();
        $this->assertSame(['name' => 'Nano', 'gender' => 1], Arr::only($attributes, ['name', 'gender']));
        $this->assertSame(['name' => 'Nano'], $model->getDirty());
        $this->assertSame(5, UserInfoCaster::$setCount);
        $this->assertSame(0, UserInfoCaster::$getCount);
    }

    public function testCastsValueSupportNull()
    {
        $model = new TestModelWithCustomCast();
        $model->user = $user = new UserInfo($model, ['name' => 'Hyperf', 'gender' => 1]);
        $attributes = $model->getAttributes();
        $this->assertSame(['name' => 'Hyperf', 'gender' => 1, 'role_id' => 0], $attributes);
        $this->assertSame(0, $user->role_id);
        $user->role_id = 1;
        $this->assertSame(['name' => 'Hyperf', 'gender' => 1, 'role_id' => 1], $model->getAttributes());
        unset($user->role_id);
        $this->assertSame(['name' => 'Hyperf', 'gender' => 1, 'role_id' => null], $model->getAttributes());
        $this->assertSame(null, $user->role_id);
        unset($user->not_found);
        $this->assertSame(['name' => 'Hyperf', 'gender' => 1, 'role_id' => null], $model->getAttributes());
    }

    public function testThrowExceptionWhenCastClassNotExist()
    {
        $this->expectException(InvalidCastException::class);
        $this->expectExceptionMessage('Call to undefined cast [HyperfTest\Database\InvalidCaster] on column [invalid_caster] in model [HyperfTest\Database\TestModelWithCustomCast].');
        $model = new TestModelWithCustomCast();
        $model->invalid_caster = 'foo';
    }
}

class TestModelWithCustomCast extends Model
{
    /**
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = [];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'address' => AddressCaster::class,
        'user' => UserInfoCaster::class,
        'password' => HashCaster::class,
        'other_password' => HashCaster::class . ':md5',
        'uppercase' => UppercaseCaster::class,
        'options' => JsonCaster::class,
        'value_object_with_caster' => ValueObject::class,
        'value_object_caster_with_argument' => ValueObject::class . ':argument',
        'value_object_caster_with_caster_instance' => ValueObjectWithCasterInstance::class,
        'cast_using' => CastUsing::class,
        'invalid_caster' => InvalidCaster::class,
    ];
}

class CastUsing implements Castable
{
    /**
     * @var CastsAttributes
     */
    public static $castsAttributes;

    public static function castUsing()
    {
        return self::$castsAttributes;
    }
}

class HashCaster implements CastsInboundAttributes
{
    public function __construct($algorithm = 'sha256')
    {
        $this->algorithm = $algorithm;
    }

    public function set($model, $key, $value, $attributes)
    {
        return [$key => hash($this->algorithm, $value)];
    }
}

class UppercaseCaster implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return strtoupper($value);
    }

    public function set($model, $key, $value, $attributes)
    {
        return [$key => strtoupper($value)];
    }
}

class AddressCaster implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return new Address($attributes['address_line_one'], $attributes['address_line_two']);
    }

    public function set($model, $key, $value, $attributes)
    {
        return ['address_line_one' => $value->lineOne, 'address_line_two' => $value->lineTwo];
    }
}

class UserInfoCaster implements CastsAttributes
{
    public static $setCount = 0;

    public static $getCount = 0;

    public function get($model, string $key, $value, array $attributes)
    {
        ++self::$getCount;
        return new UserInfo($model, Arr::only($attributes, ['name', 'gender']));
    }

    public function set($model, string $key, $value, array $attributes)
    {
        ++self::$setCount;
        return [
            'name' => $value->name,
            'gender' => $value->gender,
            'role_id' => $value->role_id,
        ];
    }
}

class JsonCaster implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return json_decode($value, true);
    }

    public function set($model, $key, $value, $attributes)
    {
        return json_encode($value);
    }
}

class ValueObjectCaster implements CastsAttributes
{
    private $argument;

    public function __construct($argument = null)
    {
        $this->argument = $argument;
    }

    public function get($model, $key, $value, $attributes)
    {
        if ($this->argument) {
            return $this->argument;
        }

        return unserialize($value);
    }

    public function set($model, $key, $value, $attributes)
    {
        return serialize($value);
    }
}

class MockeryAttribute implements CastsAttributes
{
    /**
     * @var CastsAttributes
     */
    public static $attribute;

    public function get($model, string $key, $value, array $attributes)
    {
        return self::$attribute->get($model, $key, $value, $attributes);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return self::$attribute->set($model, $key, $value, $attributes);
    }
}

class ValueObject implements Castable
{
    public static function castUsing()
    {
        return ValueObjectCaster::class;
    }
}

class ValueObjectWithCasterInstance extends ValueObject
{
    public static function castUsing()
    {
        return new ValueObjectCaster();
    }
}

class Address
{
    public $lineOne;

    public $lineTwo;

    public function __construct($lineOne, $lineTwo)
    {
        $this->lineOne = $lineOne;
        $this->lineTwo = $lineTwo;
    }
}

/**
 * @property string $name
 * @property int $gender
 * @property null|int $role_id
 */
class UserInfo extends CastsValue
{
    protected array $items = [
        'role_id' => 0,
    ];

    public function __set($name, $value)
    {
        $this->items[$name] = $value;
    }

    public function __unset($name)
    {
        unset($this->items[$name]);
    }
}
