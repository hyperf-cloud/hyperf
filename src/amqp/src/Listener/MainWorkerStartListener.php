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

namespace Hyperf\Amqp\Listener;

use Doctrine\Instantiator\Instantiator;
use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\DeclaredExchanges;
use Hyperf\Amqp\Message\ProducerMessageInterface;
use Hyperf\Amqp\Producer as AmqpProducer;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Hyperf\Server\Event\MainCoroutineServerStart;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use Psr\Container\ContainerInterface;
use Throwable;

class MainWorkerStartListener implements ListenerInterface
{
    public function __construct(private ContainerInterface $container, private StdoutLoggerInterface $logger)
    {
    }

    /**
     * @return string[] returns the events that you want to listen
     */
    public function listen(): array
    {
        return [
            MainWorkerStart::class,
            MainCoroutineServerStart::class,
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process(object $event): void
    {
        if (! $this->isEnable()) {
            return;
        }

        // Declare exchange and routingKey
        $producerMessages = AnnotationCollector::getClassesByAnnotation(Producer::class);
        if ($producerMessages) {
            $producer = $this->container->get(AmqpProducer::class);
            $instantiator = $this->container->get(Instantiator::class);
            /**
             * @var string $producerMessageClass
             * @var Producer $annotation
             */
            foreach ($producerMessages as $producerMessageClass => $annotation) {
                $instance = $instantiator->instantiate($producerMessageClass);
                if (! $instance instanceof ProducerMessageInterface || ! $instance->isAutoDeclare()) {
                    continue;
                }
                $annotation->exchange && $instance->setExchange($annotation->exchange);
                $annotation->routingKey && $instance->setRoutingKey($annotation->routingKey);
                try {
                    $producer->declare($instance);
                    DeclaredExchanges::add($instance->getExchange());
                    $routingKey = $instance->getRoutingKey();
                    if (is_array($routingKey)) {
                        $routingKey = implode(',', $routingKey);
                    }
                    $this->logger->debug(sprintf('AMQP exchange[%s] and routingKey[%s] were created successfully.', $instance->getExchange(), $routingKey));
                } catch (AMQPProtocolChannelException $e) {
                    $this->logger->debug('AMQPProtocolChannelException: ' . $e->getMessage());
                    DeclaredExchanges::remove($instance->getExchange());
                    // Do nothing.
                } catch (Throwable $exception) {
                    $this->logger->error((string) $exception);
                }
            }
        }
    }

    protected function isEnable(): bool
    {
        if (! $this->container->has(ConfigInterface::class)) {
            return true;
        }

        $config = $this->container->get(ConfigInterface::class);
        return (bool) $config->get('amqp.enable', true);
    }
}
