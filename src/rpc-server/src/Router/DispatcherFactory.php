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

namespace Hyperf\RpcServer\Router;

use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteParser\Std;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Exception\ConflictAnnotationException;
use Hyperf\Di\ReflectionManager;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\Rpc\Contract\GrpcPathGeneratorInterface;
use Hyperf\Rpc\Contract\PathGeneratorInterface;
use Hyperf\RpcServer\Annotation\RpcService;
use Hyperf\RpcServer\Event\AfterPathRegister;
use Hyperf\Stringable\Str;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;

class DispatcherFactory
{
    protected array $routes = [BASE_PATH . '/config/services.php'];

    /**
     * @var RouteCollector[]
     */
    private array $routers = [];

    /**
     * @var Dispatcher[]
     */
    private array $dispatchers = [];

    public function __construct(private EventDispatcherInterface $eventDispatcher, private PathGeneratorInterface $pathGenerator)
    {
        $this->initAnnotationRoute(AnnotationCollector::list());
        $this->initConfigRoute();
    }

    public function getDispatcher(string $serverName): Dispatcher
    {
        if (isset($this->dispatchers[$serverName])) {
            return $this->dispatchers[$serverName];
        }

        $router = $this->getRouter($serverName);
        return $this->dispatchers[$serverName] = new GroupCountBased($router->getData());
    }

    public function initConfigRoute()
    {
        Router::init($this);
        foreach ($this->routes as $route) {
            if (file_exists($route)) {
                require_once $route;
            }
        }
    }

    public function getRouter(string $serverName): RouteCollector
    {
        if (isset($this->routers[$serverName])) {
            return $this->routers[$serverName];
        }

        $parser = new Std();
        $generator = new DataGenerator();
        return $this->routers[$serverName] = new RouteCollector($parser, $generator);
    }

    private function initAnnotationRoute(array $collector): void
    {
        foreach ($collector as $className => $metadata) {
            if (isset($metadata['_c'][RpcService::class])) {
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->handleRpcService($className, $metadata['_c'][RpcService::class], $metadata['_m'] ?? [], $middlewares);
            }
        }
    }

    /**
     * Register route according to RpcService annotation.
     */
    private function handleRpcService(
        string $className,
        RpcService $annotation,
        array $methodMetadata,
        array $middlewares = []
    ): void {
        $prefix = $annotation->name ?: $className;
        $router = $this->getRouter($annotation->server);

        $publicMethods = ReflectionManager::reflectClass($className)->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($publicMethods as $reflectionMethod) {
            $methodName = $reflectionMethod->getName();
            if (Str::startsWith($methodName, '__')) {
                continue;
            }
            if ($this->pathGenerator instanceof GrpcPathGeneratorInterface) {
                $path = $this->pathGenerator->generate($prefix, $methodName, $annotation->options);
            } else {
                $path = $this->pathGenerator->generate($prefix, $methodName);
            }
            $router->addRoute($path, [
                $className,
                $methodName,
            ]);

            $methodMiddlewares = $middlewares;
            // Handle method level middlewares.
            if (isset($methodMetadata[$methodName])) {
                $methodMiddlewares = array_merge($this->handleMiddleware($methodMetadata[$methodName]), $middlewares);
            }
            // TODO: Remove array_unique from v3.0.
            $methodMiddlewares = array_unique($methodMiddlewares);

            // Register middlewares.
            MiddlewareManager::addMiddlewares($annotation->server, $path, 'POST', $methodMiddlewares);

            // Trigger the AfterPathRegister event.
            $this->eventDispatcher->dispatch(new AfterPathRegister($path, $className, $methodName, $annotation));
        }
    }

    private function handleMiddleware(array $metadata): array
    {
        $hasMiddlewares = isset($metadata[Middlewares::class]);
        $hasMiddleware = isset($metadata[Middleware::class]);
        if (! $hasMiddlewares && ! $hasMiddleware) {
            return [];
        }
        if ($hasMiddlewares && $hasMiddleware) {
            throw new ConflictAnnotationException('Could not use @Middlewares and @Middleware annotation at the same times at same level.');
        }
        if ($hasMiddlewares) {
            // @Middlewares
            /** @var Middlewares $middlewares */
            $middlewares = $metadata[Middlewares::class];
            $result = [];
            foreach ($middlewares->middlewares as $middleware) {
                $result[] = $middleware->middleware;
            }
            return $result;
        }
        // @Middleware
        /** @var Middleware $middleware */
        $middleware = $metadata[Middleware::class];
        return [$middleware->middleware];
    }
}
