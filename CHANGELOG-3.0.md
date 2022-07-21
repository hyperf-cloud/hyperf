# v3.0.0 - TBD

- [#4238](https://github.com/hyperf/hyperf/issues/4238) Upgraded the minimum php version to `^8.0` for all components;

## BC breaks

- 框架移除了 `@Annotation` 的支持，全部使用 `PHP8` 原生注解 `Attribute`，更新前务必检查项目中，是否已经全部替换为 `Attribute`。

可以执行以下脚本，将 `Doctrine Annotations` 转化为 `PHP8 Attributes`.

**注意: 这个脚本只能在 2.2 版本下执行**

```shell
composer require hyperf/code-generator
php bin/hyperf.php code:generate -D app
```

- 升级模型脚本

> 因为模型基类增加了成员变量的类型支持，所以需要使用以下脚本，将其升级为新版本。

```shell
composer require hyperf/code-generator
php vendor/bin/regenerate-models.php $PWD/app/Model
```

- 框架为类库增加了更多的类型限制，所以从 `2.2` 更新到 `3.0` 版本时，需要跑一遍静态检测。

```shell
composer analyse
```

## Dependencies Upgrade

- Upgraded `php-amqplib/php-amqplib` to `^3.1`;
- Upgraded `phpstan/phpstan` to `^1.0`;
- Upgraded `mix/redis-subscribe` to `mix/redis-subscriber:^3.0`
- Upgraded `psr/simple-cache` to `^1.0|^2.0|^3.0`
- Upgraded `monolog/monolog` to `^2.7|^3.1`
- Upgraded `league/flysystem` to `^1.0|^2.0|^3.0`

## Added

- [#4196](https://github.com/hyperf/hyperf/pull/4196) Added `Hyperf\Amqp\IO\IOFactory` which used to create amqp io by yourself.
- [#4304](https://github.com/hyperf/hyperf/pull/4304) Support `$suffix` for trait `Hyperf\Utils\Traits\StaticInstance`.
- [#4400](https://github.com/hyperf/hyperf/pull/4400) Added `$description` which used to set command description easily for `Hyperf\Command\Command`.
- [#4277](https://github.com/hyperf/hyperf/pull/4277) Added `Hyperf\Utils\IPReader` to get local IP.
- [#4497](https://github.com/hyperf/hyperf/pull/4497) Added `Hyperf\Coordinator\Timer` which can be stopped safely.
- [#4523](https://github.com/hyperf/hyperf/pull/4523) Support callback conditions for `Conditionable::when()` and `Conditionable::unless()`.
- [#4663](https://github.com/hyperf/hyperf/pull/4663) Make `Hyperf\Utils\Stringable` implements `Stringable`.
- [#4700](https://github.com/hyperf/hyperf/pull/4700) Support coroutine style server for `socketio-server`.
- [#4852](https://github.com/hyperf/hyperf/pull/4852) Added `NullDisableEventDispatcher` to disable event dispatcher by default.
- [#4866](https://github.com/hyperf/hyperf/pull/4866) [#4869](https://github.com/hyperf/hyperf/pull/4869) Added Annotation `Scene` which use scene in FormRequest easily.
- [#4908](https://github.com/hyperf/hyperf/pull/4908) Added `Db::beforeExecuting()` to register a hook which to be run just before a database query is executed.
- [#4909](https://github.com/hyperf/hyperf/pull/4909) Added `ConsumerMessageInterface::getNums()` to change the number of amqp consumer by dynamically.
- [#4918](https://github.com/hyperf/hyperf/pull/4918) Added `LoadBalancerInterface::afterRefreshed()` to register a hook which to be run after refresh nodes.

## Optimized

- [#4147](https://github.com/hyperf/hyperf/pull/4147) Optimized code for nacos which you can use `http://xxx.com/yyy/` instead of `http://xxx.com:8848/` to connect `nacos`.
- [#4367](https://github.com/hyperf/hyperf/pull/4367) Optimized `DataFormatterInterface` which uses object instead of array as inputs.
- [#4547](https://github.com/hyperf/hyperf/pull/4547) Optimized code of `Str::contains` `Str::startsWith` and `Str::endsWith` based on `PHP8`.
- [#4596](https://github.com/hyperf/hyperf/pull/4596) Optimized `Hyperf\Context\Context` which support `coroutineId` for `set()` `override()` and `getOrSet()`.
- [#4658](https://github.com/hyperf/hyperf/pull/4658) The method name is used as the routing path, when the path is null in route annotations.
- [#4668](https://github.com/hyperf/hyperf/pull/4668) Optimized class `Hyperf\Utils\Str` whose methods `padBoth` `padLeft` and `padRight` support `multibyte`.
- [#4678](https://github.com/hyperf/hyperf/pull/4679) Close all another servers when one of them closed.
- [#4688](https://github.com/hyperf/hyperf/pull/4688) Added `SafeCaller` to avoid server shutdown which caused by exceptions.
- [#4715](https://github.com/hyperf/hyperf/pull/4715) Adjust the order of injections for controllers to avoid inject null preferentially.
- [#4865](https://github.com/hyperf/hyperf/pull/4865) No need to check `Redis::isConnected()`, because it could be connected defer or reconnected after disconnected.
- [#4874](https://github.com/hyperf/hyperf/pull/4874) Use `wait` instead of `parallel` for coroutine style tcp server.
- [#4875](https://github.com/hyperf/hyperf/pull/4875) Use the original style when regenerating models.
- [#4880](https://github.com/hyperf/hyperf/pull/4880) Support `ignoreAnnotations` for `Annotation Reader`.
- [#4888](https://github.com/hyperf/hyperf/pull/4888) Removed useless `Hyperf\Di\ClassLoader::$proxies`, because merge it into `Composer\Autoload\ClassLoader::$classMap`.
- [#4905](https://github.com/hyperf/hyperf/pull/4905) Removed the redundant parameters of method `Hyperf\Database\Model\Concerns\HasEvents::fireModelEvent()`.
- [#4949](https://github.com/hyperf/hyperf/pull/4949) Removed useless `call()` from `Coroutine::create()`.

## Changed

- [#4199](https://github.com/hyperf/hyperf/pull/4199) Changed the `public` property `$message` to `protected` for `Hyperf\AsyncQueue\Event\Event`.
- [#4214](https://github.com/hyperf/hyperf/pull/4214) Renamed `$circularDependences` to `$checkCircularDependencies` for `Dag`.
- [#4225](https://github.com/hyperf/hyperf/pull/4225) Split `hyperf/coordinator` from `hyperf/utils`.
- [#4269](https://github.com/hyperf/hyperf/pull/4269) Changed the default priority of listener to `0` from `1`.
- [#4345](https://github.com/hyperf/hyperf/pull/4345) Renamed `Hyperf\Kafka\Exception\ConnectionCLosedException` to `Hyperf\Kafka\Exception\ConnectionClosedException`.
- [#4434](https://github.com/hyperf/hyperf/pull/4434) The method `Hyperf\Database\Model\Builder::insertOrIgnore` will be return affected count.
- [#4495](https://github.com/hyperf/hyperf/pull/4495) Changed the default value to `null` for `Hyperf\DbConnection\Db::__connection()`.
- [#4460](https://github.com/hyperf/hyperf/pull/4460) Use `??` instead of `?:` for `$callback` when using `Stringable::when()`.
- [#4502](https://github.com/hyperf/hyperf/pull/4502) Use `Hyperf\Engine\Channel` instead of `Hyperf\Coroutine\Channel` in `hyperf/reactive-x`.
- [#4611](https://github.com/hyperf/hyperf/pull/4611) Changed return type to `void` for `Hyperf\Event\Contract\ListenerInterface::process()`.
- [#4669](https://github.com/hyperf/hyperf/pull/4669) Changed all annotations which only support `PHP` >= `8.0`.
- [#4678](https://github.com/hyperf/hyperf/pull/4678) Support event dispatcher for command by default.
- [#4680](https://github.com/hyperf/hyperf/pull/4680) Stop processes which controlled by `ProcessManager` when server shutdown.
- [#4848](https://github.com/hyperf/hyperf/pull/4848) Changed `$value.timeout` to `$options.timeout` for `CircuitBreaker`.
- [#4930](https://github.com/hyperf/hyperf/pull/4930) Renamed method `AnnotationManager::getFormatedKey()` to `AnnotationManager::getFormattedKey()`.
- [#4934](https://github.com/hyperf/hyperf/pull/4934) Throw `NoNodesAvailableException` when cannot select any node from load balancer.
- [#4952](https://github.com/hyperf/hyperf/pull/4952) Don't write pid when the `settings.pid_file` is null when using swow server.

## Swow Supported

- [#4756](https://github.com/hyperf/hyperf/pull/4756) Support `hyperf/amqp`.
- [#4757](https://github.com/hyperf/hyperf/pull/4757) Support `Hyperf\Utils\Coroutine\Locker`.
- [#4804](https://github.com/hyperf/hyperf/pull/4804) Support `Hyperf\Utils\WaitGroup`.
- [#4808](https://github.com/hyperf/hyperf/pull/4808) Replaced `Swoole\Coroutine\Channel` by `Hyperf\Engine\Channel` for all components.
- [#4873](https://github.com/hyperf/hyperf/pull/4873) Support `hyperf/websocket-server`.
- [#4917](https://github.com/hyperf/hyperf/pull/4917) Support `hyperf/load-balancer`.
- [#4924](https://github.com/hyperf/hyperf/pull/4924) Support TcpServer for `hyperf/server`.

## Removed

- [#4199](https://github.com/hyperf/hyperf/pull/4199) Removed deprecated handler `Hyperf\AsyncQueue\Signal\DriverStopHandler`.
- [#4482](https://github.com/hyperf/hyperf/pull/4482) Removed deprecated `Hyperf\Utils\Resource`.
- [#4487](https://github.com/hyperf/hyperf/pull/4487) Removed log warning from cache component when the key is greater than 64 characters.
- [#4596](https://github.com/hyperf/hyperf/pull/4596) Removed `Hyperf\Utils\Context`, please use `Hyperf\Context\Context` instead.
- [#4623](https://github.com/hyperf/hyperf/pull/4623) Removed AliyunOssHook for `hyperf/filesystem`.
- [#4667](https://github.com/hyperf/hyperf/pull/4667) Removed `doctrine/annotations`, please use `PHP8 Attributes`.

## Deprecated

- `Hyperf\Utils\Contracts\Arrayable` will be deprecated, please use `Hyperf\Contract\Arrayable` instead.
- `Hyperf\AsyncQueue\Message` will be deprecated, please use `Hyperf\AsyncQueue\JobMessage` instead.

## Fixed

- [#4549](https://github.com/hyperf/hyperf/pull/4549) Fixed bug that `PhpParser::getExprFromValue()` does not support assoc array.
- [#4835](https://github.com/hyperf/hyperf/pull/4835) Fixed the lost description when using property `$description` and `$signature` for `hyperf/command`.
- [#4851](https://github.com/hyperf/hyperf/pull/4851) Fixed bug that prometheus server will not be closed automatically when using command which enable event dispatcher.
- [#4854](https://github.com/hyperf/hyperf/pull/4854) Fixed bug that the `socket-io` client always reconnect when using coroutine style server.
- [#4885](https://github.com/hyperf/hyperf/pull/4885) Fixed bug that `ProxyTrait::__getParamsMap` can not work when using trait alias.
- [#4892](https://github.com/hyperf/hyperf/pull/4892) [#4895](https://github.com/hyperf/hyperf/pull/4895) Fixed bug that `RedisAdapter::mixSubscribe` cannot work cased by redis prefix when using `socketio-server`.
- [#4910](https://github.com/hyperf/hyperf/pull/4910) Fixed bug that method `ComponentTagCompiler::escapeSingleQuotesOutsideOfPhpBlocks()` cannot work.
- [#4912](https://github.com/hyperf/hyperf/pull/4912) Fixed bug that websocket connection will be closed after 10s when using `Swow`.
- [#4919](https://github.com/hyperf/hyperf/pull/4919) [#4921](https://github.com/hyperf/hyperf/pull/4921) Fixed bug that rpc connections can't refresh themselves after nodes changed when using `rpc-multiplex`.
- [#4920](https://github.com/hyperf/hyperf/pull/4920) Fixed bug that the routing path is wrong (like `//foo`) when the routing prefix is end of '/'.
- [#4940](https://github.com/hyperf/hyperf/pull/4940) Fixed memory leak caused by an exception which occurred in `Parallel`.
