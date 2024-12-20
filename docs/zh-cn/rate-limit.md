# 令牌桶限流器

## 安装

```bash
composer require hyperf/rate-limit
```

## 配置

### 发布配置

```bash
php bin/hyperf.php vendor:publish hyperf/rate-limit
```

### 配置说明

|  配置          | 默认值 | 类型 |       备注        |
|:--------------:|:------:|:--------------:|:-------------------:|
| create         | 1      |int| 每秒生成令牌数      |
| consume        | 1      |int| 每次请求消耗令牌数  |
| capacity       | 2      |int| 令牌桶最大容量      |
| limitCallback  | `[]`   |null\|callable| 触发限流时回调方法  |
| waitTimeout    | 1      |int| 排队超时时间        |
| key            | 当前请求 url 地址     |callable\|string| 限流的 key        |

## 使用限流器

组件提供 `Hyperf\RateLimit\Annotation\RateLimit` 注解，作用于类、类方法，可以覆盖配置文件。 例如，

```php
<?php

namespace App\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\RateLimit\Annotation\RateLimit;

#[Controller(prefix: "rate-limit")]
class RateLimitController
{
    #[RequestMapping(path: "test")]
    #[RateLimit(create: 1, capacity: 3)]
    public function test()
    {
        return ["QPS 1, 峰值3"];
    }

    #[RequestMapping(path: "test2")]
    #[RateLimit(create: 2, consume: 2, capacity: 4)]
    public function test2()
    {
        return ["QPS 2, 峰值2"];
    }
}
``` 
配置优先级 `方法注解 > 类注解 > 配置文件 > 默认配置`

## 触发限流
当限流被触发时, 默认会抛出 `Hyperf\RateLimit\Exception\RateLimitException` 异常

可以通过[异常处理](zh-cn/exception-handler.md)或者配置 `limitCallback` 限流回调处理。

例如:
```php
<?php

namespace App\Controller;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\RateLimit\Annotation\RateLimit;

#[Controller(prefix: "rate-limit")]
#[RateLimit(limitCallback: [RateLimitController::class, "limitCallback"])]
class RateLimitController
{
    #[RequestMapping(path: "test")]
    #[RateLimit(create: 1, capacity: 3)]
    public function test()
    {
        return ["QPS 1, 峰值3"];
    }
    
    public static function limitCallback(float $seconds, ProceedingJoinPoint $proceedingJoinPoint)
    {
        // $seconds 下次生成Token 的间隔, 单位为秒
        // $proceedingJoinPoint 此次请求执行的切入点
        // 可以通过调用 `$proceedingJoinPoint->process()` 继续完成执行，或者自行处理
        return $proceedingJoinPoint->process();
    }
}
```

## 自定义令牌桶限流 key

默认的 key 是根据当前请求的 `url` ，当一个用户触发限流时，其他用户也被限流请求此`url`；

若需要不同颗粒度的限流，如用户维度的限流，可以针对用户 `ID` 进行限流，达到 A 用户被限流，B 用户正常请求：

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\RateLimit\Annotation\RateLimit;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;

class TestController
{
    /**
     * @RateLimit(create=1, capacity=3, key={TestController::class, "getUserId"})
     */
    public function test()
    {
        return ["QPS 1, 峰值3"];
    }

    public static function getUserId(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $request = ApplicationContext::getContainer()->get(RequestInterface::class);
        // 同理可以根据手机号、IP地址等不同纬度进行限流
        return $request->input('user_id');
    }
}
```
