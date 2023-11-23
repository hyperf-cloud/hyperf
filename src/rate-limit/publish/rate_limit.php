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
use Hyperf\RateLimit\Storage\RedisStorageInterface;

return [
    'create' => 1,
    'consume' => 1,
    'capacity' => 2,
    'limitCallback' => [],
    'waitTimeout' => 1,
    'storage' => [
        'class' => RedisStorageInterface::class,
        'options' => [
            'pool' => 'default',
        ],
    ],
];
