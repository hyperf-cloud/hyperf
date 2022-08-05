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
namespace Hyperf\Etcd;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Etcd\Exception\ClientNotFindException;
use Hyperf\Guzzle\HandlerStackFactory;
use Psr\Container\ContainerInterface;

class KVFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $uri = $config->get('etcd.uri', 'http://127.0.0.1:2379');
        $version = $config->get('etcd.version', 'v3beta');
        $options = $config->get('etcd.options', []);
        $factory = $container->get(HandlerStackFactory::class);

        return $this->make($uri, $version, $options, $factory);
    }

    protected function make(string $uri, string $version, array $options, HandlerStackFactory $factory)
    {
        $params = [
            'uri' => $uri,
            'version' => $version,
            'options' => $options,
            'factory' => $factory,
        ];
        return match ($version) {
            'v3', 'v3alpha', 'v3beta' => make(V3\KV::class, $params),
            default => throw new ClientNotFindException("KV of {$version} is not find."),
        };
    }
}
