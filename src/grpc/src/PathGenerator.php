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

namespace Hyperf\Grpc;

use Hyperf\Rpc\Contract\GrpcPathGeneratorInterface;
use Hyperf\Stringable\Str;

class PathGenerator implements GrpcPathGeneratorInterface
{
    public function generate(string $service, string $method, array $options = []): string
    {
        $handledNamespace = explode('\\', $service);
        $handledNamespace = Str::replaceLast('Service', '', end($handledNamespace));

        $path = '/grpc.' . $handledNamespace . '/' . $method;
        if (isset($options['package'])) {
            $path = '/' . $options['package'] . '.' . $handledNamespace . '/' . $method;
        }

        return $path;
    }
}
