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
namespace Hyperf\Kafka\Event;

use Hyperf\Kafka\AbstractConsumer;

abstract class Consume extends Event
{
    protected mixed $data;

    public function __construct(AbstractConsumer $consumer, mixed $data)
    {
        parent::__construct($consumer);
        $this->data = $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
