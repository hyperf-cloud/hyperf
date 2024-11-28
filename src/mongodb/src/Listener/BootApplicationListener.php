<?php
declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Mongodb;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Illuminate\Database\Capsule\Manager as Capsule;
use Jenssegers\Mongodb\Connection;

class BootApplicationListener implements ListenerInterface
{
    /**
     * @var ConfigInterface
     */
    public $config;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event)
    {
        $mongo_config = $this->config->get('mongodb', []);
        if (!$mongo_config) {
            return;
        }
        $capsule = new Capsule();

        // register mongodb connection info
        foreach ($mongo_config as $connection_name => $connection_config) {
            $capsule->addConnection($connection_config, $connection_name);
        }
        $capsule->getDatabaseManager()
            ->extend('mongodb', function ($config, $name) {
                $config['name'] = $name;

                return new Connection($config);
            });

        $capsule->setAsGlobal();;

        $capsule->bootEloquent();
    }
}