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

namespace Hyperf\Testing;

use Hyperf\Codec\Packer\JsonPacker;
use Hyperf\Collection\Arr;
use Hyperf\Context\Context;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\PackerInterface;
use Hyperf\Dispatcher\HttpDispatcher;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpMessage\Server\Request as Psr7Request;
use Hyperf\HttpMessage\Server\Response as Psr7Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpMessage\Uri\Uri;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\ResponseEmitter;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Server;
use Hyperf\Support\Filesystem\Filesystem;
use Hyperf\Testing\HttpMessage\Upload\UploadedFile;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function Hyperf\Collection\data_get;
use function Hyperf\Coroutine\wait;

class Client extends Server
{
    protected PackerInterface $packer;

    protected float $waitTimeout = 10.0;

    protected string $baseUri = 'http://127.0.0.1/';

    public function __construct(ContainerInterface $container, ?PackerInterface $packer = null, $server = 'http')
    {
        parent::__construct(
            $container,
            $container->get(HttpDispatcher::class),
            $container->get(ExceptionHandlerDispatcher::class),
            $container->get(ResponseEmitter::class)
        );

        $this->packer = $packer ?? new JsonPacker();

        $this->initCoreMiddleware($server);
        $this->initBaseUri($server);
    }

    public function get(string $uri, array $data = [], array $headers = [])
    {
        $response = $this->request('GET', $uri, [
            'headers' => $headers,
            'query' => $data,
        ]);

        return $this->packer->unpack((string) $response->getBody());
    }

    public function post(string $uri, array $data = [], array $headers = [])
    {
        $response = $this->request('POST', $uri, [
            'headers' => $headers,
            'form_params' => $data,
        ]);

        return $this->packer->unpack((string) $response->getBody());
    }

    public function put(string $uri, array $data = [], array $headers = [])
    {
        $response = $this->request('PUT', $uri, [
            'headers' => $headers,
            'form_params' => $data,
        ]);

        return $this->packer->unpack((string) $response->getBody());
    }

    public function patch(string $uri, array $data = [], array $headers = [])
    {
        $response = $this->request('PATCH', $uri, [
            'headers' => $headers,
            'form_params' => $data,
        ]);

        return $this->packer->unpack((string) $response->getBody());
    }

    public function delete(string $uri, array $data = [], array $headers = [])
    {
        $response = $this->request('DELETE', $uri, [
            'headers' => $headers,
            'query' => $data,
        ]);

        return $this->packer->unpack((string) $response->getBody());
    }

    public function json(string $uri, array $data = [], array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        $response = $this->request('POST', $uri, [
            'headers' => $headers,
            'json' => $data,
        ]);
        return $this->packer->unpack((string) $response->getBody());
    }

    public function file(string $uri, array $data = [], array $headers = [])
    {
        $multipart = [];
        if (Arr::isAssoc($data)) {
            $data = [$data];
        }

        foreach ($data as $item) {
            $name = $item['name'];
            $file = $item['file'];

            $multipart[] = [
                'name' => $name,
                'contents' => fopen($file, 'r'),
                'filename' => basename($file),
            ];
        }

        $response = $this->request('POST', $uri, [
            'headers' => $headers,
            'multipart' => $multipart,
        ]);

        return $this->packer->unpack((string) $response->getBody());
    }

    public function request(string $method, string $path, array $options = [], ?callable $callable = null)
    {
        return wait(function () use ($method, $path, $options, $callable) {
            $callable && $callable();
            return $this->execute($this->initRequest($method, $path, $options));
        }, $this->waitTimeout);
    }

    public function sendRequest(ServerRequestInterface $psr7Request, ?callable $callable = null): ResponseInterface
    {
        return wait(function () use ($psr7Request, $callable) {
            $callable && $callable();
            return $this->execute($psr7Request);
        }, $this->waitTimeout);
    }

