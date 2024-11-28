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
namespace HyperfTest\Amqp\Stub;

use Hyperf\Amqp\Message\ProducerMessage;

class DemoDisableAutoDeclareProducer extends ProducerMessage
{
    protected string $exchange = 'hyperf';

    protected array|string $routingKey = 'hyperf';

    public function __construct($data)
    {
        $this->payload = $data;
    }

    public function isAutoDeclare(): bool
    {
        return false;
    }
}
