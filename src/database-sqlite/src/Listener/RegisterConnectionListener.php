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

namespace Hyperf\Database\SQLite\Listener;

use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Connection;
use Hyperf\Database\SQLite\SQLiteConnection;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Psr\Container\ContainerInterface;

class RegisterConnectionListener implements ListenerInterface
{
    /**
     * Create a new connection factory instance.
     */
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    /**
     * Register sqlite connection.
     */
    public function process(object $event): void
    {
        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            if ($config['database'] === ':memory:') {
                $connection = $this->createPersistentPdoResolver($connection, $config);
            }

            return new SQLiteConnection($connection, $database, $prefix, $config);
        });
    }

    protected function createPersistentPdoResolver($connection, $config)
    {
        return function () use ($config, $connection) {
            /** @var \Hyperf\Contract\ContainerInterface $container */
            $container = ApplicationContext::getContainer();
            $key = "sqlite.persistent.pdo.{$config['name']}";

            if (! $container->has($key)) {
                $container->set($key, call_user_func($connection));
            }

            return $container->get($key);
        };
    }
}