    public function initRequest(string $method, string $path, array $options = []): ServerRequestInterface
    {
        $query = $options['query'] ?? [];
        $params = $options['form_params'] ?? [];
        $json = $options['json'] ?? [];
        $headers = $options['headers'] ?? [];
        $multipart = $options['multipart'] ?? [];

        $parsePath = parse_url($path);
        $path = $parsePath['path'];
        $uriPathQuery = $parsePath['query'] ?? [];
        if (! empty($uriPathQuery)) {
            parse_str($uriPathQuery, $pathQuery);
            $query = array_merge($pathQuery, $query);
        }

        $data = $params;

        // Initialize PSR-7 Request and Response objects.
        $uri = (new Uri($this->baseUri . ltrim($path, '/')))->withQuery(http_build_query($query));

        $content = http_build_query($params);
        if ($method == 'POST' && data_get($headers, 'Content-Type') == 'application/json') {
            $content = json_encode($json, JSON_UNESCAPED_UNICODE);
            $data = $json;
        }

        $body = new SwooleStream($content);

        $request = new Psr7Request($method, $uri, $headers, $body);
        $request->setServerParams($this->getServerParams($method, $uri->getPath()));

        return $request->withQueryParams($query)
            ->withParsedBody($data)
            ->withUploadedFiles($this->normalizeFiles($multipart));
    }

    protected function execute(ServerRequestInterface $psr7Request): ResponseInterface
    {
        $this->persistToContext($psr7Request, new Psr7Response());

        $psr7Request = $this->coreMiddleware->dispatch($psr7Request);
        /** @var Dispatched $dispatched */
        $dispatched = $psr7Request->getAttribute(Dispatched::class);
        $middlewares = $this->middlewares;
        if ($dispatched->isFound()) {
            $registeredMiddlewares = MiddlewareManager::get($this->serverName, $dispatched->handler->route, $psr7Request->getMethod());
            $middlewares = array_merge($middlewares, $registeredMiddlewares);
        }

        $middlewares = MiddlewareManager::sortMiddlewares($middlewares);

        try {
            $psr7Response = $this->dispatcher->dispatch($psr7Request, $middlewares, $this->coreMiddleware);
        } catch (Throwable $throwable) {
            // Delegate the exception to exception handler.
            $psr7Response = $this->exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
        }

        return $psr7Response;
    }

    protected function persistToContext(ServerRequestInterface $request, ResponseInterface $response)
    {
        Context::set(ServerRequestInterface::class, $request);
        Context::set(ResponseInterface::class, $response);
    }

    protected function initBaseUri(string $server): void
    {
        if ($this->container->has(ConfigInterface::class)) {
            $config = $this->container->get(ConfigInterface::class);
            $servers = $config->get('server.servers', []);
            foreach ($servers as $item) {
                if ($item['name'] == $server) {
                    $this->baseUri = sprintf('http://127.0.0.1:%d/', (int) $item['port']);
                    break;
                }
            }
        }
    }

    protected function normalizeFiles(array $multipart): array
    {
        $files = [];
        $fileSystem = $this->container->get(Filesystem::class);

        foreach ($multipart as $item) {
            if (isset($item['name'], $item['contents'], $item['filename'])) {
                $name = $item['name'];
                $contents = $item['contents'];
                $filename = $item['filename'];

                $dir = BASE_PATH . '/runtime/uploads';
                $tmpName = $dir . '/' . $filename;
                if (! is_dir($dir)) {
                    $fileSystem->makeDirectory($dir);
                }
                $fileSystem->put($tmpName, $contents);

                $stats = fstat($contents);

                $files[$name] = new UploadedFile(
                    $tmpName,
                    $stats['size'],
                    0,
                    $name
                );
            }
        }

        return $files;
    }

    protected function getStream(string $resource)
    {
        $stream = fopen('php://temp', 'r+');
        if ($resource !== '') {
            fwrite($stream, $resource);
            fseek($stream, 0);
        }

        return $stream;
    }

    protected function getServerParams(string $method, string $uri): array
    {
        return [
            'request_method' => $method,
            'request_uri' => $uri,
            'path_info' => $uri,
            'request_time' => time(),
            'request_time_float' => microtime(true),
            'server_protocol' => 'HTTP/1.1',
            'server_port' => 9501,
            'remote_port' => 40005,
            'remote_addr' => '127.0.0.1',
            'master_time' => time(),
        ];
    }
}
