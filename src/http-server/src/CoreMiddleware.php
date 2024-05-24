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

namespace Hyperf\HttpServer;

use Closure;
use FastRoute\Dispatcher;
use Hyperf\Codec\Json;
use Hyperf\Context\Context;
use Hyperf\Context\RequestContext;
use Hyperf\Context\ResponseContext;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Htmlable;
use Hyperf\Contract\Jsonable;
use Hyperf\Contract\NormalizerInterface;
use Hyperf\Di\ClosureDefinitionCollectorInterface;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\Di\ReflectionType;
use Hyperf\HttpMessage\Exception\MethodNotAllowedHttpException;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpMessage\Exception\ServerErrorHttpException;
use Hyperf\HttpMessage\Server\ResponsePlusProxy;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Server\Exception\ServerException;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Swow\Psr7\Message\ResponsePlusInterface;

/**
 * Core middleware of Hyperf, main responsibility is used to handle route info
 * and then delegate to the specified handler (which is Controller) to handle the request,
 * generate a response object and delegate to next middleware (Because this middleware is the
 * core middleware, then the next middleware also means it's the previous middlewares object) .
 */
class CoreMiddleware implements CoreMiddlewareInterface
{
    protected Dispatcher $dispatcher;

    private MethodDefinitionCollectorInterface $methodDefinitionCollector;

    private ?ClosureDefinitionCollectorInterface $closureDefinitionCollector = null;

    private NormalizerInterface $normalizer;

    public function __construct(protected ContainerInterface $container, private string $serverName)
    {
        $this->dispatcher = $this->createDispatcher($serverName);
        $this->normalizer = $this->container->get(NormalizerInterface::class);
        $this->methodDefinitionCollector = $this->container->get(MethodDefinitionCollectorInterface::class);
        if ($this->container->has(ClosureDefinitionCollectorInterface::class)) {
            $this->closureDefinitionCollector = $this->container->get(ClosureDefinitionCollectorInterface::class);
        }
    }

    public function dispatch(ServerRequestInterface $request): ServerRequestInterface
    {
        $routes = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        $dispatched = new Dispatched($routes, $this->serverName);

        return RequestContext::set($request)->setAttribute(Dispatched::class, $dispatched);
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = RequestContext::set($request);

        /** @var Dispatched $dispatched */
        $dispatched = $request->getAttribute(Dispatched::class);

        if (! $dispatched instanceof Dispatched) {
            throw new ServerException(sprintf('The dispatched object is not a %s object.', Dispatched::class));
        }

        $response = match ($dispatched->status) {
            Dispatcher::NOT_FOUND => $this->handleNotFound($request),
            Dispatcher::METHOD_NOT_ALLOWED => $this->handleMethodNotAllowed($dispatched->params, $request),
            Dispatcher::FOUND => $this->handleFound($dispatched, $request),
            default => null,
        };

        if (! $response instanceof ResponsePlusInterface) {
            $response = $this->transferToResponse($response, $request);
        }

        return $response->addHeader('Server', 'Hyperf');
    }

    public function getMethodDefinitionCollector(): MethodDefinitionCollectorInterface
    {
        return $this->methodDefinitionCollector;
    }

    public function getClosureDefinitionCollector(): ClosureDefinitionCollectorInterface
    {
        return $this->closureDefinitionCollector;
    }

    public function getNormalizer(): NormalizerInterface
    {
        return $this->normalizer;
    }

    protected function createDispatcher(string $serverName): Dispatcher
    {
        $factory = $this->container->get(DispatcherFactory::class);
        return $factory->getDispatcher($serverName);
    }

    /**
     * Handle the response when found.
     *
     * @return array|Arrayable|mixed|ResponseInterface|string
     */
    protected function handleFound(Dispatched $dispatched, ServerRequestInterface $request): mixed
    {
        if ($dispatched->handler->callback instanceof Closure) {
            $parameters = $this->parseClosureParameters($dispatched->handler->callback, $dispatched->params);
            $callback = $dispatched->handler->callback;
            $response = $callback(...$parameters);
        } else {
            [$controller, $action] = $this->prepareHandler($dispatched->handler->callback);
            $controllerInstance = $this->container->get($controller);
            if (! method_exists($controllerInstance, $action)) {
                // Route found, but the handler does not exist.
                throw new ServerErrorHttpException('Method of class does not exist.');
            }
            $parameters = $this->parseMethodParameters($controller, $action, $dispatched->params);
            $response = $controllerInstance->{$action}(...$parameters);
        }
        return $response;
    }

