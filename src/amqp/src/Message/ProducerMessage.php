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

namespace Hyperf\Amqp\Message;

use Hyperf\Amqp\Constants;
use Hyperf\Amqp\Packer\Packer;
use Hyperf\Context\ApplicationContext;

abstract class ProducerMessage extends Message implements ProducerMessageInterface
{
    protected mixed $payload = '';

    protected array|string $routingKey = '';

    protected array $properties
        = [
            'content_type' => 'text/plain',
            'delivery_mode' => Constants::DELIVERY_MODE_PERSISTENT,
        ];

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setPayload($data): self
    {
        $this->payload = $data;
        return $this;
    }

    public function payload(): string
    {
        return $this->serialize();
    }

    public function serialize(): string
    {
        $packer = ApplicationContext::getContainer()->get(Packer::class);
        return $packer->pack($this->payload);
    }

    public function isAutoDeclare(): bool
    {
        return true;
    }
}
