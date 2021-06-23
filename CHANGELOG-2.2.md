# v2.2.0 - TBD

## Dependencies Upgrade

- Upgraded `friendsofphp/php-cs-fixer` to `^3.0`;
- Upgraded `psr/container` to `^1.0|^2.0`;
- Upgraded `egulias/email-validator` to `^3.0`;
- Upgraded `markrogoyski/math-php` to `^2.0`;

## Dependencies Changed

- [#3577](https://github.com/hyperf/hyperf/pull/3577) `domnikl/statsd` is abandoned and no longer maintained. The author suggests using the `slickdeals/statsd` package instead.

## Changed

- [#3334](https://github.com/hyperf/hyperf/pull/3334) Changed the return value of `LengthAwarePaginator::toArray()` to be consistent with that of `Paginator::toArray()`.
- [#3550](https://github.com/hyperf/hyperf/pull/3550) Removed `broker` and `bootstrap_server` from `kafka`, please use `brokers` and `bootstrap_servers` instead.
- [#3580](https://github.com/hyperf/hyperf/pull/3580) Changed the default priority of aspect to 0.
- [#3582](https://github.com/hyperf/hyperf/pull/3582) Changed the consumer tag of amqp to empty string.
- [#3634](https://github.com/hyperf/hyperf/pull/3634) Use Fork Process strategy to replace BetterReflection strategy.
  - [#3649](https://github.com/hyperf/hyperf/pull/3649) Removed `roave/better-reflection` from `hyperf/database` when using `gen:model`.
  - [#3651](https://github.com/hyperf/hyperf/pull/3651) Removed `roave/better-reflection` from LazyLoader.
  - [#3654](https://github.com/hyperf/hyperf/pull/3654) Removed `roave/better-reflection` from other components.
- [#3676](https://github.com/hyperf/hyperf/pull/3676) Use `promphp/prometheus_client_php` instead of `endclothing/prometheus_client_php`.
- [#3694](https://github.com/hyperf/hyperf/pull/3694) Changed `Hyperf\CircuitBreaker\CircuitBreakerInterface` to support php8.
  - Changed `CircuitBreaker::inc*Counter()` to `CircuitBreaker::incr*Counter()`.
  - Changed type hint for method `AbstractHandler::switch()`.
- [#3706](https://github.com/hyperf/hyperf/pull/3706) Changed the style of writing to `#[Middlewares(FooMiddleware::class)]` from `@Middlewares({@Middleware(FooMiddleware::class)})` in PHP8.
- [#3715](https://github.com/hyperf/hyperf/pull/3715) Restructure nacos component, be sure to reread the documents.
- [#3722](https://github.com/hyperf/hyperf/pull/3722) Removed config `config_apollo.php`, please use `config_center.php` instead.
- [#3725](https://github.com/hyperf/hyperf/pull/3725) Removed config `config_etcd.php`, please use `config_center.php` instead.

## Deprecated

- [#3636](https://github.com/hyperf/hyperf/pull/3636) `Hyperf\Utils\Resource` will be deprecated in v2.3, please use `Hyperf\Utils\ResourceGenerator` instead.

## Added

- [#3589](https://github.com/hyperf/hyperf/pull/3589) Added DAG component.
- [#3606](https://github.com/hyperf/hyperf/pull/3606) Added RPN component.
- [#3629](https://github.com/hyperf/hyperf/pull/3629) Added `Hyperf\Utils\Channel\ChannelManager` which used to manage channels.
- [#3631](https://github.com/hyperf/hyperf/pull/3631) Support multiplexing for AMQP component.
  - [#3639](https://github.com/hyperf/hyperf/pull/3639) Close push channel and socket when worker exited.
  - [#3640](https://github.com/hyperf/hyperf/pull/3640) Optimized log level for SwooleIO.
  - [#3657](https://github.com/hyperf/hyperf/pull/3657) Fixed memory exhausted for rabbitmq caused by confirm channel.
  - [#3659](https://github.com/hyperf/hyperf/pull/3659) Optimized code which be used to close connection friendly.
  - [#3681](https://github.com/hyperf/hyperf/pull/3681) Fixed bug that rpc client does not work for amqp.
- [#3635](https://github.com/hyperf/hyperf/pull/3635) Added `Hyperf\Utils\CodeGen\PhpParser` which used to generate AST for reflection. 
- [#3648](https://github.com/hyperf/hyperf/pull/3648) Added `Hyperf\Utils\CodeGen\PhpDocReaderManager` to manage `PhpDocReader`.
- [#3679](https://github.com/hyperf/hyperf/pull/3679) Added Nacos SDK component.
  - [#3712](https://github.com/hyperf/hyperf/pull/3712) The input parameters of `InstanceProvider::update()` are modified to make it more friendly.
- [#3698](https://github.com/hyperf/hyperf/pull/3698) Support PHP8 Attribute which can replace doctrine annotations.
- [#3714](https://github.com/hyperf/hyperf/pull/3714) Added ide-helper component.
- [#3722](https://github.com/hyperf/hyperf/pull/3722) Added config-center component.

## Optimized

- [#3670](https://github.com/hyperf/hyperf/pull/3670) Adapt database component to support php8.
- [#3673](https://github.com/hyperf/hyperf/pull/3673) Adapt all components to support php8.

## Fixed

- [#3650](https://github.com/hyperf/hyperf/pull/3650) Fixed bug that `ReflectionParameter::getClass()` will be deprecated in php8.
- [#3692](https://github.com/hyperf/hyperf/pull/3692) Fixed bug that class proxies couldn't be included when building phar.
