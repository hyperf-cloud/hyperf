<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Clickhouse;

class ConfigProvider
{
    public function __invoke()
    {
        return [
            'scan' => [
                'paths' => [
                    __DIR__,
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for clickhouse.',
                    'source' => __DIR__ . '/../publish/clickhouse.php',
                    'destination' => BASE_PATH . '/config/autoload/clickhouse.php',
                ],
            ],
        ];
    }
}
