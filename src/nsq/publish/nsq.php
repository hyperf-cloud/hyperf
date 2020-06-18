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
return [
    'default' => [
        'enable' => true,
        'host' => '127.0.0.1',
        'port' => 4150,
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => 60.0,
        ],
    ],
    'nsqlookup' => [
        'debug' => "false",
        'host' =>"127.0.0.1",
        'port' => 4161,
        'topic' =>"demoTopic",
        'channel' =>"demoChannel",
        'name' => "demoConsumer",
        'nums' =>2,
        'url' =>"/NODES",
        'cache_ttl' => "3600",
        'pool' =>[
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => 60.0
        ]

    ],
];
