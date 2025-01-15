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

namespace Hyperf\WebSocketClient;
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [

            ],
            'publish' => [
                [
                    'id' => 'config',
                    'path' => __DIR__ . '/../publish/websocket_client.php',
                    'source' => 'websocket_client',
                ],
            ],
        ];
    }
}
