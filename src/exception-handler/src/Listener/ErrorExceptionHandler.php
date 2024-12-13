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

namespace Hyperf\ExceptionHandler\Listener;

use ErrorException;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Psr\Container\ContainerInterface;

class ErrorExceptionHandler implements ListenerInterface
{
    protected ?StdoutLoggerInterface $logger = null;

    public function __construct(ContainerInterface $container)
    {
        if ($container->has(StdoutLoggerInterface::class)) {
            $this->logger = $container->get(StdoutLoggerInterface::class);
        }
    }

    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        $logger = $this->logger;
        set_error_handler(static function ($level, $message, $file = '', $line = 0) use ($logger): bool {
            if (error_reporting() & $level) {
                if ($line === 0) {
                    if ($logger) {
                        $logger->error($message);
                    } else {
                        echo $message . PHP_EOL;
                    }
                    return true;
                }

                throw new ErrorException($message, 0, $level, $file, $line);
            }

            return true;
        });
    }
}
