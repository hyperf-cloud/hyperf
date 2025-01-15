<?php
declare(strict_types=1);
namespace Hyperf\WebSocketClient;

use Hyperf\Contract\ContainerInterface;
use Hyperf\HttpMessage\Uri\Uri;
use Hyperf\Stringable\Str;
use function Hyperf\Support\make;
use function Hyperf\Coroutine\defer;

class ClientFactory
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function create(string $uri, bool $autoClose = true, array $headers = []): ClientInterface
    {
        if (! Str::startsWith($uri, ['ws://', 'wss://'])) {
            $uri = 'ws://' . $uri;
        }
        /** @var ClientInterface $client */
        $client = make(ClientInterface::class, ['uri' => new Uri($uri), 'headers' => $headers]);
        if ($autoClose) {
            defer(function () use ($client) {
                $client->close();
            });
        }
        return $client;
    }
}