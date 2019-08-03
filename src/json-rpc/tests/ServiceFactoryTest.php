<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\JsonRpc;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\NormalizerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Scanner;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Di\DocMethodDefinitionCollector;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\JsonRpc\DataFormatter;
use Hyperf\JsonRpc\JsonRpcTransporter;
use Hyperf\JsonRpc\NormalizeDataFormatter;
use Hyperf\Logger\Logger;
use Hyperf\Rpc\PathGenerator;
use Hyperf\RpcClient\ServiceAdapter;
use Hyperf\RpcClient\ServiceFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Packer\JsonPacker;
use Hyperf\Utils\Serializer\SerializerFactory;
use Hyperf\Utils\Serializer\SymfonyNormalizer;
use HyperfTest\JsonRpc\Stub\CalculatorServiceInterface;
use HyperfTest\JsonRpc\Stub\IntegerValue;
use kuiper\docReader\DocReader;
use kuiper\docReader\DocReaderInterface;
use Mockery\MockInterface;
use Monolog\Handler\StreamHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 * @coversNothing
 */
class ServiceFactoryTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        ServiceAdapter::set('jsonrpc:' . CalculatorServiceInterface::class, null);
    }

    public function testSimple()
    {
        $container = $this->createContainer();
        /** @var MockInterface $transporter */
        $transporter = $container->get(JsonRpcTransporter::class);
        $transporter->shouldReceive('setLoadBalancer')
            ->andReturnSelf();
        $transporter->shouldReceive('send')
            ->andReturn(json_encode([
                'result' => 3,
            ]));
        $factory = new ServiceFactory($container);
        /** @var CalculatorServiceInterface $service */
        $service = $factory->createService(CalculatorServiceInterface::class, 'jsonrpc');
        $ret = $service->add(1, 2);
        $this->assertEquals(3, $ret);
    }

    public function testCreate()
    {
        $container = $this->createContainer();
        /** @var MockInterface $transporter */
        $transporter = $container->get(JsonRpcTransporter::class);
        $transporter->shouldReceive('setLoadBalancer')
            ->andReturnSelf();
        $transporter->shouldReceive('send')
            ->andReturn(json_encode([
                'result' => ['value' => 3],
            ]));
        $factory = new ServiceFactory($container);
        /** @var CalculatorServiceInterface $service */
        $service = $factory->createService(CalculatorServiceInterface::class, 'jsonrpc');
        $ret = $service->sum([IntegerValue::newInstance(1), IntegerValue::newInstance(2)]);
        $this->assertInstanceOf(IntegerValue::class, $ret);
        $this->assertEquals(3, $ret->getValue());
    }

    public function createContainer()
    {
        $transporter = \Mockery::mock(JsonRpcTransporter::class);
        $container = new Container(new DefinitionSource([
            NormalizerInterface::class => SymfonyNormalizer::class,
            Serializer::class => SerializerFactory::class,
            DocReaderInterface::class => DocReader::class,
            DataFormatter::class => NormalizeDataFormatter::class,
            MethodDefinitionCollectorInterface::class => DocMethodDefinitionCollector::class,
            StdoutLoggerInterface::class => function () {
                return new Logger('App', [new StreamHandler('php://stderr')]);
            },
            ConfigInterface::class => function () {
                return new Config([
                    'services' => [
                        'consumers' => [
                            [
                                'name' => CalculatorServiceInterface::class,
                                'nodes' => [
                                    ['host' => '0.0.0.0', 'port' => 1234],
                                ],
                            ],
                        ],
                    ],
                    'protocols' => [
                        'jsonrpc' => [
                            'packer' => JsonPacker::class,
                            'transporter' => JsonRpcTransporter::class,
                            'path-generator' => PathGenerator::class,
                            'data-formatter' => DataFormatter::class,
                        ],
                    ],
                ]);
            },
            JsonRpcTransporter::class => function () use ($transporter) {
                return $transporter;
            },
        ], [], new Scanner()));
        ApplicationContext::setContainer($container);
        return $container;
    }
}
