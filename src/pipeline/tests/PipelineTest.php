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

namespace HyperfTest\Pipeline;

use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Pipeline\Pipeline;
use HyperfTest\Pipeline\Stub\FooPipeline;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class PipelineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testPipelineBasicUsage()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            return $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);
        $this->assertSame('foo', $_SERVER['__test.pipe.two']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two']);
    }

    public function testPipelineUsageWithObjects()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([new PipelineTestPipeOne()])
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithInvokableObjects()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([new PipelineTestPipeTwo()])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithCallable()
    {
        $function = function ($piped, $next) {
            $_SERVER['__test.pipe.one'] = 'foo';

            return $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([$function])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);

        $result = (new Pipeline($this->getContainer()))
            ->send('bar')
            ->through($function)
            ->then(static function ($passable) {
                return $passable;
            });

        $this->assertSame('bar', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithInvokableClass()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeTwo::class])
            ->then(
                function ($piped) {
                    return $piped;
                }
            );

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineUsageWithParameters()
    {
        $parameters = ['one', 'two'];

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through(PipelineTestParameterPipe::class . ':' . implode(',', $parameters))
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame('foo', $result);
        $this->assertEquals($parameters, $_SERVER['__test.pipe.parameters']);

        unset($_SERVER['__test.pipe.parameters']);
    }

    public function testPipelineViaChangesTheMethodBeingCalledOnThePipes()
    {
        $pipelineInstance = new Pipeline($this->getContainer());
        $result = $pipelineInstance->send('data')
            ->through(PipelineTestPipeOne::class)
            ->via('differentMethod')
            ->then(function ($piped) {
                return $piped;
            });
        $this->assertSame('data', $result);
    }

    public function testPipelineThenMethodRunsPipelineThenReturnsPassable()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class])
            ->then(static function ($passable) {
                return $passable;
            });

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testPipelineThenReturnMethodRunsPipelineThenReturnsTheResult()
    {
        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class])
            ->thenReturn();

        $this->assertSame('foo', $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);

        unset($_SERVER['__test.pipe.one']);
    }

    public function testHandleCarry()
    {
        $result = (new FooPipeline($this->getContainer()))
            ->send($id = rand(0, 99))
            ->through([PipelineTestPipeOne::class])
            ->via('incr')
            ->then(static function ($passable) {
                if (is_int($passable)) {
                    $passable += 3;
                }
                return $passable;
            });

        $this->assertSame($id + 6, $result);
    }

    public function testPipelineFinally()
    {
        $pipeTwo = function ($piped, $next) {
            $_SERVER['__test.pipe.two'] = $piped;

            $next($piped);
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->finally(function ($piped) {
                $_SERVER['__test.pipe.finally'] = $piped;
            })
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame(null, $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);
        $this->assertSame('foo', $_SERVER['__test.pipe.two']);
        $this->assertSame('foo', $_SERVER['__test.pipe.finally']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two'], $_SERVER['__test.pipe.finally']);
    }

    public function testPipelineFinallyMethodWhenChainIsStopped()
    {
        $pipeTwo = function ($piped) {
            $_SERVER['__test.pipe.two'] = $piped;
        };

        $result = (new Pipeline($this->getContainer()))
            ->send('foo')
            ->through([PipelineTestPipeOne::class, $pipeTwo])
            ->finally(function ($piped) {
                $_SERVER['__test.pipe.finally'] = $piped;
            })
            ->then(function ($piped) {
                return $piped;
            });

        $this->assertSame(null, $result);
        $this->assertSame('foo', $_SERVER['__test.pipe.one']);
        $this->assertSame('foo', $_SERVER['__test.pipe.two']);
        $this->assertSame('foo', $_SERVER['__test.pipe.finally']);

        unset($_SERVER['__test.pipe.one'], $_SERVER['__test.pipe.two'], $_SERVER['__test.pipe.finally']);
    }

    public function testPipelineFinallyOrder()
    {
        $std = new stdClass();

        $result = (new Pipeline($this->getContainer()))
            ->send($std)
            ->through([
                function ($std, $next) {
                    $std->value = 1;

                    return $next($std);
                },
                function ($std, $next) {
                    ++$std->value;

                    return $next($std);
                },
            ])->finally(function ($std) {
                $this->assertSame(3, $std->value);

                ++$std->value;
            })->then(function ($std) {
                ++$std->value;

                return $std;
            });

        $this->assertSame(4, $std->value);
        $this->assertSame(4, $result->value);
    }

    public function testPipelineFinallyWhenExceptionOccurs()
    {
        $std = new stdClass();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('My Exception: 1');

        try {
            (new Pipeline($this->getContainer()))
                ->send($std)
                ->through([
                    function ($std, $next) {
                        $std->value = 1;

                        return $next($std);
                    },
                    function ($std) {
                        throw new Exception('My Exception: ' . $std->value);
                    },
                ])->finally(function ($std) {
                    $this->assertSame(1, $std->value);

                    ++$std->value;
                })->then(function ($std) {
                    $std->value = 0;

                    return $std;
                });
        } catch (Exception $e) {
            $this->assertSame('My Exception: 1', $e->getMessage());
            $this->assertSame(2, $std->value);

            throw $e;
        }
    }

    protected function getContainer()
    {
        $container = Mockery::mock(ContainerInterface::class);
        ApplicationContext::setContainer($container);
        $container->shouldReceive('get')->with(PipelineTestPipeOne::class)->andReturn(new PipelineTestPipeOne());
        $container->shouldReceive('get')->with(PipelineTestPipeTwo::class)->andReturn(new PipelineTestPipeTwo());
        $container->shouldReceive('get')->with(PipelineTestParameterPipe::class)->andReturn(new PipelineTestParameterPipe());

        return $container;
    }
}

class PipelineTestPipeOne
{
    public function handle($piped, $next)
    {
        $_SERVER['__test.pipe.one'] = $piped;

        return $next($piped);
    }

    public function differentMethod($piped, $next)
    {
        return $next($piped);
    }

    public function incr($piped, $next)
    {
        return $next(++$piped);
    }
}

class PipelineTestPipeTwo
{
    public function __invoke($piped, $next)
    {
        $_SERVER['__test.pipe.one'] = $piped;

        return $next($piped);
    }
}

class PipelineTestParameterPipe
{
    public function handle($piped, $next, $parameter1 = null, $parameter2 = null)
    {
        $_SERVER['__test.pipe.parameters'] = [$parameter1, $parameter2];

        return $next($piped);
    }
}
