# 令牌桶限流器

## 安裝

```bash
composer require hyperf/rate-limit
```

## 配置

### 釋出配置

```bash
php bin/hyperf.php vendor:publish hyperf/rate-limit
```

### 配置說明

|  配置          | 預設值 | 型別 |       備註        |
|:--------------:|:------:|:--------------:|:-------------------:|
| create         | 1      |int| 每秒生成令牌數      |
| consume        | 1      |int| 每次請求消耗令牌數  |
| capacity       | 2      |int| 令牌桶最大容量      |
| limitCallback  | `[]`   |null\|callable| 觸發限流時回撥方法  |
| waitTimeout    | 1      |int| 排隊超時時間        |
| key            | 當前請求 url 地址     |callable\|string| 限流的 key        |

## 使用限流器

元件提供 `Hyperf\RateLimit\Annotation\RateLimit` 註解，作用於類、類方法，可以覆蓋配置檔案。 例如，

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
配置優先順序 `方法註解 > 類註解 > 配置檔案 > 預設配置`

## 觸發限流
當限流被觸發時, 預設會丟擲 `Hyperf\RateLimit\Exception\RateLimitException` 異常

可以透過[異常處理](zh-tw/exception-handler.md)或者配置 `limitCallback` 限流回調處理。

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
        // $seconds 下次生成Token 的間隔, 單位為秒
        // $proceedingJoinPoint 此次請求執行的切入點
        // 可以透過呼叫 `$proceedingJoinPoint->process()` 繼續完成執行，或者自行處理
        return $proceedingJoinPoint->process();
    }
}
```

## 自定義令牌桶限流 key

預設的 key 是根據當前請求的 `url` ，當一個使用者觸發限流時，其他使用者也被限流請求此`url`；

若需要不同顆粒度的限流，如使用者維度的限流，可以針對使用者 `ID` 進行限流，達到 A 使用者被限流，B 使用者正常請求：

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
        // 同理可以根據手機號、IP地址等不同緯度進行限流
        return $request->input('user_id');
    }
}
```
