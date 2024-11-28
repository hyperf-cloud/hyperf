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

interface ProducerMessageInterface extends MessageInterface
{
    public function setPayload($data);

    public function payload(): string;

    public function getProperties(): array;

    public function isAutoDeclare(): bool;
}
