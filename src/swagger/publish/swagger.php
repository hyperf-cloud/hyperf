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
return [
    'enable' => env('APP_ENV', 'dev') !== 'prod',
    'port' => 9500,
    'json' => '/storage/openapi.json',
    'html' => null,
    'url' => '/swagger',
];
