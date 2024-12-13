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

namespace HyperfTest\ExceptionHandler;

use ErrorException;
use Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class ErrorExceptionHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    #[WithoutErrorHandler]
    public function testHandleError()
    {
        $container = Mockery::mock(\Psr\Container\ContainerInterface::class);
        $container->shouldReceive('has')->with(\Hyperf\Contract\StdoutLoggerInterface::class)->andReturn(false);
        $listener = new ErrorExceptionHandler($container);
        $listener->process((object) []);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Undefined array key 1');

        try {
            $array = [];
            $array[1];
        } finally {
            restore_error_handler();
        }
    }
}