    /**
     * Handle the response when cannot found any routes.
     */
    protected function handleNotFound(ServerRequestInterface $request): mixed
    {
        throw new NotFoundHttpException();
    }

    /**
     * Handle the response when the routes found but doesn't match any available methods.
     */
    protected function handleMethodNotAllowed(array $methods, ServerRequestInterface $request): mixed
    {
        throw new MethodNotAllowedHttpException('Allow: ' . implode(', ', $methods));
    }

    protected function prepareHandler(array|string $handler): array
    {
        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                return explode('@', $handler);
            }
            if (str_contains($handler, '::')) {
                return explode('::', $handler);
            }
            return [$handler, '__invoke'];
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new RuntimeException('Handler not exist.');
    }

    /**
     * Transfer the non-standard response content to a standard response object.
     *
     * @param null|array|Arrayable|Htmlable|Jsonable|ResponseInterface|string $response
     */
    protected function transferToResponse($response, ServerRequestInterface $request): ResponsePlusInterface
    {
        if (is_string($response)) {
            return $this->response()->addHeader('content-type', 'text/plain')->setBody(new SwooleStream($response));
        }

        if ($response instanceof ResponseInterface) {
            return new ResponsePlusProxy($response);
        }

        if (is_array($response) || $response instanceof Arrayable) {
            return $this->response()
                ->addHeader('content-type', 'application/json')
                ->setBody(new SwooleStream(Json::encode($response)));
        }

        if ($response instanceof Jsonable) {
            return $this->response()
                ->addHeader('content-type', 'application/json')
                ->setBody(new SwooleStream((string) $response));
        }

        if ($response instanceof Htmlable) {
            return $this->response()
                ->addHeader('content-type', 'text/html')
                ->setBody(new SwooleStream((string) $response));
        }

        if ($this->response()->hasHeader('content-type')) {
            return $this->response()->setBody(new SwooleStream((string) $response));
        }

        return $this->response()->addHeader('content-type', 'text/plain')->setBody(new SwooleStream((string) $response));
    }

    /**
     * Get response instance from context.
     */
    protected function response(): ResponsePlusInterface
    {
        return ResponseContext::get();
    }

    /**
     * Parse the parameters of method definitions, and then bind the specified arguments or
     * get the value from DI container, combine to an argument array that should be injected
     * and return the array.
     */
    protected function parseMethodParameters(string $controller, string $action, array $arguments): array
    {
        $definitions = $this->getMethodDefinitionCollector()->getParameters($controller, $action);
        return $this->getInjections($definitions, "{$controller}::{$action}", $arguments);
    }

    /**
     * Parse the parameters of closure definitions, and then bind the specified arguments or
     * get the value from DI container, combine to an argument array that should be injected
     * and return the array.
     */
    protected function parseClosureParameters(Closure $closure, array $arguments): array
    {
        if (! $this->container->has(ClosureDefinitionCollectorInterface::class)) {
            return [];
        }
        $definitions = $this->getClosureDefinitionCollector()->getParameters($closure);
        return $this->getInjections($definitions, 'Closure', $arguments);
    }

    /**
     * @param ReflectionType[] $definitions
     */
    private function getInjections(array $definitions, string $callableName, array $arguments): array
    {
        $injections = [];
        foreach ($definitions as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($this->container->has($definition->getName())) {
                    $injections[] = $this->container->get($definition->getName());
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } else {
                    throw new InvalidArgumentException("Parameter '{$definition->getMeta('name')}' "
                        . "of {$callableName} should not be null");
                }
            } else {
                $injections[] = $this->getNormalizer()->denormalize($value, $definition->getName());
            }
        }
        return $injections;
    }
}
