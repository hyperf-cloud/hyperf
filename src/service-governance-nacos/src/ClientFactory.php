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

namespace Hyperf\ServiceGovernanceNacos;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Nacos\Config;
use Psr\Container\ContainerInterface;

class ClientFactory
{
    public function __invoke(ContainerInterface $container, array $parameters = []): Client
    {
        if (empty($parameters['config'])) {
            $config = $container->get(ConfigInterface::class)->get('services.drivers.nacos', []);
        } else {
            $config = $parameters['config'];
        }
        if (! empty($config['uri'])) {
            $baseUri = $config['uri'];
        } else {
            $baseUri = sprintf('http://%s:%d', $config['host'] ?? '127.0.0.1', $config['port'] ?? 8848);
        }
        return new Client(new Config([
            'base_uri' => $baseUri,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
            'access_key' => $config['access_key'] ?? null,
            'access_secret' => $config['access_secret'] ?? null,
            'guzzle_config' => $config['guzzle']['config'] ?? null,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'grpc' => $config['grpc'] ?? [],
        ]));
    }
}
