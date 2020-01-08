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

namespace Hyperf\ExceptionHandler;

use Hyperf\ExceptionHandler\Formatter\DefaultFormatter;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\ExceptionHandler\Listener\ExceptionHandlerAnnotation;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                FormatterInterface::class => DefaultFormatter::class,
            ],
            'listeners' => [
                ExceptionHandlerAnnotation::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}
