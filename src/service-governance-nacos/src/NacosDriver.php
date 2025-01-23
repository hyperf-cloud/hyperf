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

use Hyperf\Codec\Json;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Nacos\Exception\RequestException;
use Hyperf\ServiceGovernance\DriverInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function Hyperf\Support\make;
use function Hyperf\Support\retry;

class NacosDriver implements DriverInterface
{
    protected Client $client;

    protected LoggerInterface $logger;

    protected ConfigInterface $config;

    protected array $serviceRegistered = [];

    protected array $serviceCreated = [];

    protected array $registerHeartbeat = [];

    private array $metadata = [];

    public function __construct(protected ContainerInterface $container)
    {
        $this->client = $container->get(Client::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    public function isLongPolling(): bool
    {
        return false;
    }

    public function getNodes(string $uri, string $name, array $metadata): array
    {
        $response = $this->getConsumersClient($name)->instance->list($name, [
            'groupName' => $this->config->get('services.drivers.nacos.group_name'),
            'namespaceId' => $this->config->get('services.drivers.nacos.namespace_id'),
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RequestException((string) $response->getBody(), $response->getStatusCode());
        }

        $data = Json::decode((string) $response->getBody());
        $hosts = $data['hosts'] ?? [];
        $nodes = [];
        foreach ($hosts as $node) {
            if (isset($node['ip'], $node['port']) && ($node['healthy'] ?? false)) {
                $nodes[] = [
                    'host' => $node['ip'],
                    'port' => $node['port'],
                    'weight' => $this->getWeight($node['weight'] ?? 1),
                ];
            }
        }
        return $nodes;
    }

    public function register(string $name, string $host, int $port, array $metadata): void
    {
        $this->setMetadata($name, $metadata);
        $ephemeral = (bool) $this->config->get('services.drivers.nacos.ephemeral');
        if (! $ephemeral && ! array_key_exists($name, $this->serviceCreated)) {
            $response = $this->client->service->create($name, [
                'groupName' => $this->config->get('services.drivers.nacos.group_name'),
                'namespaceId' => $this->config->get('services.drivers.nacos.namespace_id'),
                'metadata' => $this->getMetadata($name),
                'protectThreshold' => (float) $this->config->get('services.drivers.nacos.protect_threshold', 0),
            ]);

            if ($response->getStatusCode() !== 200 || (string) $response->getBody() !== 'ok') {
                throw new RequestException(sprintf('Failed to create nacos service %s , %s !', $name, $response->getBody()));
            }

            $this->serviceCreated[$name] = true;
        }

        $response = $this->client->instance->register($host, $port, $name, [
            'groupName' => $this->config->get('services.drivers.nacos.group_name'),
            'namespaceId' => $this->config->get('services.drivers.nacos.namespace_id'),
            'metadata' => $this->getMetadata($name),
            'ephemeral' => $ephemeral ? 'true' : 'false',
        ]);

        if ($response->getStatusCode() !== 200 || (string) $response->getBody() !== 'ok') {
            throw new RequestException(sprintf('Failed to create nacos instance %s:%d! for %s , %s ', $host, $port, $name, $response->getBody()));
        }

        $this->serviceRegistered[$name] = true;
        if ($ephemeral) {
            $this->registerHeartbeat($name, $host, $port);
        }
    }

    public function isRegistered(string $name, string $host, int $port, array $metadata): bool
    {
        if (array_key_exists($name, $this->serviceRegistered)) {
            return true;
        }
        $this->setMetadata($name, $metadata);
        $response = $this->client->service->detail(
            $name,
            $this->config->get('services.drivers.nacos.group_name'),
            $this->config->get('services.drivers.nacos.namespace_id')
        );

        if ($response->getStatusCode() === 404) {
            return false;
        }

        if (in_array($response->getStatusCode(), [400, 500], true) && strpos((string) $response->getBody(), 'not found') > 0) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            throw new RequestException(sprintf('Failed to get nacos service %s!', $name), $response->getStatusCode());
        }

        $this->serviceCreated[$name] = true;

        $response = $this->client->instance->detail($host, $port, $name, [
            'groupName' => $this->config->get('services.drivers.nacos.group_name'),
            'namespaceId' => $this->config->get('services.drivers.nacos.namespace_id'),
        ]);

        if ($this->isNoIpsFound($response)) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            throw new RequestException(sprintf('Failed to get nacos instance %s:%d for %s!', $host, $port, $name));
        }
        $this->serviceRegistered[$name] = true;

        if ($this->config->get('services.drivers.nacos.ephemeral')) {
            $this->registerHeartbeat($name, $host, $port);
        }

        return true;
    }

    protected function getConsumersClient(string $name): Client
    {
        static $client;
        if (empty($client[$name])) {
            $consumerConfigs = $this->config->get('services.consumers');
            foreach ($consumerConfigs as $consumerConfig) {
                if ($consumerConfig['name'] == $name) {
                    $config = $consumerConfig['registry']['config'] ?? [];
                    $address = $consumerConfig['registry']['address'] ?? '';
                    // If there is no dedicated configuration and address not empty
                    if (empty($config) && $address != '') {
                        // merge and replace config from services.drivers.nacos
                        $config = array_merge(
                            $this->container->get(ConfigInterface::class)->get('services.drivers.nacos', []),
                            [
                                'uri' => $address,
                            ]
                        );
                    }
                    if (! empty($config)) {
                        $client[$name] = make(Client::class, [
                            'config' => $config,
                        ]);
                    }
                    break;
                }
            }
            if (empty($client[$name])) {
                $client[$name] = $this->client;
            }
        }
        return $client[$name];
    }

    protected function isNoIpsFound(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() === 404) {
            return true;
        }

        if ($response->getStatusCode() === 500) {
            $messages = [
                'no ips found',
                'no matched ip',
            ];
            $body = (string) $response->getBody();
            foreach ($messages as $message) {
                if (str_contains($body, $message)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function setMetadata(string $name, array $metadata)
    {
        $this->metadata[$name] = $metadata;
    }

    protected function getMetadata(string $name): ?string
    {
        if (empty($this->metadata[$name])) {
            return null;
        }
        unset($this->metadata[$name]['methodName']);
        return Json::encode($this->metadata[$name]);
    }

    protected function registerHeartbeat(string $name, string $host, int $port): void
    {
        $key = $name . $host . $port;
        if (isset($this->registerHeartbeat[$key])) {
            return;
        }
        $this->registerHeartbeat[$key] = true;

        Coroutine::create(function () use ($name, $host, $port) {
            retry(INF, function () use ($name, $host, $port) {
                $lightBeatEnabled = false;
                while (true) {
                    try {
                        $heartbeat = $this->config->get('services.drivers.nacos.heartbeat', 5);
                        if (CoordinatorManager::until(Constants::WORKER_EXIT)->yield($heartbeat)) {
                            break;
                        }

                        $groupName = $this->config->get('services.drivers.nacos.group_name');

                        $response = $this->client->instance->beat(
                            $name,
                            [
                                'ip' => $host,
                                'port' => $port,
                                'serviceName' => $groupName . '@@' . $name,
                            ],
                            $groupName,
                            $this->config->get('services.drivers.nacos.namespace_id'),
                            null,
                            $lightBeatEnabled
                        );

                        $result = Json::decode((string) $response->getBody());

                        if ($response->getStatusCode() === 200) {
                            $this->logger->debug(sprintf('Instance %s:%d heartbeat successfully, result code:%s', $host, $port, $result['code']));
                        } else {
                            $this->logger->error(sprintf('Instance %s:%d heartbeat failed! %s', $host, $port, (string) $response->getBody()));
                            continue;
                        }

                        $lightBeatEnabled = false;
                        if (isset($result['lightBeatEnabled'])) {
                            $lightBeatEnabled = $result['lightBeatEnabled'];
                        }

                        if ($result['code'] == 20404) {
                            $this->client->instance->register($host, $port, $name, [
                                'groupName' => $this->config->get('services.drivers.nacos.group_name'),
                                'namespaceId' => $this->config->get('services.drivers.nacos.namespace_id'),
                                'metadata' => $this->getMetadata($name),
                            ]);
                        }
                    } catch (Throwable $exception) {
                        $this->logger->error('The nacos heartbeat failed, caused by ' . $exception);
                        throw $exception;
                    }
                }
            });
        });
    }

    private function getWeight($weight): int
    {
        return intval(100 * $weight);
    }
}
