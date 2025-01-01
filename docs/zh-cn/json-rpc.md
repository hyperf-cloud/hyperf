# JSON RPC 服务

JSON RPC 是一种基于 JSON 格式的轻量级的 RPC 协议标准，易于使用和阅读。在 Hyperf 里由 [hyperf/json-rpc](https://github.com/hyperf/json-rpc) 组件来实现，可自定义基于 HTTP 协议来传输，或直接基于 TCP 协议来传输。

## 安装

```bash
composer require hyperf/json-rpc
```

该组件只是 JSON RPC 的协议处理的组件，通常来说，您仍需配合 [hyperf/rpc-server](https://github.com/hyperf/rpc-server) 或 [hyperf/rpc-client](https://github.com/hyperf/rpc-client) 来满足 服务端 和 客户端的场景，如同时使用则都需要安装：   

要使用 JSON RPC 服务端：

```bash
composer require hyperf/rpc-server
```

要使用 JSON RPC 客户端：

```bash
composer require hyperf/rpc-client
```

## 使用

服务有两种角色，一种是 `服务提供者(ServiceProvider)`，即为其它服务提供服务的服务，另一种是 `服务消费者(ServiceConsumer)`，即依赖其它服务的服务，一个服务既可能是 `服务提供者(ServiceProvider)`，同时又是 `服务消费者(ServiceConsumer)`。而两者直接可以通过 `服务契约` 来定义和约束接口的调用，在 Hyperf 里，可直接理解为就是一个 `接口类(Interface)`，通常来说这个接口类会同时出现在提供者和消费者下。

### 定义服务提供者

目前仅支持通过注解的形式来定义 `服务提供者(ServiceProvider)`，后续迭代会增加配置的形式。   
我们可以直接通过 `#[RpcService]` 注解对一个类进行定义即可发布这个服务了：

```php
<?php

namespace App\JsonRpc;

use Hyperf\RpcServer\Annotation\RpcService;

/**
 * 注意，如希望通过服务中心来管理服务，需在注解内增加 publishTo 属性
 */
#[RpcService(name: "CalculatorService", protocol: "jsonrpc-http", server: "jsonrpc-http")]
class CalculatorService implements CalculatorServiceInterface
{
    // 实现一个加法方法，这里简单的认为参数都是 int 类型
    public function add(int $a, int $b): int
    {
        // 这里是服务方法的具体实现
        return $a + $b;
    }
}
```

`#[RpcService]` 共有 `4` 个参数：   
`name` 属性为定义该服务的名称，这里定义一个全局唯一的名字即可，Hyperf 会根据该属性生成对应的 ID 注册到服务中心去；   
`protocol` 属性为定义该服务暴露的协议，目前仅支持 `jsonrpc-http`, `jsonrpc`, `jsonrpc-tcp-length-check` ，分别对应于 HTTP 协议和 TCP 协议下的两种协议，默认值为 `jsonrpc-http`，这里的值对应在 `Hyperf\Rpc\ProtocolManager` 里面注册的协议的 `key`，它们本质上都是 JSON RPC 协议，区别在于数据格式化、数据打包、数据传输器等不同。   
`server` 属性为绑定该服务类发布所要承载的 `Server`，默认值为 `jsonrpc-http`，该属性对应 `config/autoload/server.php` 文件内 `servers` 下所对应的 `name`，这里也就意味着我们需要定义一个对应的 `Server`；   
`publishTo` 属性为定义该服务所要发布的服务中心，目前仅支持 `consul`、`nacos` 或为空，为空时代表不发布该服务到服务中心去，但也就意味着您需要手动处理服务发现的问题，要使用此功能需安装 [hyperf/service-governance](https://github.com/hyperf/service-governance) 组件及对应的驱动依赖，具体可参考 [服务注册](zh-cn/service-register.md) 章节；

> 使用 `#[RpcService]` 注解需 `use Hyperf\RpcServer\Annotation\RpcService;` 命名空间。

#### 定义 JSON RPC Server

HTTP Server (适配 `jsonrpc-http` 协议)

```php
<?php

use Hyperf\Server\Server;
use Hyperf\Server\Event;

return [
    // 这里省略了该文件的其它配置
    'servers' => [
        [
            'name' => 'jsonrpc-http',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => 9504,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [\Hyperf\JsonRpc\HttpServer::class, 'onRequest'],
            ],
        ],
    ],
];
```

TCP Server (适配 `jsonrpc` 协议)

```php
<?php

use Hyperf\Server\Server;
use Hyperf\Server\Event;

return [
    // 这里省略了该文件的其它配置
    'servers' => [
        [
            'name' => 'jsonrpc',
            'type' => Server::SERVER_BASE,
            'host' => '0.0.0.0',
            'port' => 9503,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_RECEIVE => [\Hyperf\JsonRpc\TcpServer::class, 'onReceive'],
            ],
            'settings' => [
                'open_eof_split' => true,
                'package_eof' => "\r\n",
                'package_max_length' => 1024 * 1024 * 2,
            ],
        ],
    ],
];
```

TCP Server (适配 `jsonrpc-tcp-length-check` 协议)

当前协议为 `jsonrpc` 的扩展协议，用户可以很方便的修改对应的 `settings` 使用此协议，示例如下。

```php
<?php

use Hyperf\Server\Server;
use Hyperf\Server\Event;

return [
    // 这里省略了该文件的其它配置
    'servers' => [
        [
            'name' => 'jsonrpc',
            'type' => Server::SERVER_BASE,
            'host' => '0.0.0.0',
            'port' => 9503,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_RECEIVE => [\Hyperf\JsonRpc\TcpServer::class, 'onReceive'],
            ],
            'settings' => [
                'open_length_check' => true,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 2,
            ],
        ],
    ],
];
```

### 发布到服务中心

目前仅支持发布服务到 `consul`、`nacos`，后续会增加其它服务中心。   
发布服务到 `consul` 在 Hyperf 也是非常容易的一件事情，通过 `composer require hyperf/service-governance-consul` 引用组件（如果已安装则可忽略该步骤），然后再在 `config/autoload/services.php` 配置文件内配置 `drivers.consul` 配置即可。
发布服务到 `nacos` 在也是类似，通过 `composer require hyperf/service-governance-nacos` 引用组件（如果已安装则可忽略该步骤），然后再在 `config/autoload/services.php` 配置文件内配置 `drivers.nacos` 配置即可，示例如下：

```php
<?php
return [
    'enable' => [
        'discovery' => true,
        'register' => true,
    ],
    'consumers' => [],
    'providers' => [],
    'drivers' => [
        'consul' => [
            'uri' => 'http://127.0.0.1:8500',
            'token' => '',
        ],
        'nacos' => [
            // nacos server url like https://nacos.hyperf.io, Priority is higher than host:port
            // 'url' => '',
            // The nacos host info
            'host' => '127.0.0.1',
            'port' => 8848,
            // The nacos account info
            'username' => null,
            'password' => null,
            'guzzle' => [
                'config' => null,
            ],
            'group_name' => 'api',
            'namespace_id' => 'namespace_id',
            'heartbeat' => 5,
        ],
    ],
];
```

配置完成后，在启动服务时，Hyperf 会自动地将 `#[RpcService]` 定义了 `publishTo` 属性为 `consul` 或 `nacos` 的服务注册到对应的服务中心去。

> 目前仅支持 `jsonrpc` 和 `jsonrpc-http` 协议发布到服务中心去，其它协议尚未实现服务注册

### 定义服务消费者

一个 `服务消费者(ServiceConsumer)` 可以理解为就是一个客户端类，但在 Hyperf 里您无需处理连接和请求相关的事情，只需要进行一些鉴定配置即可。

#### 自动创建代理消费者类

您可通过在 `config/autoload/services.php` 配置文件内进行一些简单的配置，即可通过动态代理自动创建消费者类。

```php
<?php
return [
    // 此处省略了其它同层级的配置
    'consumers' => [
        [
            // name 需与服务提供者的 name 属性相同
            'name' => 'CalculatorService',
            // 服务接口名，可选，默认值等于 name 配置的值，如果 name 直接定义为接口类则可忽略此行配置，如 name 为字符串则需要配置 service 对应到接口类
            'service' => \App\JsonRpc\CalculatorServiceInterface::class,
            // 对应容器对象 ID，可选，默认值等于 service 配置的值，用来定义依赖注入的 key
            'id' => \App\JsonRpc\CalculatorServiceInterface::class,
            // 服务提供者的服务协议，可选，默认值为 jsonrpc-http
            // 可选 jsonrpc-http jsonrpc jsonrpc-tcp-length-check
            'protocol' => 'jsonrpc-http',
            // 负载均衡算法，可选，默认值为 random
            'load_balancer' => 'random',
            // 这个消费者要从哪个服务中心获取节点信息，如不配置则不会从服务中心获取节点信息
            'registry' => [
                'protocol' => 'consul',
                'address' => 'http://127.0.0.1:8500',
            ],
            // 如果没有指定上面的 registry 配置，即为直接对指定的节点进行消费，通过下面的 nodes 参数来配置服务提供者的节点信息
            'nodes' => [
                ['host' => '127.0.0.1', 'port' => 9504],
            ],
            // 配置项，会影响到 Packer 和 Transporter
            'options' => [
                'connect_timeout' => 5.0,
                'recv_timeout' => 5.0,
                'settings' => [
                    // 根据协议不同，区分配置
                    'open_eof_split' => true,
                    'package_eof' => "\r\n",
                    // 'open_length_check' => true,
                    // 'package_length_type' => 'N',
                    // 'package_length_offset' => 0,
                    // 'package_body_offset' => 4,
                ],
                // 重试次数，默认值为 2，收包超时不进行重试。暂只支持 JsonRpcPoolTransporter
                'retry_count' => 2,
                // 重试间隔，毫秒
                'retry_interval' => 100,
                // 使用多路复用 RPC 时的心跳间隔，null 为不触发心跳
                'heartbeat' => 30,
                // 当使用 JsonRpcPoolTransporter 时会用到以下配置
                'pool' => [
                    'min_connections' => 1,
                    'max_connections' => 32,
                    'connect_timeout' => 10.0,
                    'wait_timeout' => 3.0,
                    'heartbeat' => -1,
                    'max_idle_time' => 60.0,
                ],
            ],
        ]
    ],
];
```

在应用启动时会自动创建客户端类的代理对象，并在容器中使用配置项 `id` 的值（如果未设置，会使用配置项 `service` 值代替）来添加绑定关系，这样就和手工编写的客户端类一样，通过注入 `CalculatorServiceInterface` 接口来直接使用客户端。

> 当服务提供者使用接口类名发布服务名，在服务消费端只需要设置配置项 `name` 值为接口类名，不需要重复设置配置项 `id` 和 `service`。

#### 手动创建消费者类

如您对消费者类有更多的需求，您可通过手动创建一个消费者类来实现，只需要定义一个类及相关属性即可。

```php
<?php

namespace App\JsonRpc;

use Hyperf\RpcClient\AbstractServiceClient;

class CalculatorServiceConsumer extends AbstractServiceClient implements CalculatorServiceInterface
{
    /**
     * 定义对应服务提供者的服务名称
     */
    protected string $serviceName = 'CalculatorService';
    
    /**
     * 定义对应服务提供者的服务协议
     */
    protected string $protocol = 'jsonrpc-http';

    public function add(int $a, int $b): int
    {
        return $this->__request(__FUNCTION__, compact('a', 'b'));
    }
}
```

然后还需要在配置文件定义一个配置标记要从何服务中心获取节点信息，位于 `config/autoload/services.php` (如不存在可自行创建)

```php
<?php
return [
    // 此处省略了其它同层级的配置
    'consumers' => [
        [
            // 对应消费者类的 $serviceName
            'name' => 'CalculatorService',
            // 这个消费者要从哪个服务中心获取节点信息，如不配置则不会从服务中心获取节点信息
            'registry' => [
                'protocol' => 'consul',
                'address' => 'http://127.0.0.1:8500',
            ],
            // 如果没有指定上面的 registry 配置，即为直接对指定的节点进行消费，通过下面的 nodes 参数来配置服务提供者的节点信息
            'nodes' => [
                ['host' => '127.0.0.1', 'port' => 9504],
            ],
        ]
    ],
];
```


这样我们便可以通过 `CalculatorService` 类来实现对服务的消费了，为了让这里的关系逻辑更加的合理，还应该在 `config/autoload/dependencies.php` 内定义 `CalculatorServiceInterface` 和 `CalculatorServiceConsumer` 的关系，示例如下：

```php
return [
    App\JsonRpc\CalculatorServiceInterface::class => App\JsonRpc\CalculatorServiceConsumer::class,
];
```

这样便可以通过注入 `CalculatorServiceInterface` 接口来使用客户端了。

#### 配置复用

通常来说，一个服务消费者会同时消费多个服务提供者，当我们通过服务中心来发现服务提供者时， `config/autoload/services.php` 配置文件内就可能会重复配置很多次 `registry` 配置，但通常来说，我们的服务中心可能是统一的，也就意味着多个服务消费者配置都是从同样的服务中心去拉取节点信息，此时我们可以通过 `PHP 变量` 或 `循环` 等 PHP 代码来实现配置文件的生成。

##### 通过 PHP 变量生成配置

```php
<?php
$registry = [
   'protocol' => 'consul',
   'address' => 'http://127.0.0.1:8500',
];
return [
    // 下面的 FooService 和 BarService 仅示例多服务，并不是在文档示例中真实存在的
    'consumers' => [
        [
            'name' => 'FooService',
            'registry' => $registry,
        ],
        [
            'name' => 'BarService',
            'registry' => $registry,
        ]
    ],
];
```

##### 通过循环生成配置

```php
<?php
return [
    // 此处省略了其它同层级的配置
    'consumers' => value(function () {
        $consumers = [];
        // 这里示例自动创建代理消费者类的配置形式，顾存在 name 和 service 两个配置项，这里的做法不是唯一的，仅说明可以通过 PHP 代码来生成配置
        // 下面的 FooServiceInterface 和 BarServiceInterface 仅示例多服务，并不是在文档示例中真实存在的
        $services = [
            'FooService' => App\JsonRpc\FooServiceInterface::class,
            'BarService' => App\JsonRpc\BarServiceInterface::class,
        ];
        foreach ($services as $name => $interface) {
            $consumers[] = [
                'name' => $name,
                'service' => $interface,
                'registry' => [
                   'protocol' => 'consul',
                   'address' => 'http://127.0.0.1:8500',
                ]
            ];
        }
        return $consumers;
    }),
];
```

### 返回 PHP 对象

当框架导入 `symfony/serializer (^5.0)` 和 `symfony/property-access (^5.0)` 后，并在 `dependencies.php` 中配置一下映射关系

```php
use Hyperf\Serializer\SerializerFactory;
use Hyperf\Serializer\Serializer;

return [
    Hyperf\Contract\NormalizerInterface::class => new SerializerFactory(Serializer::class),
];
```

`NormalizerInterface` 就会支持对象的序列化和反序列化。暂时不支持这种 `MathValue[]` 对象数组。

定义返回对象

```php
<?php

declare(strict_types=1);

namespace App\JsonRpc;

class MathValue
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}
```

改写接口文件

```php
<?php

declare(strict_types=1);

namespace App\JsonRpc;

interface CalculatorServiceInterface
{
    public function sum(MathValue $v1, MathValue $v2): MathValue;
}
```

控制器中调用

```php
<?php

use Hyperf\Context\ApplicationContext;
use App\JsonRpc\CalculatorServiceInterface;
use App\JsonRpc\MathValue;

$client = ApplicationContext::getContainer()->get(CalculatorServiceInterface::class);

/** @var MathValue $result */
$result = $client->sum(new MathValue(1), new MathValue(2));

var_dump($result->value);
```

### 使用 JsonRpcPoolTransporter

框架提供了基于连接池的 `Transporter`，可以有效避免高并发时，建立过多连接的问题。这里可以通过替换 `JsonRpcTransporter` 的方式，使用 `JsonRpcPoolTransporter`。

修改 `dependencies.php` 文件

```php
<?php

declare(strict_types=1);

use Hyperf\JsonRpc\JsonRpcPoolTransporter;
use Hyperf\JsonRpc\JsonRpcTransporter;

return [
    JsonRpcTransporter::class => JsonRpcPoolTransporter::class,
];

```

### 协议&数据

框架中的默认定义了 `jsonrpc`, `jsonrpc-tcp-length-check`, `jsonrpc-http` 三种协议。主要区别在于数据的打包及传输方式，而数据结构及请求路径均相同。注册协议监听器代码示例
```php
## Hyperf\JsonRpc\Listener\RegisterProtocolListener

public function process(object $event): void
{
    $this->protocolManager->register('jsonrpc', [
        'packer' => JsonEofPacker::class,
        'transporter' => JsonRpcTransporter::class,
        'path-generator' => PathGenerator::class,
        'data-formatter' => DataFormatter::class,
    ]);

    $this->protocolManager->register('jsonrpc-tcp-length-check', [
        'packer' => JsonLengthPacker::class,
        'transporter' => JsonRpcTransporter::class,
        'path-generator' => PathGenerator::class,
        'data-formatter' => DataFormatter::class,
    ]);

    $this->protocolManager->register('jsonrpc-http', [
        'packer' => JsonPacker::class,
        'transporter' => JsonRpcHttpTransporter::class,
        'path-generator' => PathGenerator::class,
        'data-formatter' => DataFormatter::class,
    ]);
}

```
协议的数据结构符合[JSON-RPC2.0规范]((http://wiki.geekdream.com/Specification/json-rpc_2.0.html))。请求对象中会包含`jsonrpc`, `method`, `params`, `id` 四个成员。其中 params 为调用方法的参数值，method 为调用方法名称。
在启动 jsonrpc 服务时，框架会根据指定协议中的路径生成器来注册服务中方法的路由，发起请求时的 method 则需要与生成的路由对应。服务提供者示例
```php
<?php

namespace App\JsonRpc;

use Hyperf\RpcServer\Annotation\RpcService;

#[RpcService(name: 'Test', server: 'jsonRpc', protocol: 'jsonrpc', publishTo: 'nacos')]
class TestService
{
    public function hi() : int
    {
        return 1;
    }

}
```
此时会注册一个hi方法的路由为`/Test/hi`。调用方发起请求的数据结构应该为
```json
{"jsonrpc": "2.0", "method": "/Test/hi", "id": 1}
```
响应数据结构为
```json
{"jsonrpc": "2.0", "result": 1, "id": 1}

```