# v3.0.50 - TBD

# v3.0.49 - 2024-05-16

## Added

- [#6764](https://github.com/hyperf/hyperf/pull/6764) Added `Premature end of data` into `DetectsLostConnections`.

# v3.0.48 - 2024-04-17

## Fixed

- [#6693](https://github.com/hyperf/hyperf/pull/6693) Fixed bug that socket-io cannot parse data with `?` but without `query`.

# v3.0.47 - 2024-01-11

## Optimized

- [#6455](https://github.com/hyperf/hyperf/pull/6455) Optimized code of Hyperf\SocketIOServer\Parser\Decoder::decode().

## Optimized

- [#6218](https://github.com/hyperf/hyperf/pull/6218) Optimized code for `Hyperf\Codec\Exception\InvalidArgumentException` which you can be used to get the origin data.

# v3.0.46 - 2023-11-30

## Fixed

- [#6321](https://github.com/hyperf/hyperf/pull/6321) Fixed bug that the jaeger cannot show the http code when using `tracer`.

# v3.0.45 - 2023-11-24

## Added

- [#6297](https://github.com/hyperf/hyperf/pull/6297) Added `Hyperf\Crontab\Mutex\ServerNodeInterface` to get unique name for server node.
- [#6300](https://github.com/hyperf/hyperf/pull/6300) Added `rate_limit.storage.options.pool` which can be used to select the pool of redis when using `rate-limit`.
- [#6306](https://github.com/hyperf/hyperf/pull/6306) Support rpc-server request lifecycle events.

## Fixed

- [#6298](https://github.com/hyperf/hyperf/pull/6298) Fixed complexity context for stdout logger.
- [#6303](https://github.com/hyperf/hyperf/pull/6303) Fixed bug that the PDO connection was broken when timed out.

## Optimized

- [#6302](https://github.com/hyperf/hyperf/pull/6302) Set catch exceptions to false in `Executor` class.
- [#6315](https://github.com/hyperf/hyperf/pull/6315) Optimized the tracer component by replacing the direct instantiation with a closure at Context::getOrSet.

# v3.0.44 - 2023-11-17

## Added

- [#6283](https://github.com/hyperf/hyperf/pull/6283) Added `grpc` request lifecycle support.
- [#6292](https://github.com/hyperf/hyperf/pull/6292) Added `Validator::setValue` and make `Validator::getValue` public.

## Fixed

- [#6284](https://github.com/hyperf/hyperf/pull/6284) Fixed bug that the crontab cannot be closed when throw exceptions.
- [#6291](https://github.com/hyperf/hyperf/pull/6291) Fixed that the crontab timer cannot be stopped sometimes.

## Optimized

- [#6293](https://github.com/hyperf/hyperf/pull/6293) Optimized the code about `Arr::flatten`, which is faster than before.

# v3.0.43 - 2023-11-10

## Added

- [#6272](https://github.com/hyperf/hyperf/pull/6272) Added `metric.enable_command_metric` switch.
- [#6273](https://github.com/hyperf/hyperf/pull/6273) Added `serverName` parameter to `Hyperf\HttpServer\Router\Dispatched`.

## Optimized

- [#6261](https://github.com/hyperf/hyperf/pull/6261) Handle `class_parents` returning `false` in `class_uses_recursive`.
- [#6271](https://github.com/hyperf/hyperf/pull/6271) Limit closure type crontab dispatch in WorkerStrategy.

# v3.0.42 - 2023-11-03

## Added

- [#6236](https://github.com/hyperf/hyperf/pull/6236) Support unionType param for GenerateModelIDEVisitor.
- [#6246](https://github.com/hyperf/hyperf/pull/6246) [#6252](https://github.com/hyperf/hyperf/pull/6252) Added crontab lifecycle events.
- [#6249](https://github.com/hyperf/hyperf/pull/6249) Support crontab closure type.

## Optimized

- [#6239](https://github.com/hyperf/hyperf/pull/6239) Improve amqp, use methods instead of `$delivery_info` and optimize `BeforeConsume` event.
- [#6250](https://github.com/hyperf/hyperf/pull/6250) Enhance scopeDoc for generateModelIdeVisitor.
- [#6253](https://github.com/hyperf/hyperf/pull/6253) Print error logs when collecting class reflections failed.
- [#6256](https://github.com/hyperf/hyperf/pull/6256) Refactor `hyperf/translation` classes to use typed properties.

# v3.0.41 - 2023-10-27

## Added

- [#6224](https://github.com/hyperf/hyperf/pull/6224) Added charset param and html method for Response.
- [#6232](https://github.com/hyperf/hyperf/pull/6232) Added `isNotFound` method for Dispatched.
- [#6233](https://github.com/hyperf/hyperf/pull/6233) Improved `AfterExecute` event of `hyperf/command`.

## Optimized

- [#6226](https://github.com/hyperf/hyperf/pull/6226) Don't send response when the connection of grpc server has been closed.
- [#6228](https://github.com/hyperf/hyperf/pull/6228) [#6229](https://github.com/hyperf/hyperf/pull/6229)  Make rules method in FormRequest optional.

# v3.0.40 - 2023-10-20

## Added

- [#6220](https://github.com/hyperf/hyperf/pull/6220) Added `Hyperf\Stringable\Str::replaceMatches()`.

## Fixed

- [#6217](https://github.com/hyperf/hyperf/pull/6217) Fixed bug that `Str::replaceLast` with empty search cannot work as expected.

## Optimized

- [#6209](https://github.com/hyperf/hyperf/pull/6209) Support for phpredis 6.x [sentinel](https://github.com/phpredis/phpredis/blob/develop/sentinel.md#examples-for-version-60-or-later)

# v3.0.39 - 2023-10-13

## Added

- [#6188](https://github.com/hyperf/hyperf/pull/6188) Added Redis options name to support string type.
- [#6193](https://github.com/hyperf/hyperf/pull/6193) Support http and websocket protocols at the same port for swow server.
- [#6198](https://github.com/hyperf/hyperf/pull/6198) Added `RpcAspect` and use it instead of `JsonRpcAspect`.
- [#6200](https://github.com/hyperf/hyperf/pull/6200) Added switch for `ElasticserachAspect` and `CoroutineAspect`.
- [#6203](https://github.com/hyperf/hyperf/pull/6203) Added `Hyperf\Tracer\Aspect\GrpcAspect`.
- [#6207](https://github.com/hyperf/hyperf/pull/6207) Added `exception_callback` option for `hyperf/kafka`.

# v3.0.38 - 2023-10-05

## Fixed

- [#6183](https://github.com/hyperf/hyperf/pull/6183) Fixed PriorityMiddleware for testing.
- [#6185](https://github.com/hyperf/hyperf/pull/6185) Fixed bug that `cleanUpExpiredOnce` failed when sids is empty when using `socketio-server`.

## Optimized

- [#6177](https://github.com/hyperf/hyperf/pull/6177) Optimized Base62 of `hyperf/codec` to support subclass rewriting.

# v3.0.37 - 2023-09-22

## Added

- [#6156](https://github.com/hyperf/hyperf/pull/6156) Enhance stringable, such as `replaceStart` `isJson`.

## Optimized

- [#6154](https://github.com/hyperf/hyperf/pull/6154) Use PHP native `json_validate` to validate is json if function is available.
- [#6157](https://github.com/hyperf/hyperf/pull/6157) Record the exception message only by opening the exception switch and closing the ignore switch when using `trace`.
- [#6167](https://github.com/hyperf/hyperf/pull/6167) Optimized KafkaClientFactory of `hyperf/tracer`.

## Fixed

- [#6160](https://github.com/hyperf/hyperf/pull/6160) Fixed bug that service governance still registers services even when `services.enable.register` is `false`.
- [#6162](https://github.com/hyperf/hyperf/pull/6162) [#6163](https://github.com/hyperf/hyperf/pull/6163) Fixed bug that the mutex lock of `crontab` cannot not work well when the crontab executed more than one hour (the default expired time).

# v3.0.36 - 2023-09-15

## Added

- [#6062](https://github.com/hyperf/hyperf/pull/6057) Added `RequestTraceListener` for `hyperf/tracer`.
- [#6143](https://github.com/hyperf/hyperf/pull/6143) Added `ignore_exceptions` for `hyperf/tracer`.

## Optimized

- [#6151](https://github.com/hyperf/hyperf/pull/6151) Optimized `FailToConsume` event for `hyperf/kafka`.

## Fixed

- [#6117](https://github.com/hyperf/hyperf/pull/6117) Fixed bug that grpc client cannot able to be reused.
- [#6146](https://github.com/hyperf/hyperf/pull/6146) Fixed bug that `validateJson` cannot work when using php 8.0.

# v3.0.35 - 2023-09-01

## Fixed

- [#6097](https://github.com/hyperf/hyperf/pull/6097) Fixed error that using non-zipkin driver of tracer.
- [#6099](https://github.com/hyperf/hyperf/pull/6099) Fixed bug that `ConstantFrequency` cannot work when using `redis`.
- [#6110](https://github.com/hyperf/hyperf/pull/6110) Fixed bug that configuration of other processes were not updated when using `nacos grpc client`.

## Added

- [#6096](https://github.com/hyperf/hyperf/pull/6096) Added `getThrowable` method to request events and crontab event.
- [#6094](https://github.com/hyperf/hyperf/pull/6094) Added some validation rules such as `ExcludeIf` `File` `ImageFile` and `ProhibitedIf`.
- [#6112](https://github.com/hyperf/hyperf/pull/6112) Added `sendSync` and `sendBatchAsync` methods for `Hyperf\Kafka\Producer`.

## Optimized

- [#6098](https://github.com/hyperf/hyperf/pull/6098) Optimize `kafka` reporter for `hyperf/tracer`.
- [#6100](https://github.com/hyperf/hyperf/pull/6100) Optimize `HttpClientFactory` for `hyperf/tracer`.
- [#6108](https://github.com/hyperf/hyperf/pull/6108) Optimize `describe:routes` command, sort middleware by MiddlewareManager
- [#6111](https://github.com/hyperf/hyperf/pull/6111) Allowed output log when an exception occurs.

# v3.0.34 - 2023-08-25

## Added

- [#6060](https://github.com/hyperf/hyperf/pull/6060) Supplement the missing tag configuration items.
- [#6063](https://github.com/hyperf/hyperf/pull/6063) Added `$server` property to request events.
- [#6070](https://github.com/hyperf/hyperf/pull/6070) Added `php_serialize` protocol for `hyperf/rpc-multilex`.
- [#6069](https://github.com/hyperf/hyperf/pull/6069) [#6075](https://github.com/hyperf/hyperf/pull/6075) Added kafka reporter for `hyperf/tracer`.
- [#6078](https://github.com/hyperf/hyperf/pull/6078) Added `Hyperf\Support\Composer::hasPackage()` method.
- [#6083](https://github.com/hyperf/hyperf/pull/6083) [#6084](https://github.com/hyperf/hyperf/pull/6084) Added middleware priority.

## Fixed

- [#6065](https://github.com/hyperf/hyperf/pull/6065) Fixed bug that `Context::override` and `Context::getOrSet` cannot work when using `$coroutineId`.
- [#6090](https://github.com/hyperf/hyperf/pull/6090) Fix the bug that cannot stop the command from terminating gracefully.

## Optimized

- [#6046](https://github.com/hyperf/hyperf/pull/6046) Using the tracer instance from coroutine context.
- [#6061](https://github.com/hyperf/hyperf/pull/6061) Enhance server config to support key-value mode.
- [#6077](https://github.com/hyperf/hyperf/pull/6077) Don't show deprecated notice when using `#[Hyperf\Constants\Annotation\Constants]`.
- [#6088](https://github.com/hyperf/hyperf/pull/6088) Optimize `Hyperf\Support\Composer`.

# v3.0.33 - 2023-08-18

## Fixed

- [#6011](https://github.com/hyperf/hyperf/pull/6011) Fixed the issue where validation for invocable controller route requests was not working.
- [#6013](https://github.com/hyperf/hyperf/pull/6013) Fixed the bug that `no_aspect` is overridden.
- [#6053](https://github.com/hyperf/hyperf/pull/6053) Fixed bug that `Arr::has` with integer keys cannot work as expected.

## Optimized

- [#6023](https://github.com/hyperf/hyperf/pull/6023) Allow using the tracer instance from context, append `Trace-Id` to Response Header.
- [#6027](https://github.com/hyperf/hyperf/pull/6027) Optimized the tracing in coroutine.

## Deprecated

- [#6044](https://github.com/hyperf/hyperf/pull/6044) Make `Hyperf\Coroutine\Traits\Container` as deprecated.

# v3.0.32 - 2023-08-09

## Added

- [#5996](https://github.com/hyperf/hyperf/pull/5996) Support aspect to `GuzzleHttp\Client::request()`.

## Fixed

- [#6004](https://github.com/hyperf/hyperf/pull/6004) Fixed bug that the exit code is invalid when using command to throw exceptions.

# v3.0.31 - 2023-07-27

## Fixed

- [#5969](https://github.com/hyperf/hyperf/pull/5969) Fixed bug that `Str::contains` will cause the error result when the `$needles` is `[null]`.
- [#5970](https://github.com/hyperf/hyperf/pull/5970) Fixed bug that `Str::startsWith` and `Str::endsWith` will cause the error result when the `$needles` is `[null]`.

## Added

- [#5971](https://github.com/hyperf/hyperf/pull/5971) Added `Str::containsIgnoreCase()` which determine if a given string contains a given substring regardless of case sensitivity.

# v3.0.30 - 2023-07-21

## Fixed

- [#5947](https://github.com/hyperf/hyperf/pull/5947) Fixed bug that lock failed when using more than one pool for amqp.

## Optimized

- [#5954](https://github.com/hyperf/hyperf/pull/5954) Optimized the model generator to generate correct property comments.

## Added

- [#5951](https://github.com/hyperf/hyperf/pull/5951) Added `SameSite` support to session cookies.
- [#5955](https://github.com/hyperf/hyperf/pull/5955) Support `access_key` and `access_secret` for nacos service governance.
- [#5957](https://github.com/hyperf/hyperf/pull/5957) Added `Hyperf\Codec\Packer\IgbinarySerializerPacker`.
- [#5962](https://github.com/hyperf/hyperf/pull/5962) Support modify the context of sub coroutine when using test components.

# v3.0.29 - 2023-07-14

## Fixed

- [#5921](https://github.com/hyperf/hyperf/pull/5921) Fixed bug that `http2-client` cannot be closed when didn't open heartbeat.
- [#5923](https://github.com/hyperf/hyperf/pull/5923) Fixed bug that `nacos grpc client` cannot be closed friendly when worker exit.
- [#5922](https://github.com/hyperf/hyperf/pull/5922) Fixed bug that `ApplicationContext` cannot be found when using `grpc-client`.

## Optimized

- [#5924](https://github.com/hyperf/hyperf/pull/5924) Hide the abnormal output when the worker exited.

# v3.0.28 - 2023-07-08

## Fixed

- [#5909](https://github.com/hyperf/hyperf/pull/5909) Fixed bug that acm `client::$servers` must be accessed before initialization.
- [#5911](https://github.com/hyperf/hyperf/pull/5911) Fixed bug that nacos grpc client auth failed.
- [#5912](https://github.com/hyperf/hyperf/pull/5912) Fixed bug that nacos grpc client cannot reconnect when the client closed.

## Added

- [#5895](https://github.com/hyperf/hyperf/pull/5895) Added strict mode support for `Integer` and `Boolean`.

## Optimized

- [#5910](https://github.com/hyperf/hyperf/pull/5910) Optimized code about `NacosClientFactory` which will create nacos client instead of nacos application.

# v3.0.27 - 2023-06-30

## Fixed

- [#5880](https://github.com/hyperf/hyperf/pull/5880) Fixed bug that start server failed caused by swagger server name isn't string.
- [#5890](https://github.com/hyperf/hyperf/pull/5890) Added some exception messages which used to reconnect PDO connection.

## Optimized

- [#5886](https://github.com/hyperf/hyperf/pull/5886) Throw exception (executing sql failed) when used `clickhouse` for `hyperf/db`.

# v3.0.26 - 2023-06-24

## Fixed

- [#5861](https://github.com/hyperf/hyperf/pull/5861) Fixed bug that `CoroutineMemory::clearPrefix()` cannot work as expected.

## Optimized

- [#5858](https://github.com/hyperf/hyperf/pull/5858) Throw exception when using `chunkById` but the column is not existed.
- [#5882](https://github.com/hyperf/hyperf/pull/5882) Move `ConfirmableTrait`/`DisableEventDispatcher`/`NullDisableEventDispatcher` to Concerns.

# v3.0.25 - 2023-06-19

## Fixed

- [#5829](https://github.com/hyperf/hyperf/pull/5829) Fixed bug that the method `Hyperf\Database\Model\Builder::value()` cannot work when using column like `table.column`.
- [#5831](https://github.com/hyperf/hyperf/pull/5831) Fixed an endless loop when socket.io parses namespace.

# v3.0.24 - 2023-06-10

## Fixed

- [#5794](https://github.com/hyperf/hyperf/pull/5794) Fixed bug that `__FILE__` and `__DIR__` cannot be rewritten successfully in proxy classes.
- [#5803](https://github.com/hyperf/hyperf/pull/5803) Fixed bug that `hyperf/http-server` cannot match psr7.
- [#5808](https://github.com/hyperf/hyperf/pull/5808) Fixed bug that validation rules `le`、`lte`、`gt`、`gte` do not support comparison between numeric and string values.

## Optimized

- [#5789](https://github.com/hyperf/hyperf/pull/5789) Support `psr/http-message`.
- [#5806](https://github.com/hyperf/hyperf/pull/5806) Merge swow server settings with config settings.
- [#5814](https://github.com/hyperf/hyperf/pull/5814) Added function `build_sql` which be used in `QueryException`.

# v3.0.23 - 2023-06-02

## Added

- [#5757](https://github.com/hyperf/hyperf/pull/5757) Support nacos naming signature.
- [#5765](https://github.com/hyperf/hyperf/pull/5765) Support Full-Text Search for `database`.

## Fixed

- [#5782](https://github.com/hyperf/hyperf/pull/5782) Fixed bug that prometheus cannot collect histograms.

## Optimized

- [#5768](https://github.com/hyperf/hyperf/pull/5768) Improved `Hyperf\Command\Annotation\Command`, support set properties for command.
- [#5780](https://github.com/hyperf/hyperf/pull/5780) Convert carrier key to string in `Zipkin\Propagation\Map`.

# v3.0.22 - 2023-05-27

## Added

- [#5760](https://github.com/hyperf/hyperf/pull/5760) Added namespace for functions of `hyperf/translation`.
- [#5761](https://github.com/hyperf/hyperf/pull/5761) Added `Hyperf\Coordinator\Timer::until()`.

## Optimized

- [#5741](https://github.com/hyperf/hyperf/pull/5741) Added deprecated comments to `Hyperf\DB\MySQLConnection`.
- [#5702](https://github.com/hyperf/hyperf/pull/5702) Optimized the code of `Hyperf\Metric\Adapter\Prometheus\Redis` which allowed to rewrite the prefix about redis keys.
- [#5762](https://github.com/hyperf/hyperf/pull/5762) Use non-blocking mode for swoole process by default.

# v3.0.21 - 2023-05-18

## Added

- [#5721](https://github.com/hyperf/hyperf/pull/5721) Added `exception` property to Request Lifecycle Events.
- [#5723](https://github.com/hyperf/hyperf/pull/5723) Support `Swoole 5 PgSQL` for `hyperf/db`.
- [#5725](https://github.com/hyperf/hyperf/pull/5725) Support `Swoole 4 PgSQL` for `hyperf/db`.
- [#5731](https://github.com/hyperf/hyperf/pull/5731) Added `Arr::hasAny()`.

## Fixed

- [#5726](https://github.com/hyperf/hyperf/pull/5726) [#5730](https://github.com/hyperf/hyperf/pull/5730) Fixed bug that pgsql cannot init when using pgsql-swoole.

## Optimized

- [#5718](https://github.com/hyperf/hyperf/pull/5718) Optimized the code and added some test cases for `view-engine`.
- [#5719](https://github.com/hyperf/hyperf/pull/5719) Optimized the code of `metric` and added some unit cases.
- [#5720](https://github.com/hyperf/hyperf/pull/5720) Optimized the code of `Hyperf\Metric\Listener\OnPipeMessage` to avoid message block.

# v3.0.20 - 2023-05-12

## Added

- [#5707](https://github.com/hyperf/hyperf/pull/5707) Added `Hyperf\Config\config` function.
- [#5711](https://github.com/hyperf/hyperf/pull/5711) Added `Arr::mapWithKeys()`.
- [#5715](https://github.com/hyperf/hyperf/pull/5715) Support http request lifecycle events.

## Fixed

- [#5709](https://github.com/hyperf/hyperf/pull/5709) Fixed bug that the error message is wrong when the logger group not found.
- [#5713](https://github.com/hyperf/hyperf/pull/5713) Support Server instance as default.

## Optimized

- [#5716](https://github.com/hyperf/hyperf/pull/5716) Support CoroutineServer for SuperGlobals.

# v3.0.19 - 2023-05-06

## Fixed

- [#5679](https://github.com/hyperf/hyperf/pull/5679) Fixed bug that the type of `$timeout` in `#[Task]` don't match `TaskAspect`.
- [#5684](https://github.com/hyperf/hyperf/pull/5684) Fixed bug that blade view engine cannot work when using break statement.

## Added

- [#5680](https://github.com/hyperf/hyperf/pull/5680) Support store context when using `rpc-multiplex`.
- [#5695](https://github.com/hyperf/hyperf/pull/5695) Added creation and update datetime columns.
- [#5699](https://github.com/hyperf/hyperf/pull/5699) Added `Model::resolveRelationUsing()` which you can set dynamic relation for model.

## Optimized

- [#5694](https://github.com/hyperf/hyperf/pull/5694) Remove `hyperf/utils` from `hyperf/rpc`.
- [#5696](https://github.com/hyperf/hyperf/pull/5696) Use `Hyperf\Coroutine\Coroutine::sleep()` instead of `Swoole\Coroutine::sleep()`.

# v3.0.18 - 2023-04-26

## Added

- [#5672](https://github.com/hyperf/hyperf/pull/5672) Added some helper functions in `hyperf/support`.

## Fixed

- [#5662](https://github.com/hyperf/hyperf/pull/5662) Fixed bug that `pgsql-swoole` cannot throw exceptions when statement execution failed.

## Optimized

- [#5660](https://github.com/hyperf/hyperf/pull/5660) Split `hyperf/codec` from `hyperf/utils`.
- [#5663](https://github.com/hyperf/hyperf/pull/5663) Split `hyperf/serializer` from `hyperf/utils`.
- [#5666](https://github.com/hyperf/hyperf/pull/5666) Split `Packers` to `hyperf/codec`.
- [#5668](https://github.com/hyperf/hyperf/pull/5668) Split `hyperf/support` from `hyperf/utils`.
- [#5670](https://github.com/hyperf/hyperf/pull/5670) Split `hyperf/code-parser` from `hyperf/utils`.
- [#5671](https://github.com/hyperf/hyperf/pull/5671) Use `Hyperf\Coroutine\Channel\Pool` instead of `Hyperf\Utils\ChannelPool`.
- [#5674](https://github.com/hyperf/hyperf/pull/5674) Instead of `classes` and `functions` of `Hyperf\Utils`.

# v3.0.17 - 2023-04-19

## Fixed

- [#5642](https://github.com/hyperf/hyperf/pull/5642) Fixed bug that the model cache cannot be created when using `find many` to get non-exists models.
- [#5643](https://github.com/hyperf/hyperf/pull/5643) Fixed bug that the empty caches cannot be used for `Model::findManyFromCache()`.
- [#5649](https://github.com/hyperf/hyperf/pull/5649) Fixed bug init table collector cannot work for coroutine style server.

## Added

- [#5634](https://github.com/hyperf/hyperf/pull/5634) Added `Hyperf\Stringable\str()` helper function.
- [#5639](https://github.com/hyperf/hyperf/pull/5639) Added `Redis::pipeline()` and `Redis::transaction()` support.
- [#5641](https://github.com/hyperf/hyperf/pull/5641) Support deeply nested cache relations for `model-cache`.
- [#5646](https://github.com/hyperf/hyperf/pull/5646) Added `PriorityDefinition` to sort dependencies.

## Optimized

- [#5634](https://github.com/hyperf/hyperf/pull/5634) Use `Hyperf\Stringable\Str` instead of `Hyperf\Utils\Str`.
- [#5636](https://github.com/hyperf/hyperf/pull/5636) Reduce kafka first start time and handle stop consumer logic
- [#5648](https://github.com/hyperf/hyperf/pull/5648) Removed requirement `hyperf/utils` from `hyperf/guzzle`.

# v3.0.16 - 2023-04-12

## Fixed

- [#5627](https://github.com/hyperf/hyperf/pull/5627) Fixed issue where coroutine context was not destroyed in `Hyperf\Context\Context::destroy` method.

## Optimized

- [#5616](https://github.com/hyperf/hyperf/pull/5616) Split `ApplicationContext` from `hyperf/utils` to `hyperf/context`.
- [#5617](https://github.com/hyperf/hyperf/pull/5617) Removed the requirement `hyperf/guzzle` from `hyperf/consul`.
- [#5618](https://github.com/hyperf/hyperf/pull/5618) Support to set the default router for swagger.
- [#5619](https://github.com/hyperf/hyperf/pull/5619) [#5620](https://github.com/hyperf/hyperf/pull/5620) Split `hyperf/coroutine` from `hyperf/utils`.
- [#5621](https://github.com/hyperf/hyperf/pull/5621) Use `Hyperf\Context\ApplicationContext` instead of `Hyperf\Utils\ApplicationContext`.
- [#5622](https://github.com/hyperf/hyperf/pull/5622) Split `CoroutineProxy` from `hyperf/utils` to `hyperf/context`.
- [#5623](https://github.com/hyperf/hyperf/pull/5623) Use `Hyperf\Coroutine\Coroutine` instead of `Hyperf\Utils\Coroutine`.
- [#5624](https://github.com/hyperf/hyperf/pull/5624) Split Channel utils from `hyperf/utils` to `hyperf/coroutine`.
- [#5629](https://github.com/hyperf/hyperf/pull/5629) Refactor `Hyperf\Utils\Arr` that let it extends `Hyperf\Collection\Arr`.

# v3.0.15 - 2023-04-07

## Added

- [#5606](https://github.com/hyperf/hyperf/pull/5606) Added `server.options.send_channel_capacity` to control whether to use safe socket.

## Optimized

- [#5593](https://github.com/hyperf/hyperf/pull/5593) [#5598](https://github.com/hyperf/hyperf/pull/5598) Use `Hyperf\Collection\Collection` instead of `Hyperf\Utils\Collection`.
- [#5594](https://github.com/hyperf/hyperf/pull/5594) Use `Hyperf\Collection\Arr` instead of `Hyperf\Utils\Arr`.
- [#5596](https://github.com/hyperf/hyperf/pull/5596) Split `hyperf/pipeline` from `hyperf/utils`.
- [#5599](https://github.com/hyperf/hyperf/pull/5599) Use Hyperf\Pipeline\Pipeline instead of Hyperf\Utils\Pipeline。

# v3.0.14 - 2023-04-01

## Fixed

- [#5578](https://github.com/hyperf/hyperf/pull/5578) Fixed bug that unable to serialize `Channel` in `Crontab`.
- [#5579](https://github.com/hyperf/hyperf/pull/5579) Fixed bug that `crontab:run` cannot work.

## Optimized

- [#5572](https://github.com/hyperf/hyperf/pull/5572) Update Http Server to use new WritableConnection implementation.
- [#5577](https://github.com/hyperf/hyperf/pull/5577) Split `hyperf/collection` from `hyperf/utils`.
- [#5580](https://github.com/hyperf/hyperf/pull/5580) Split `hyperf/conditionable` and `hyperf/tappable` from `hyperf/utils`.
- [#5585](https://github.com/hyperf/hyperf/pull/5585) Removed the requirement `consul` from `service-governance`.

# v3.0.13 - 2023-03-26

## Added

- [#5561](https://github.com/hyperf/hyperf/pull/5561) Added setTimer support for `hyperf/kafka`.
- [#5562](https://github.com/hyperf/hyperf/pull/5562) Added method `Query\Builder::upsert()`.
- [#5563](https://github.com/hyperf/hyperf/pull/5563) Added `running channel` to make sure all crontab tasks handled successfully.

## Optimized

- [#5544](https://github.com/hyperf/hyperf/pull/5554) Cancel `grpc-server`'s dependency on `hyperf/rpc`.
- [#5550](https://github.com/hyperf/hyperf/pull/5550) Optimized code for crontab parser and coordinator timer.
- [#5566](https://github.com/hyperf/hyperf/pull/5566) Optimized the type hint to `nullable` for schemas which generated by `cmd`.
- [#5569](https://github.com/hyperf/hyperf/pull/5569) Simplify RunCommand's dependencies.

# v3.0.12 - 2023-03-20

## Added

- [#4112](https://github.com/hyperf/hyperf/pull/4112) Added `kafka.default.enable` to control the consumer start or not.
- [#5533](https://github.com/hyperf/hyperf/pull/5533) [#5535](https://github.com/hyperf/hyperf/pull/5535) Added `client` & `socket` config for kafka.
- [#5536](https://github.com/hyperf/hyperf/pull/5536) Added `hyperf/http2-client`.
- [#5538](https://github.com/hyperf/hyperf/pull/5538) Support stream call for http2 client.
- [#5511](https://github.com/hyperf/hyperf/pull/5511) Support GRPC services which can easily to registry and discovery.
- [#5543](https://github.com/hyperf/hyperf/pull/5543) Support nacos grpc which used to listen config changed event.
- [#5545](https://github.com/hyperf/hyperf/pull/5545) Added streaming test cases for http2 client.
- [#5546](https://github.com/hyperf/hyperf/pull/5546) Support grpc streaming for config-nacos.

## Optimized

- [#5539](https://github.com/hyperf/hyperf/pull/5539) Optimized code for `AMQPConnection` to support the latest `php-amqplib`.
- [#5528](https://github.com/hyperf/hyperf/pull/5528) Optimized hot reload for `aspects`.
- [#5541](https://github.com/hyperf/hyperf/pull/5541) Improve FactoryResolver.

# v3.0.11 - 2023-03-15

## Added

- [#5499](https://github.com/hyperf/hyperf/pull/5499) Support `enum` for `hyperf/constants`.
- [#5508](https://github.com/hyperf/hyperf/pull/5508) Added `Hyperf\Rpc\Protocol::getNormalizer`.
- [#5509](https://github.com/hyperf/hyperf/pull/5509) Auto register `normalizer` for `json-rpc`.
- [#5513](https://github.com/hyperf/hyperf/pull/5513) Use default normalizer for `rpc-multiplex` and use `protocol.normalizer` for `rpc-server`.
- [#5518](https://github.com/hyperf/hyperf/pull/5518) Added `SwooleConnection::getSocket` to get swoole response.
- [#5520](https://github.com/hyperf/hyperf/pull/5520) Added `Coroutine::stats()` and `Coroutine::exists()`.
- [#5525](https://github.com/hyperf/hyperf/pull/5525) Added `kafka.default.consume_timeout` to control the consumer for consuming messages.
- [#5526](https://github.com/hyperf/hyperf/pull/5526) Added `Hyperf\Kafka\AbstractConsumer::isEnable()` to control the kafka consumer start or not.

## Fixed

- [#5519](https://github.com/hyperf/hyperf/pull/5519) Fixed bug that worker cannot exit caused by kafka `producer->loop()`.
- [#5523](https://github.com/hyperf/hyperf/pull/5523) Fixed bug that process stopped when kafka rebalance.

## Optimized

- [#5510](https://github.com/hyperf/hyperf/pull/5510) Allow developers to replace the `normalizer` of `RPC Client` themselves.
- [#5525](https://github.com/hyperf/hyperf/pull/5525) Running in an independent coroutine when consume kafka message. 

# v3.0.10 - 2023-03-11

## Fixed

- [#5497](https://github.com/hyperf/hyperf/pull/5497) Fixed bug that `ConfigChanged` cannot dispatched when using `apollo`.

## Added

- [#5491](https://github.com/hyperf/hyperf/pull/5491) Added `charAt` method to both `Str` and `Stringable`.
- [#5503](https://github.com/hyperf/hyperf/pull/5503) Added `Hyperf\Contract\JsonDeSerializable`.
- [#5504](https://github.com/hyperf/hyperf/pull/5504) Added `Hyperf\Utils\Serializer\JsonDeNormalizer`.

## Optimized

- [#5493](https://github.com/hyperf/hyperf/pull/5493) Optimized code for service registration which support nacos `1.x` and `2.x`.
- [#5494](https://github.com/hyperf/hyperf/pull/5494) [#5501](https://github.com/hyperf/hyperf/pull/5501) Do not replace `Handler` when `native-curl` is supported.

## Changed

- [#5492](https://github.com/hyperf/hyperf/pull/5492) Renamed `Hyperf\DbConnection\Listener\CreatingListener` to `Hyperf\DbConnection\Listener\InitUidOnCreatingListener`.

# v3.0.9 - 2023-03-05

## Added

- [#5467](https://github.com/hyperf/hyperf/pull/5467) Support `Google\Rpc\Status` for `GRPC`.
- [#5472](https://github.com/hyperf/hyperf/pull/5472) Support `ulid` and `uuid` for Model.
- [#5476](https://github.com/hyperf/hyperf/pull/5476) Added ArrayAccess to Stringable.
- [#5478](https://github.com/hyperf/hyperf/pull/5478) Added isMatch method to Str and Stringable helpers.

## Optimized

- [#5469](https://github.com/hyperf/hyperf/pull/5469) Ensure that the connection must be reset the next time after broken.

# v3.0.8 - 2023-02-26

## Fixed

- [#5433](https://github.com/hyperf/hyperf/pull/5433) [#5438](https://github.com/hyperf/hyperf/pull/5438) Fixed bug that the persistent service no need to send heartbeat.
- [#5464](https://github.com/hyperf/hyperf/pull/5464) Fixed bug that swagger server cannot work when using async style server.

## Added

- [#5434](https://github.com/hyperf/hyperf/pull/5434) Support UDP Server for Swow.
- [#5444](https://github.com/hyperf/hyperf/pull/5444) Added `GenSchemaCommand` to generate schemas for swagger.
- [#5451](https://github.com/hyperf/hyperf/pull/5451) Added method `appends($attributes)` to model collections.
- [#5453](https://github.com/hyperf/hyperf/pull/5453) Added missing methods `put()` and `patch()` to testing HTTP client.
- [#5454](https://github.com/hyperf/hyperf/pull/5454) Added method `Hyperf\Grpc\Parser::statusFromResponse`.
- [#5459](https://github.com/hyperf/hyperf/pull/5459) Added some methods of `uuid` and `ulid` for `Str` and `Stringable`.

## Optimized

- [#5437](https://github.com/hyperf/hyperf/pull/5437) Remove unnecessary `if `statement in `Str::length`.
- [#5439](https://github.com/hyperf/hyperf/pull/5439) Improve `Arr::shuffle`.

# v3.0.7 - 2023-02-18

## Added

- [#5042](https://github.com/hyperf/hyperf/pull/5402) Added `swagger.scan.paths` to rewrite `scan paths` for swagger.
- [#5403](https://github.com/hyperf/hyperf/pull/5403) Support swoole server settings for swow server.
- [#5404](https://github.com/hyperf/hyperf/pull/5404) Support multiport server for swagger.
- [#5406](https://github.com/hyperf/hyperf/pull/5406) Added `mixin` method to `Hyperf\Database\Model\Builder`.
- [#5407](https://github.com/hyperf/hyperf/pull/5407) Support HTTP methods `Delete` and `Options` for swagger.
- [#5409](https://github.com/hyperf/hyperf/pull/5409) Adds `methods` for `Query\Builder` and `Paginator`.
- [#5414](https://github.com/hyperf/hyperf/pull/5414) Added `clone` method to `Hyperf\Database\Model\Builder`.
- [#5418](https://github.com/hyperf/hyperf/pull/5418) Added `ConfigChanged` event to `config-center`.
- [#5429](https://github.com/hyperf/hyperf/pull/5429) Added `access_key` and `access_secret` which used to connect aliyun nacos.

## Fixed

- [#5405](https://github.com/hyperf/hyperf/pull/5405) Fixed get local ip error when IPv6 exists.
- [#5417](https://github.com/hyperf/hyperf/pull/5417) Fixed bug that database-pgsql does not support migration.
- [#5421](https://github.com/hyperf/hyperf/pull/5421) Fixed database about boolean types for where in the json type.
- [#5428](https://github.com/hyperf/hyperf/pull/5428) Fixed bug that metric middleware cannot work well when encountered an exception.
- [#5424](https://github.com/hyperf/hyperf/pull/5424) Fixed bug that migrator cannot work when using `PHP8.2`.

## Optimized

- [#5411](https://github.com/hyperf/hyperf/pull/5411) Optimized the code of `WebSocketHandeShakeException` which should inheritance `BadRequestHttpException`.
- [#5419](https://github.com/hyperf/hyperf/pull/5419) Optimized the code of `RPN`.
- [#5422](https://github.com/hyperf/hyperf/pull/5422) Enable swagger by default when installed swagger component.

# v3.0.6 - 2023-02-12

## Fixed

- [#5361](https://github.com/hyperf/hyperf/pull/5361) Fixed bug that the current service XXX is persistent service, can't register ephemeral instance.
- [#5382](https://github.com/hyperf/hyperf/pull/5382) Fixed bug that mix-subscriber cannot work caused by the empty auth.
- [#5386](https://github.com/hyperf/hyperf/pull/5386) Fixed bug that non-existing method `exec` called by `SwoolePostgresqlClient`.
- [#5394](https://github.com/hyperf/hyperf/pull/5394) Fixed bug that `hyperf/config-apollo` cannot work.

## Added

- [#5366](https://github.com/hyperf/hyperf/pull/5366) Added `forceDeleting` event to `hyperf/database`.
- [#5373](https://github.com/hyperf/hyperf/pull/5373) Support server settings for `SwowServer`.
- [#5376](https://github.com/hyperf/hyperf/pull/5376) Support coroutine server stats for `hyperf/metric`.
- [#5379](https://github.com/hyperf/hyperf/pull/5379) Added log records when nacos heartbeat failed.
- [#5389](https://github.com/hyperf/hyperf/pull/5389) Added swagger support.
- [#5395](https://github.com/hyperf/hyperf/pull/5395) Support validation for swagger.
- [#5397](https://github.com/hyperf/hyperf/pull/5397) Support all swagger annotations.

# v3.0.5 - 2023-02-05

## Added

- [#5338](https://github.com/hyperf/hyperf/pull/5338) Added `addRestoreOrCreate` extension to `SoftDeletingScope`.
- [#5349](https://github.com/hyperf/hyperf/pull/5349) Added `ResumeExitCoordinatorListener`.
- [#5355](https://github.com/hyperf/hyperf/pull/5355) Added `System::getCpuCoresNum()`.

## Fixed

- [#5357](https://github.com/hyperf/hyperf/pull/5357) Fixed bug that the coordinator timer can't stop when an exception occurs inside `$closure`.

## Optimized

- [#5342](https://github.com/hyperf/hyperf/pull/5342) Compatible with `tcp://host:port` configuration redis sentry address.

# v3.0.4 - 2023-01-22

## Fixed

- [#5332](https://github.com/hyperf/hyperf/pull/5332) Fixed bug that `PgSQLSwooleConnection::unprepared` cannot work.
- [#5333](https://github.com/hyperf/hyperf/pull/5333) Fixed bug that database cannot work when disconnect failed.

# v3.0.3 - 2023-01-16

## Fixed

- [#5318](https://github.com/hyperf/hyperf/pull/5318) Fixed bug that rate-limit cannot work when using php `8.1`.
- [#5324](https://github.com/hyperf/hyperf/pull/5324) Fixed bug that database cannot work when disconnect caused by connection reset by mysql.
- [#5322](https://github.com/hyperf/hyperf/pull/5322) Fixed bug that kafka consumer cannot work when don't set `memberId` and so on.
- [#5327](https://github.com/hyperf/hyperf/pull/5327) Fixed bug that PostgresSQL can't work when create connection timed out.

## Added

- [#5314](https://github.com/hyperf/hyperf/pull/5314) Added method `Hyperf\Coordinator\Timer::stats()`.
- [#5323](https://github.com/hyperf/hyperf/pull/5323) Added method `Hyperf\Nacos\Provider\ConfigProvider::listener()`.

## Optimized

- [#5308](https://github.com/hyperf/hyperf/pull/5308) [#5309](https://github.com/hyperf/hyperf/pull/5309) [#5310](https://github.com/hyperf/hyperf/pull/5310) [#5311](https://github.com/hyperf/hyperf/pull/5311) Added `CoroutineServer` Support for `hyperf/metric`.
- [#5315](https://github.com/hyperf/hyperf/pull/5315) Improve `hyperf/metric`.
- [#5326](https://github.com/hyperf/hyperf/pull/5326) Collect the metric of `Server::stats()` by loop.

# v3.0.2 - 2023-01-09

# Fixed

- [#5305](https://github.com/hyperf/hyperf/pull/5305) Fixed bug that commit failed when has no active transaction for polardb.
- [#5307](https://github.com/hyperf/hyperf/pull/5307) Fixed the parameter `$timeout` of `Timer::tick()` in `hyperf/metric`.

## Optimized

- [#5306](https://github.com/hyperf/hyperf/pull/5306) Log records when release to pool failed.

# v3.0.1 - 2023-01-09

## Fixed

- [#5289](https://github.com/hyperf/hyperf/pull/5289) Fixed bug that `signal` cannot work when using `swow`.
- [#5303](https://github.com/hyperf/hyperf/pull/5303) Fixed bug that redis nsq adapter cannot work when topics is null.

## Optimized

- [#5287](https://github.com/hyperf/hyperf/pull/5287) Added log records about the exception message when emit failed.
- [#5292](https://github.com/hyperf/hyperf/pull/5292) Support Swow for `hyperf/metric`.
- [#5301](https://github.com/hyperf/hyperf/pull/5301) Optimized code for `Hyperf\Rpc\PathGenerator\PathGenerator`.

# v3.0.0 - 2023-01-03

- [#4238](https://github.com/hyperf/hyperf/issues/4238) Upgraded the minimum php version to `^8.0` for all components;
- [#5087](https://github.com/hyperf/hyperf/pull/5087) Support PHP 8.2;

## BC breaks

- The framework removes `@Annotation` support, and uses `PHP8` native annotation `Attribute`. Before updating, be sure to check whether the project has been replaced by `Attribute`.

The following script can be executed to convert `Doctrine Annotations` to `PHP8 Attributes`.

**Note: This script can only be executed under version 2.2**

```shell
composer require hyperf/code-generator
php bin/hyperf.php code:generate -D app
```

- Database Model upgrade script

> Because the model base class has added type support for member variables, you need to use the following script to upgrade it to a new version.

```shell
composer require hyperf/code-generator
php vendor/bin/regenerate-models.php $PWD/app/Model
```

- The framework adds more type restrictions to the class library, so when updating from `2.2` to `3.0`, you need to run a static check to make sure it is works.

```shell
composer analysis
```

- The framework modifies the `Http status` returned by `gRPC Server` according to the `gRPC` specification. It is fixed at 200, and `gRPC Server` returns the corresponding `status code`. Service upgrade to version 3.x

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
- [#4992](https://github.com/hyperf/hyperf/pull/4992) Added config `amqp.enable` which used to control amqp consumer whether to start automatically and producer whether to declare automatically.
- [#4994](https://github.com/hyperf/hyperf/pull/4994) [#5016](https://github.com/hyperf/hyperf/pull/5016) Added component `hyperf/database-pgsql` which you can be used to connect pgsql server.
- [#5007](https://github.com/hyperf/hyperf/pull/5007) Support for SSL encrypted connection to Redis.
- [#5046](https://github.com/hyperf/hyperf/pull/5046) Added `Hyperf\Database\Model\Concerns\HasAttributes::getRawOriginal()`.
- [#5052](https://github.com/hyperf/hyperf/pull/5052) Support parsing IPv6 host.
- [#5061](https://github.com/hyperf/hyperf/pull/5061) Added config `symfony.event.enable` to control whether to use `SymfonyEventDispatcher`.
- [#5163](https://github.com/hyperf/hyperf/pull/5163) Added `Pipeline::thenReturn()` method to run pipes and return the result
- [#5160](https://github.com/hyperf/hyperf/pull/5160) Added `$dictionary` for `Str::slug`, your can rewrite some tags easily.
- [#5186](https://github.com/hyperf/hyperf/pull/5186) Added option `config` for command `server:watch`.
- [#5206](https://github.com/hyperf/hyperf/pull/5206) Support the transformation of object type to AST nodes.
- [#5211](https://github.com/hyperf/hyperf/pull/5211) Added Annotation `CacheAhead` which used to cache data ahead.
- [#5227](https://github.com/hyperf/hyperf/pull/5227) Added `Hyperf\WebSocketServer\Sender::getResponses()`.
- [#5250](https://github.com/hyperf/hyperf/pull/5250) Added `defer_release` config in `hyperf/db`
- [#5261](https://github.com/hyperf/hyperf/pull/5261) Added requirement `ext-posix` for `watcher`.

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
- [#4961](https://github.com/hyperf/hyperf/pull/4961) Removed proxy mode from `Hyperf\Di\ClassLoader` and Optimized `Composer::getLoader()`.
- [#4981](https://github.com/hyperf/hyperf/pull/4981) Confirm before proceeding with the action when using `ConfirmableTrait`, such as `migrate` command.
- [#5017](https://github.com/hyperf/hyperf/pull/5017) Check validity of file descriptor before sending message to it when using `socketio-server`.
- [#5029](https://github.com/hyperf/hyperf/pull/5029) Removed useless method `call()` from `callable function`.
- [#5078](https://github.com/hyperf/hyperf/pull/5078) Optimized code about creating exception from another exception.
- [#5079](https://github.com/hyperf/hyperf/pull/5079) Catch exception for function `defer` by default.

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
- [#4979](https://github.com/hyperf/hyperf/pull/4979) Don't support database commands by default, please require `hyperf/devtool` or set them in `autoload/commands`.
- [#5008](https://github.com/hyperf/hyperf/pull/5008) Removed array type of `Trace Annotation`, because don't support array.
- [#5036](https://github.com/hyperf/hyperf/pull/5036) Changed grpc server StatsCode and serializeMessage.
- [#5601](https://github.com/hyperf/hyperf/pull/5061) Don't use `Hyperf\Framework\SymfonyEventDispatcher` by default, if you listen symfony events, you must open `symfony.event.enable`.
- [#5079](https://github.com/hyperf/hyperf/pull/5079) Use `(string) $throwable` instead of `sprintf` for `Hyperf\ExceptionHandler\Formatter\FormatterInterface::format()`.
- [#5091](https://github.com/hyperf/hyperf/pull/5091) Move `Jsonable` and `Xmlable` to `contract` from `utils`.
- [#5092](https://github.com/hyperf/hyperf/pull/5092) Move `MessageBag` and `MessageProvider` to `contract` from `utils`.
- [#5204](https://github.com/hyperf/hyperf/pull/5204) Transform the type of param `$server` in `Hyperf\WebSocketServer\Server::deferOnOpen()` to `mixed`.
- [#5239](https://github.com/hyperf/hyperf/pull/5239) Throw exception when using `chunkById` but the column is not existed.

## Swow Supported

- [#4756](https://github.com/hyperf/hyperf/pull/4756) Support `hyperf/amqp`.
- [#4757](https://github.com/hyperf/hyperf/pull/4757) Support `Hyperf\Utils\Coroutine\Locker`.
- [#4804](https://github.com/hyperf/hyperf/pull/4804) Support `Hyperf\Utils\WaitGroup`.
- [#4808](https://github.com/hyperf/hyperf/pull/4808) Replaced `Swoole\Coroutine\Channel` by `Hyperf\Engine\Channel` for all components.
- [#4873](https://github.com/hyperf/hyperf/pull/4873) Support `hyperf/websocket-server`.
- [#4917](https://github.com/hyperf/hyperf/pull/4917) Support `hyperf/load-balancer`.
- [#4924](https://github.com/hyperf/hyperf/pull/4924) Support TcpServer for `hyperf/server`.
- [#4984](https://github.com/hyperf/hyperf/pull/4984) Support `hyperf/retry`.
- [#4988](https://github.com/hyperf/hyperf/pull/4988) Support `hyperf/pool`.
- [#4989](https://github.com/hyperf/hyperf/pull/4989) Support `hyperf/crontab`.
- [#4990](https://github.com/hyperf/hyperf/pull/4990) Support `hyperf/nsq`.
- [#5070](https://github.com/hyperf/hyperf/pull/5070) Support `hyperf/signal`.

## Removed

- [#4199](https://github.com/hyperf/hyperf/pull/4199) Removed deprecated handler `Hyperf\AsyncQueue\Signal\DriverStopHandler`.
- [#4482](https://github.com/hyperf/hyperf/pull/4482) Removed deprecated `Hyperf\Utils\Resource`.
- [#4487](https://github.com/hyperf/hyperf/pull/4487) Removed log warning from cache component when the key is greater than 64 characters.
- [#4596](https://github.com/hyperf/hyperf/pull/4596) Removed `Hyperf\Utils\Context`, please use `Hyperf\Context\Context` instead.
- [#4623](https://github.com/hyperf/hyperf/pull/4623) Removed AliyunOssHook for `hyperf/filesystem`.
- [#4667](https://github.com/hyperf/hyperf/pull/4667) Removed `doctrine/annotations`, please use `PHP8 Attributes`.
- [#5226](https://github.com/hyperf/hyperf/pull/5226) Removed `WARNING` log message when amqp connection restart.

## Deprecated

- `Hyperf\Utils\Contracts\Arrayable` will be deprecated, please use `Hyperf\Contract\Arrayable` instead.
- `Hyperf\AsyncQueue\Message` will be deprecated, please use `Hyperf\AsyncQueue\JobMessage` instead.
- `Hyperf\Di\Container::getDefinitionSource()` will be deprecated.

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
- [#5100](https://github.com/hyperf/hyperf/pull/5100) Fixed bug that the tag `continue` cannot work when using `view-engine`.
- [#5121](https://github.com/hyperf/hyperf/pull/5121) Fixed bug that the SQL is not valid but the correct error message cannot be obtained when using `pgsql`.
- [#5132](https://github.com/hyperf/hyperf/pull/5132) Fixed bug that the exit code of command does not work when the exception code isn't int.
- [#5142](https://github.com/hyperf/hyperf/pull/5142) Fixed bug that the method `Request::parseHost` does not work when host is invalid.
- [#5199](https://github.com/hyperf/hyperf/pull/5199) Fixed bug that `RedisSentinel` can't support empty password.
- [#5221](https://github.com/hyperf/hyperf/pull/5221) Fixed bug that `PGSqlSwooleConnection::affectingStatement()` can't work when the `sql` is wrong.
- [#5223](https://github.com/hyperf/hyperf/pull/5223) Fixed bug that `KeepaliveConnection::isTimeout()` can't work when using swow.
- [#5229](https://github.com/hyperf/hyperf/pull/5229) Fixed bug that proxy class will be generated failed when using parameters who allow null in constructor.
- [#5252](https://github.com/hyperf/hyperf/pull/5252) Fixed bug that generate rpc-client failed when the interface has parent interfaces.
- [#5268](https://github.com/hyperf/hyperf/pull/5268) Fixed bug that abstract methods will be written by `di`.
