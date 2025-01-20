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

use Hyperf\Collection\Collection;
use HyperfTest\Database\Stubs\HasAttributesWithArrayCast;
use HyperfTest\Database\Stubs\HasAttributesWithConstructorArguments;
use HyperfTest\Database\Stubs\HasAttributesWithoutConstructor;
use HyperfTest\Database\Stubs\HasCacheableAttributeWithAccessor;
use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DatabaseConcernsHasAttributesTest extends TestCase
{
    public function testWithoutConstructor()
    {
        $instance = new HasAttributesWithoutConstructor();
        $attributes = $instance->getMutatedAttributes();
        $this->assertEquals(['some_attribute'], $attributes);
    }

    public function testWithConstructorArguments()
    {
        $instance = new HasAttributesWithConstructorArguments(null);
        $attributes = $instance->getMutatedAttributes();
        $this->assertEquals(['some_attribute'], $attributes);
    }

    public function testRelationsToArray()
    {
        $mock = m::mock(HasAttributesWithoutConstructor::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('getArrayableRelations')->andReturn([
                'arrayable_relation' => Collection::make(['foo' => 'bar']),
                'invalid_relation' => 'invalid',
                'null_relation' => null,
            ])
            ->getMock();

        $this->assertEquals([
            'arrayable_relation' => ['foo' => 'bar'],
            'null_relation' => null,
        ], $mock->relationsToArray());
    }

    public function testCastingEmptyStringToArrayDoesNotError()
    {
        $instance = new HasAttributesWithArrayCast();
        $this->assertEquals(['foo' => null], $instance->attributesToArray());
        $this->assertTrue(json_last_error() === JSON_ERROR_NONE);
    }

    public function testUnsettingCachedAttribute()
    {
        $instance = new HasCacheableAttributeWithAccessor();
        $this->assertEquals('foo', $instance->getAttribute('cacheableProperty'));
        $this->assertTrue($instance->cachedAttributeIsset('cacheableProperty'));

        unset($instance->cacheableProperty);

        $this->assertFalse($instance->cachedAttributeIsset('cacheableProperty'));
    }
}
