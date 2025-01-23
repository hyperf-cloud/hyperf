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

namespace Hyperf\RpcClient;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\LoadBalancer\LoadBalancerInterface;
use Hyperf\LoadBalancer\LoadBalancerManager;
use Hyperf\LoadBalancer\Node;
use Hyperf\Rpc\Contract\DataFormatterInterface;
use Hyperf\Rpc\Contract\PathGeneratorInterface;
use Hyperf\Rpc\IdGenerator;
use Hyperf\Rpc\Protocol;
use Hyperf\Rpc\ProtocolManager;
use Hyperf\Rpc\Request;
use Hyperf\RpcClient\Exception\RequestException;
use Hyperf\ServiceGovernance\DriverInterface;
use Hyperf\ServiceGovernance\DriverManager;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\make;

abstract class AbstractServiceClient
{
    /**
     * The service name of the target service.
     */
    protected string $serviceName = '';

    /**
     * The protocol of the target service, this protocol name
     * needs to register into \Hyperf\Rpc\ProtocolManager.
     */
    protected string $protocol = 'jsonrpc-http';

    /**
     * The load balancer of the client, this name of the load balancer
     * needs to register into \Hyperf\LoadBalancer\LoadBalancerManager.
     */
    protected string $loadBalancer = 'random';

    protected Client $client;

    protected LoadBalancerManager $loadBalancerManager;

    protected ?IdGeneratorInterface $idGenerator;

    protected PathGeneratorInterface $pathGenerator;

    protected DataFormatterInterface $dataFormatter;

    protected ConfigInterface $config;

    public function __construct(protected ContainerInterface $container)
    {
        $this->config = $this->container->get(ConfigInterface::class);
        $this->loadBalancerManager = $container->get(LoadBalancerManager::class);
        $protocol = new Protocol($container, $container->get(ProtocolManager::class), $this->protocol, $this->getOptions());
        $loadBalancer = $this->createLoadBalancer(...$this->createNodes());
        $transporter = $protocol->getTransporter()->setLoadBalancer($loadBalancer);
        $this->client = make(Client::class)
            ->setPacker($protocol->getPacker())
            ->setTransporter($transporter)
            ->setNormalizer($protocol->getNormalizer());
        $this->idGenerator = $this->getIdGenerator();
        $this->pathGenerator = $protocol->getPathGenerator();
        $this->dataFormatter = $protocol->getDataFormatter();
    }

    protected function __request(string $method, array $params, ?string $id = null)
    {
        if (! $id && $this->idGenerator instanceof IdGeneratorInterface) {
            $id = $this->idGenerator->generate();
        }
        $response = $this->client->send($this->__generateData($method, $params, $id));
        if (is_array($response)) {
            $response = $this->checkRequestIdAndTryAgain($response, $id);

            if (array_key_exists('result', $response)) {
                return $response['result'];
            }
            if (array_key_exists('error', $response)) {
                return $response['error'];
            }
        }
        throw new RequestException('Invalid response.');
    }

    protected function __generateRpcPath(string $methodName): string
    {
        if (! $this->serviceName) {
            throw new InvalidArgumentException('Parameter $serviceName missing.');
        }
        return $this->pathGenerator->generate($this->serviceName, $methodName);
    }

    protected function __generateData(string $methodName, array $params, null|int|string $id)
    {
        return $this->dataFormatter->formatRequest(new Request(
            $this->__generateRpcPath($methodName),
            $params,
            $id,
            [
                'from' => $this->config->get('app_name'),
            ]
        ));
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    protected function getIdGenerator(): IdGeneratorInterface
    {
        if ($this->container->has(IdGenerator\IdGeneratorInterface::class)) {
            return $this->container->get(IdGenerator\IdGeneratorInterface::class);
        }

        if ($this->container->has(IdGeneratorInterface::class)) {
            return $this->container->get(IdGeneratorInterface::class);
        }

        return $this->container->get(IdGenerator\UniqidIdGenerator::class);
    }

    protected function createLoadBalancer(array $nodes, ?callable $refresh = null, bool $isLongPolling = false): LoadBalancerInterface
    {
        $loadBalancer = $this->loadBalancerManager->getInstance($this->serviceName, $this->loadBalancer)->setNodes($nodes);
        $refresh && $loadBalancer->refresh($refresh, $isLongPolling ? 1 : 5000);
        return $loadBalancer;
    }

    protected function getOptions(): array
    {
        $consumer = $this->getConsumerConfig();

        return $consumer['options'] ?? [];
    }

    protected function getConsumerConfig(): array
    {
        // According to the registry config of the consumer, retrieve the nodes.
        $consumers = $this->config->get('services.consumers', []);
        $config = [];
        foreach ($consumers as $consumer) {
            if (isset($consumer['name']) && $consumer['name'] === $this->serviceName) {
                $config = $consumer;
                break;
            }
        }

        return $config;
    }

    /**
     * Create nodes the first time.
     *
     * @return array [array, callable]
     */
    protected function createNodes(): array
    {
        $consumer = $this->getConsumerConfig();

        $registryProtocol = $consumer['registry']['protocol'] ?? null;
        $registryAddress = $consumer['registry']['address'] ?? '';
        // Current $consumer is the config of the specified consumer.
        if (! empty($registryProtocol) && $this->container->has(DriverManager::class)) {
            $governance = $this->container->get(DriverManager::class)->get($registryProtocol);
            if (! $governance) {
                throw new InvalidArgumentException(sprintf('Invalid protocol of registry %s', $registryProtocol));
            }
            $nodes = $this->getNodes($governance, $registryAddress, []);
            $refreshCallback = function (array $beforeNodes = []) use ($governance, $registryAddress) {
                return $this->getNodes($governance, $registryAddress, $beforeNodes);
            };

            return [$nodes, $refreshCallback, $governance->isLongPolling()];
        }

        // Not exists the registry config, then looking for the 'nodes' property.
        if (isset($consumer['nodes'])) {
            $nodes = [];
            foreach ($consumer['nodes'] as $item) {
                if (isset($item['host'], $item['port'])) {
                    if (! is_int($item['port'])) {
                        throw new InvalidArgumentException(sprintf('Invalid node config [%s], the port option has to a integer.', implode(':', $item)));
                    }
                    $nodes[] = new Node($item['host'], $item['port'], $item['weight'] ?? 0, $item['path_prefix'] ?? '');
                }
            }
            return [$nodes, null, false];
        }

        throw new InvalidArgumentException('Config of registry or nodes missing.');
    }

    protected function getNodes(DriverInterface $governance, string $address, array $nodes = []): array
    {
        $nodeArray = $governance->getNodes($address, $this->serviceName, [
            'protocol' => $this->protocol,
            'nodes' => $nodes,
        ]);

        $nodes = [];
        foreach ($nodeArray as $node) {
            $nodes[] = new Node($node['host'], $node['port'], $node['weight'] ?? 0, $node['path_prefix'] ?? '');
        }

        return $nodes;
    }

    protected function checkRequestIdAndTryAgain(array $response, $id, int $again = 1): array
    {
        if (is_null($id)) {
            // If the request id is null then do not check.
            return $response;
        }

        if (isset($response['id']) && $response['id'] === $id) {
            return $response;
        }

        if ($again <= 0) {
            throw new RequestException(sprintf(
                'Invalid response. Request id[%s] is not equal to response id[%s].',
                $id,
                $response['id'] ?? null
            ));
        }

        $response = $this->client->recv();
        --$again;

        return $this->checkRequestIdAndTryAgain($response, $id, $again);
    }
}
