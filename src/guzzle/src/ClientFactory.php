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
namespace Hyperf\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Hyperf\Coroutine\Coroutine;
use Psr\Container\ContainerInterface;

/**
 * @property \Hyperf\Di\Container $container
 */
class ClientFactory
{
    protected bool $runInSwoole = false;

    public function __construct(private ContainerInterface $container)
    {
        $this->runInSwoole = extension_loaded('swoole');
    }

    public function create(array $options = []): Client
    {
        $stack = null;

        if (
            $this->runInSwoole
            && Coroutine::inCoroutine()
        ) {
            $stack = HandlerStack::create(new CoroutineHandler());
        }

        $config = array_replace(['handler' => $stack], $options);

        if (method_exists($this->container, 'make')) {
            // Create by DI for AOP.
            return $this->container->make(Client::class, ['config' => $config]);
        }

        return new Client($config);
    }
}
