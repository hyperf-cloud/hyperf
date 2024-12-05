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

namespace Hyperf\Database\Sqlsrv;

use Hyperf\Database\Sqlsrv\Connectors\SqlServerConnector;
use Hyperf\Database\Sqlsrv\Listener\RegisterConnectionListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                'db.connector.sqlsrv' => SqlServerConnector::class,
            ],
            'listeners' => [
                RegisterConnectionListener::class,
            ],
        ];
    }
}
