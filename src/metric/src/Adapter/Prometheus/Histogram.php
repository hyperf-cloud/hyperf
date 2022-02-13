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
namespace Hyperf\Metric\Adapter\Prometheus;

use Hyperf\Metric\Contract\HistogramInterface;
use Prometheus\CollectorRegistry;

class Histogram implements HistogramInterface
{
    protected \Prometheus\Histogram $histogram;

    protected array $labelValues = [];

    public function __construct(protected CollectorRegistry $registry, string $namespace, string $name, string $help, array $labelNames)
    {
        $this->histogram = $registry->getOrRegisterHistogram($namespace, $name, $help, $labelNames);
    }

    public function with(string ...$labelValues): HistogramInterface
    {
        $this->labelValues = $labelValues;
        return $this;
    }

    public function put(float $sample): void
    {
        $this->histogram->observe($sample, $this->labelValues);
    }
}
