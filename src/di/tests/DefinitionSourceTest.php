<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Di;

use Hyperf\Di\Annotation\Scanner;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DefinitionSourceTest extends TestCase
{
    public function testAddDefinition()
    {
        $container = new Container(new DefinitionSource([], [], new Scanner()));
        $container->getDefinitionSource()->addDefinition('Foo', function () {
            return 'bar';
        });
        $this->assertEquals('bar', $container->get('Foo'));
    }
}
