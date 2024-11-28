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
        'dsn' => env('MONGODB_DSN', 'mongodb://localhost:3717'),
        'database' => env('MONGODB_DB', 'log'),
        'options' => [],
    ],
];
