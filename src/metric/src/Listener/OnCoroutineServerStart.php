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

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Engine\Channel;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Metric\Adapter\Prometheus\Constants as PrometheusConstants;
use Hyperf\Metric\Aspect\MetricAspect;
use Hyperf\Metric\Contract\MetricFactoryInterface;
use Hyperf\Metric\Event\MetricFactoryReady;
use Hyperf\Metric\Exception\RuntimeException;
use Hyperf\Metric\MetricSetter;
use Hyperf\Server\Event\MainCoroutineServerStart;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function gc_status;
use function getrusage;
use function memory_get_peak_usage;
use function memory_get_usage;

/**
 * Collect and handle metrics before worker start.
 * Only used for swoole coroutine mode or swow mode.
 */
class OnCoroutineServerStart implements ListenerInterface
{
    use MetricSetter;

    protected MetricFactoryInterface $factory;

    private ConfigInterface $config;

    private Timer $timer;

    private bool $running = false;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(protected ContainerInterface $container)
    {
        $this->config = $container->get(ConfigInterface::class);
        $this->timer = new Timer();
    }

    public function listen(): array
    {
        return [
            MainCoroutineServerStart::class,
        ];
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(object $event): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;

        $this->factory = $this->container->get(MetricFactoryInterface::class);

        /*
         * If no standalone process is started, we have to handle metrics on worker.
         */
        if ($this->config->get('metric.use_standalone_process', true)) {
            throw new RuntimeException(
                "Coroutine mode server must be used in conjunction without standalone process. \n Set metric.use_standalone_process to false to avoid this error."
            );
        }

        $this->spawnHandle();
        $this->metricChannel();

        $eventDispatcher = $this->container->get(EventDispatcherInterface::class);
        $eventDispatcher->dispatch(new MetricFactoryReady($this->factory));

        if (! $this->config->get('metric.enable_default_metric', false)) {
            return;
        }

        // The following metrics MUST be collected in worker.
        $metrics = $this->factoryMetrics(
            ['worker' => '0'],
            // 'worker_request_count',
            // 'worker_dispatch_count',
            'memory_usage',
            'memory_peak_usage',
            'gc_runs',
            'gc_collected',
            'gc_threshold',
            'gc_roots',
            'ru_oublock',
            'ru_inblock',
            'ru_msgsnd',
            'ru_msgrcv',
            'ru_maxrss',
            'ru_ixrss',
            'ru_idrss',
            'ru_minflt',
            'ru_majflt',
            'ru_nsignals',
            'ru_nvcsw',
            'ru_nivcsw',
            'ru_nswap',
            'ru_utime_tv_usec',
            'ru_utime_tv_sec',
            'ru_stime_tv_usec',
            'ru_stime_tv_sec'
        );

        $timerInterval = $this->config->get('metric.default_metric_interval', 5);
        $timerId = $this->timer->tick($timerInterval, function () use ($metrics) {
            $this->trySet('gc_', $metrics, gc_status());
            $this->trySet('', $metrics, getrusage());

            $metrics['memory_usage']->set(memory_get_usage());
            $metrics['memory_peak_usage']->set(memory_get_peak_usage());
        });
        // Clean up timer on worker exit;
        Coroutine::create(function () use ($timerId) {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            $this->timer->clear($timerId);
        });
    }

    protected function metricChannel(): void
    {
        if ($this->config->get('metric.metric.prometheus.mode') !== PrometheusConstants::CUSTOM_MODE
            || ! method_exists($this->container, 'set')) {
            return;
        }

        $channel = new Channel(65535);

        $this->container->set(MetricAspect::METRIC_CHANNEL, $channel);

        Coroutine::create(function () use ($channel) {
            while (true) {
                $metric = $channel->pop();

                Coroutine::create(function () use ($metric) {
                    if (is_callable($metric)) {
                        try {
                            $metric();
                        } catch (Throwable $exception) {
                            if ($this->container->has(StdoutLoggerInterface::class) && $logger = $this->container->get(StdoutLoggerInterface::class)) {
                                $logger->warning((string) $exception);
                            }
                        }
                    }
                });
            }
        });
    }
}
