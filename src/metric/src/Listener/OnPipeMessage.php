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
namespace Hyperf\Metric\Listener;

use Hyperf\Coroutine\Coroutine;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Metric\Adapter\RemoteProxy\Counter;
use Hyperf\Metric\Adapter\RemoteProxy\Gauge;
use Hyperf\Metric\Adapter\RemoteProxy\Histogram;
use Hyperf\Metric\Contract\MetricFactoryInterface;
use Hyperf\Process\Event\PipeMessage;

use function Hyperf\Support\make;

/**
 * Receives messages in metric process.
 */
class OnPipeMessage implements ListenerInterface
{
    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            PipeMessage::class,
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event): void
    {
        if (! $event instanceof PipeMessage) {
            return;
        }

        $inner = ! is_array($event->data) ? [$event->data] : $event->data;
        foreach ($inner as $data) {
            Coroutine::create(function () use ($data) {
                $this->processData($data);
            });
        }
    }

    protected function processData(object $data): void
    {
        $factory = make(MetricFactoryInterface::class);

        switch (true) {
            case $data instanceof Counter:
                $counter = $factory->makeCounter($data->name, $data->labelNames);
                $counter->with(...$data->labelValues)->add($data->delta);
                break;
            case $data instanceof Gauge:
                $gauge = $factory->makeGauge($data->name, $data->labelNames);
                if (isset($data->value)) {
                    $gauge->with(...$data->labelValues)->set($data->value);
                } else {
                    $gauge->with(...$data->labelValues)->add($data->delta);
                }
                break;
            case $data instanceof Histogram:
                $histogram = $factory->makeHistogram($data->name, $data->labelNames);
                $histogram->with(...$data->labelValues)->put($data->sample);
                break;
            default:
                // Nothing to do
                break;
        }
    }
}
