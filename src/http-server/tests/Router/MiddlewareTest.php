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
namespace HyperfTest\HttpServer\Router;

use Hyperf\Di\Annotation\MultipleAnnotation;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\PriorityMiddleware;
use Hyperf\HttpServer\Router\Handler;
use HyperfTest\HttpServer\Stub\BarMiddleware;
use HyperfTest\HttpServer\Stub\DemoController;
use HyperfTest\HttpServer\Stub\DispatcherFactory;
use HyperfTest\HttpServer\Stub\FooMiddleware;
use HyperfTest\HttpServer\Stub\SetHeaderMiddleware;
use Mockery;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class MiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        MiddlewareManager::$container = [];
    }

    public function testMiddlewareInController()
    {
        $factory = new DispatcherFactory();
        // Middleware in options should not works.
        $annotation = new Controller('test', options: ['name' => 'Hyperf', 'middleware' => [BarMiddleware::class]]);
        $factory->handleController(
            DemoController::class,
            $annotation,
            ['index' => [
                GetMapping::class => new GetMapping('/index', ['name' => 'index.get', 'id' => 1]),
                PostMapping::class => new PostMapping('/index', ['name' => 'index.post']),
                Middleware::class => new MultipleAnnotation(new Middleware(FooMiddleware::class)),
            ]],
            [new PriorityMiddleware(SetHeaderMiddleware::class)]
        );
        $router = $factory->getRouter('http');

        [$routers] = $router->getData();
        foreach ($routers as $method => $items) {
            /**
             * @var string $key
             * @var Handler $value
             */
            foreach ($items as $key => $value) {
                $this->assertSame([DemoController::class, 'index'], $value->callback);
                $this->assertSame('/index', $value->route);
                $this->assertSame('index.' . strtolower($method), $value->options['name']);
                if ($method === 'GET') {
                    $this->assertSame(1, $value->options['id']);
                } else {
                    $this->assertArrayNotHasKey('id', $value->options);
                }

                foreach ([
                    $value->options['middleware'],
                    MiddlewareManager::get('http', $value->route, $method),
                ] as $dataSource) {
                    $this->assertMiddlewares([
                        SetHeaderMiddleware::class,
                        FooMiddleware::class,
                    ], $dataSource);
                }
            }
        }
    }

    public function testMiddlewarePriorityInController()
    {
        $factory = new DispatcherFactory();
        // Middleware in options should not works.
        $annotation = new Controller('test', options: ['name' => 'Hyperf', 'middleware' => [BarMiddleware::class]]);
        $factory->handleController(
            DemoController::class,
            $annotation,
            ['index' => [
                GetMapping::class => new GetMapping('/index', ['name' => 'index.get', 'id' => 1]),
                PostMapping::class => new PostMapping('/index', ['name' => 'index.post']),
                Middlewares::class => new Middlewares([
                    BarMiddleware::class => 1,
                    FooMiddleware::class => 3,
                ]),
            ]],
            [
                new PriorityMiddleware(SetHeaderMiddleware::class, 1),
                new PriorityMiddleware(FooMiddleware::class),
            ]
        );
        $router = $factory->getRouter('http');

        [$routers] = $router->getData();
        foreach ($routers as $method => $items) {
            /**
             * @var string $key
             * @var Handler $value
             */
            foreach ($items as $key => $value) {
                $this->assertSame([DemoController::class, 'index'], $value->callback);
                $this->assertSame('/index', $value->route);
                $this->assertSame('index.' . strtolower($method), $value->options['name']);
                if ($method === 'GET') {
                    $this->assertSame(1, $value->options['id']);
                } else {
                    $this->assertArrayNotHasKey('id', $value->options);
                }

                foreach ([
                    $value->options['middleware'],
                    MiddlewareManager::get('http', $value->route, $method),
                ] as $dataSource) {
                    $this->assertMiddlewares([
                        FooMiddleware::class,
                        SetHeaderMiddleware::class,
                        BarMiddleware::class,
                    ], $dataSource);
                }
            }
        }
    }

    /**
     * @param string[] $expectMiddlewares
     */
    protected function assertMiddlewares(array $expectMiddlewares, array $middlewares)
    {
        $middlewares = PriorityMiddleware::getPriorityMiddlewares($middlewares);

        $offset = 0;
        foreach ($middlewares as $middlewareKey => $middleware) {
            if ($middleware instanceof PriorityMiddleware) {
                $this->assertSame($middleware->middleware, $expectMiddlewares[$offset] ?? '');
            } elseif (is_int($middleware)) {
                $middleware = $middlewareKey;
                $this->assertSame($middleware, $expectMiddlewares[$offset] ?? '');
            } else {
                $this->assertSame($middleware, $expectMiddlewares[$offset] ?? '');
            }
            ++$offset;
        }
    }
}
