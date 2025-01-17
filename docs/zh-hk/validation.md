# 驗證器

## 前言

> [hyperf/validation](https://github.com/hyperf/validation) 衍生於 [illuminate/validation](https://github.com/illuminate/validation)，我們對它進行了一些改造，但保持了驗證規則的相同。在這裏感謝一下 Laravel 開發組，實現瞭如此強大好用的驗證器組件。

## 安裝

### 引入組件包

```bash
composer require hyperf/validation
```

### 添加中間件

您需要為使用到驗證器組件的 Server 在 `config/autoload/middlewares.php` 配置文件加上一個全局中間件 `Hyperf\Validation\Middleware\ValidationMiddleware` 的配置，如下為 `http` Server 加上對應的全局中間件的示例：

```php
<?php
return [
    // 下面的 http 字符串對應 config/autoload/server.php 內每個 server 的 name 屬性對應的值，意味着對應的中間件配置僅應用在該 Server 中
    'http' => [
        // 數組內配置您的全局中間件，順序根據該數組的順序
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
        // 這裏隱藏了其它中間件
    ],
];
```

> 如沒有正確設置全局中間件，可能會導致 `表單請求(FormRequest)` 的使用方式無效。

### 添加異常處理器

異常處理器主要對 `Hyperf\Validation\ValidationException` 異常進行處理，我們提供了一個 `Hyperf\Validation\ValidationExceptionHandler` 來進行處理，您需要手動將這個異常處理器配置到您的項目的 `config/autoload/exceptions.php` 文件內，當然，您也可以自定義您的異常處理器。

```php
<?php
return [
    'handler' => [
        // 這裏對應您當前的 Server 名稱
        'http' => [
            \Hyperf\Validation\ValidationExceptionHandler::class,
        ],
    ],
];
```

### 發佈驗證器語言文件

由於存在多語言的功能，故該組件依賴 [hyperf/translation](https://github.com/hyperf/translation) 組件，如您未曾添加過 Translation 組件的配置文件，請先執行下面的命令來發布 Translation 組件的配置文件，如您已經發布過或手動添加過，只需發佈驗證器組件的語言文件即可：

發佈 Translation 組件的文件：

```bash
php bin/hyperf.php vendor:publish hyperf/translation
```

發佈驗證器組件的文件：

```bash
php bin/hyperf.php vendor:publish hyperf/validation
```

執行上面的命令會將驗證器的語言文件 `validation.php` 發佈到對應的語言文件目錄，`en` 指英文語言文件，`zh_CN` 指中文簡體的語言文件，您可以按照實際需要對 `validation.php` 文件內容進行修改和自定義。

```shell
/storage
    /languages
        /en
            validation.php
        /zh_CN
            validation.php

```

## 使用

### 表單請求驗證

對於複雜的驗證場景，您可以創建一個 `表單請求(FormRequest)`，表單請求是包含驗證邏輯的一個自定義請求類，您可以通過執行下面的命令創建一個名為 `FooRequest` 的表單驗證類：

```bash
php bin/hyperf.php gen:request FooRequest
```

表單驗證類會生成於 `app\Request` 目錄下，如果該目錄不存在，運行命令時會自動創建目錄。   
接下來我們添加一些驗證規則到該類的 `rules` 方法：

```php
/**
 * 獲取應用到請求的驗證規則
 */
public function rules(): array
{
    return [
        'foo' => 'required|max:255',
        'bar' => 'required',
    ];
}
```

那麼，驗證規則要如何生效呢？您所要做的就是在控制器方法中通過類型提示聲明該請求類為參數。這樣在控制器方法被調用之前會驗證傳入的表單請求，這意味着你不需要在控制器中寫任何驗證邏輯並很好的解耦了這兩部分的代碼：

```php
<?php
namespace App\Controller;

use App\Request\FooRequest;

class IndexController
{
    public function index(FooRequest $request)
    {
        // 傳入的請求通過驗證...
        
        // 獲取通過驗證的數據...
        $validated = $request->validated();
    }
}
```

如果驗證失敗，驗證器會拋一個 `Hyperf\Validation\ValidationException` 異常，您可以在通過添加一個自定義的異常處理類來處理該異常，與此同時，我們也提供了一個`Hyperf\Validation\ValidationExceptionHandler` 異常處理器來處理該異常，您也可以直接配置我們提供的異常處理器來處理。但默認提供的異常處理器不一定能夠滿足您的需求，您可以根據情況通過自定義異常處理器自定義處理驗證失敗後的行為。

#### 自定義錯誤消息

您可以通過重寫 `messages` 方法來自定義表單請求使用的錯誤消息，該方法應該返回屬性/規則對數組及其對應錯誤消息：

```php
/**
 * 獲取已定義驗證規則的錯誤消息
 */
public function messages(): array
{
    return [
        'foo.required' => 'foo is required',
        'bar.required'  => 'bar is required',
    ];
}
```

#### 自定義驗證屬性

如果您希望將驗證消息中的 `:attribute` 部分替換為自定義的屬性名，則可以通過重寫 `attributes` 方法來指定自定義的名稱。該方法會返回屬性名及對應自定義名稱鍵值對數組：

```php
/**
 * 獲取驗證錯誤的自定義屬性
 */
public function attributes(): array
{
    return [
        'foo' => 'foo of request',
    ];
}
```

### 手動創建驗證器

如果您不想使用 `表單請求(FormRequest)` 的自動驗證功能，可以通過注入 `ValidatorFactoryInterface` 接口類來獲得驗證器工廠類，然後通過 `make` 方法手動創建一個驗證器實例：

```php
<?php

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class IndexController
{
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    public function foo(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'foo' => 'required',
                'bar' => 'required',
            ],
            [
                'foo.required' => 'foo is required',
                'bar.required' => 'bar is required',
            ]
        );

        if ($validator->fails()){
            // Handle exception
            $errorMessage = $validator->errors()->first();  
        }
        // Do something
    }
}
```

傳給 `make` 方法的第一個參數是需要驗證的數據，第二個參數則是該數據的驗證規則。

#### 自定義錯誤消息

如果有需要，你也可以使用自定義錯誤信息代替默認值進行驗證。有幾種方法可以指定自定義信息。首先，你可以將自定義信息作為第三個參數傳遞給 `make` 方法：

```php
<?php
$messages = [
    'required' => 'The :attribute field is required.',
];

$validator = $this->validationFactory->make($request->all(), $rules, $messages);
```

在這個例子中， `:attribute` 佔位符會被驗證字段的實際名稱替換。除此之外，你還可以在驗證消息中使用其它佔位符。例如：

```php
$messages = [
    'same'    => 'The :attribute and :other must match.',
    'size'    => 'The :attribute must be exactly :size.',
    'between' => 'The :attribute value :input is not between :min - :max.',
    'in'      => 'The :attribute must be one of the following types: :values',
];
```

#### 為給定屬性指定自定義信息

有時候你可能只想為特定的字段自定義錯誤信息。只需在屬性名稱後使用「點」來指定驗證的規則即可：

```php
$messages = [
    'email.required' => 'We need to know your e-mail address!',
];
```

#### 在 PHP 文件中指定自定義信息

在大多數情況下，您可能會在文件中指定自定義信息，而不是直接將它們傳遞給 `Validator` 。為此，需要把你的信息放置於 `storage/languages/xx/validation.php` 語言文件內的 `custom` 數組中。

#### 在 PHP 文件中指定自定義屬性

如果你希望將驗證信息的 `:attribute` 部分替換為自定義屬性名稱，你可以在 `storage/languages/xx/validation.php` 語言文件的 `attributes` 數組中指定自定義名稱：

```php
'attributes' => [
    'email' => 'email address',
],
```

### 驗證後鈎子

驗證器還允許你添加在驗證成功之後允許的回調函數，以便你進行下一步的驗證，甚至在消息集合中添加更多的錯誤消息。使用它只需在驗證實例上使用 `after` 方法：

```php
<?php

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class IndexController
{
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    public function foo(RequestInterface $request)
    {
        $validator = $this->validationFactory->make(
            $request->all(),
            [
                'foo' => 'required',
                'bar' => 'required',
            ],
            [
                'foo.required' => 'foo is required',
                'bar.required' => 'bar is required',
            ]
        );

        $validator->after(function ($validator) {
            if ($this->somethingElseIsInvalid()) {
                $validator->errors()->add('field', 'Something is wrong with this field!');
            }
        });
        
        if ($validator->fails()) {
            //
        }
    }
}
```

## 處理錯誤消息

通過 `Validator` 實例調用 `errors` 方法，會返回 `Hyperf\Support\MessageBag` 實例，它擁有各種方便的方法處理錯誤信息。

### 查看特定字段的第一個錯誤信息

要查看特定字段的第一個錯誤消息，可以使用 `first` 方法：

```php
$errors = $validator->errors();

echo $errors->first('foo');
```

### 查看特定字段的所有錯誤消息

如果你需要獲取指定字段的所有錯誤信息的數組，則可以使用 `get` 方法：

```php
foreach ($errors->get('foo') as $message) {
    //
}
```

如果要驗證表單的數組字段，你可以使用 `*` 來獲取每個數組元素的所有錯誤消息：

```php
foreach ($errors->get('foo.*') as $message) {
    //
}
```

### 查看所有字段的所有錯誤消息

如果你想要得到所有字段的所有錯誤消息，可以使用 `all` 方法：

```php
foreach ($errors->all() as $message) {
    //
}
```

### 判斷特定字段是否含有錯誤消息

`has` 方法可以被用來判斷指定字段是否存在錯誤信息:

```php
if ($errors->has('foo')) {
    //
}
```

### 場景

驗證器增加了場景功能，我們可以很方便的按需修改驗證規則。

> 此功能需要本組件版本大於等於 2.2.7

創建一個 `SceneRequest` 如下：

```php
<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class SceneRequest extends FormRequest
{
    protected array $scenes = [
        'foo' => ['username'],
        'bar' => ['username', 'password'],
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username' => 'required',
            'gender' => 'required',
        ];
    }
}
```

當我們正常使用時，會使用所有的驗證規則，即 `username` 和 `gender` 都是必填的。

我們可以設定場景，讓此次請求只驗證 `username` 必填。

如果我們配置了 `Hyperf\Validation\Middleware\ValidationMiddleware`，且將 `SceneRequest` 注入到方法上，
就會導致入參在中間件中直接進行驗證，故場景值無法生效，所以我們需要在方法裏從容器中獲取對應的 `SceneRequest`，進行場景切換。

```php
<?php

namespace App\Controller;

use App\Request\DebugRequest;
use App\Request\SceneRequest;
use Hyperf\HttpServer\Annotation\AutoController;

#[AutoController(prefix: 'foo')]
class FooController extends Controller
{
    public function scene()
    {
        $request = $this->container->get(SceneRequest::class);
        $request->scene('foo')->validateResolved();

        return $this->response->success($request->all());
    }
}
```

當然，我們也可以通過 `Scene` 註解切換場景

```php
<?php

namespace App\Controller;

use App\Request\DebugRequest;
use App\Request\SceneRequest;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\Validation\Annotation\Scene;

#[AutoController(prefix: 'foo')]
class FooController extends Controller
{
    #[Scene(scene:'bar1')]
    public function bar1(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }

    #[Scene(scene:'bar2', argument: 'request')] // 綁定到 $request
    public function bar2(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }

    #[Scene(scene:'bar3', argument: 'request')]
    #[Scene(scene:'bar3', argument: 'req')] // 支持多個參數
    public function bar3(SceneRequest $request, DebugRequest $req)
    {
        return $this->response->success($request->all());
    }

    #[Scene()] // 默認 scene 為方法名，效果等於 #[Scene(scene: 'bar1')]
    public function bar1(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }
}
```

#### 不同場景下同名參數

在兩個接口下定義相同的參數名是比較常見的，但此時不同場景的參數驗證要求可能不相同，我們需要區分定義，可以嘗試以下示例

```php
public function rules(): array
{
    return [
        'foo' => ['username'],
        'bar' => $this->ruleBar()
    ];
}

protected function ruleBar()
{
    return match ($this->getScene()) {
        'delete' => 'bail|json|required',
        'batch-delete' => 'bail|json',
        default => 'bail|required|json',
    };
}
```

## 驗證規則

下面是有效規則及其函數列表：

##### accepted

驗證字段的值必須是 `yes`、`on`、`1` 或 `true`，這在「同意服務協議」時很有用。

##### accepted_if:anotherfield,value,…
如果另一個正在驗證的字段等於指定的值，則驗證中的字段必須為 `yes`、`on`、`1` 或 `true`，這對於驗證「服務條款」接受或類似字段很有用。

##### declined
正在驗證的字段必須是 `no`、`off`、`0` 或者 `false`。

##### declined_if:anotherfield,value,…
如果另一個驗證字段的值等於指定值，則驗證字段的值必須為 `no`、`off`、`0` 或 `false`。

##### active_url

驗證字段必須是基於 `PHP` 函數 `dns_get_record` 的，有 `A` 或 `AAAA` 記錄的值。

##### after:date

驗證字段必須是給定日期之後的一個值，日期將會通過 PHP 函數 strtotime 傳遞：

```php
'start_date' => 'required|date|after:tomorrow'
```

你可以指定另外一個與日期進行比較的字段，而不是傳遞一個日期字符串給 strtotime 執行：

```php
'finish_date' => 'required|date|after:start_date'
```

##### after_or_equal:date

驗證字段必須是大於等於給定日期的值，更多信息，請參考 after:date 規則。

##### alpha

驗證字段必須是字母(包含中文)。 為了將此驗證規則限制在 ASCII 範圍內的字符（a-z 和 A-Z），你可以為驗證規則提供 ascii 選項：

```php
'username' => 'alpha:ascii',
```

##### alpha_dash

驗證字段可以包含字母(包含中文)和數字，以及破折號和下劃線。為了將此驗證規則限制在 ASCII 範圍內的字符（a-z 和 A-Z），你可以為驗證規則提供 ascii 選項：

```php
'username' => 'alpha_dash:ascii',
```

##### alpha_num

驗證字段必須是字母(包含中文)或數字。為了將此驗證規則限制在 ASCII 範圍內的字符（a-z 和 A-Z），你可以為驗證規則提供 ascii 選項：

```php
'username' => 'alpha_num:ascii',
```

##### ascii

正在驗證的字段必須完全是 7 位的 ASCII 字符。

##### array

驗證字段必須是 PHP 數組。

##### required_array_keys:foo,bar,…

驗證的字段必須是一個數組，並且必須至少包含指定的鍵。

##### bail

第一個驗證規則驗證失敗則停止運行其它驗證規則。

##### before:date

和 after:date 相對，驗證字段必須是指定日期之前的一個數值，日期將會傳遞給 PHP strtotime 函數。

##### before_or_equal:date

驗證字段必須小於等於給定日期。日期將會傳遞給 PHP 的 strtotime 函數。

##### between:min,max

驗證字段大小在給定的最小值和最大值之間，字符串、數字、數組和文件都可以像使用 size 規則一樣使用該規則：

'name' => 'required|between:1,20'

##### boolean

驗證字段必須可以被轉化為布爾值，接收 true, false, 1, 0, "1" 和 "0" 等輸入。

##### boolean:strict

驗證字段必須可以被轉化為布爾值，僅接收 true 和 false。

##### confirmed

驗證字段必須有一個匹配字段 foo_confirmation，例如，如果驗證字段是 password，必須輸入一個與之匹配的 password_confirmation 字段。

##### date

驗證字段必須是一個基於 PHP strtotime 函數的有效日期

##### date_equals:date

驗證字段必須等於給定日期，日期會被傳遞到 PHP strtotime 函數。

##### date_format:format

驗證字段必須匹配指定格式，可以使用 PHP 函數 date 或 date_format 驗證該字段。

##### decimal:min,max

驗證字段必須是數值類型，並且必須包含指定的小數位數：

```php
// 必須正好有兩位小數（例如 9.99）...
'price' => 'decimal:2'

// 必須有 2 到 4 位小數...
'price' => 'decimal:2,4'
```

##### lowercase

驗證的字段必須是小寫的。

##### uppercase

驗證字段必須為大寫。

##### mac_address

驗證的字段必須是一個 MAC 地址。

##### max_digits:value

驗證的整數必須具有最大長度 value。

##### min_digits:value

驗證的整數必須具有至少_value_位數。

##### exclude

`validate` 和 `validated` 方法中會排除掉當前驗證的字段。

##### exclude_if:anotherfield,value
如果 `anotherfield` 等於 `value` ，`validate` 和 `validated` 方法中會排除掉當前驗證的字段。

在一些複雜的場景，也可以使用 `Rule::excludeIf` 方法，這個方法需要返回一個布爾值或者一個匿名函數。如果返回的是匿名函數，那麼這個函數應該返回 `true` 或 `false` 去決定被驗證的字段是否應該被排除掉：

```php
use Hyperf\Validation\Rule;

$this->validationFactory->make($request->all(), [
    'role_id' => Rule::excludeIf($request->user()->is_admin),
]);

$this->validationFactory->make($request->all(), [
    'role_id' => Rule::excludeIf(fn () => $request->user()->is_admin),
]);
```

##### prohibited

需要驗證的字段必須不存在或為空。如果符合以下條件之一，字段將被認為是 “空”：

1. 值為 `null`。
2. 值為空字符串。
3. 值為空數組或空的可計數對象。
4. 值為上傳文件，但文件路徑為空。

##### prohibited_if:anotherfield,value,…

如果 `anotherfield` 字段等於任何 `value`，則需要驗證的字段必須不存在或為空。如果符合以下條件之一，字段將被認為是 “空”：

1. 值為 `null`。
2. 值為空字符串。
3. 值為空數組或空的可計數對象。
4. 值為上傳文件，但文件路徑為空。

如果需要複雜的條件禁止邏輯，則可以使用 `Rule::prohibitedIf` 方法。該方法接受一個布爾值或一個閉包。當給定一個閉包時，閉包應返回 `true` 或 `false`，以指示是否應禁止驗證字段：


```php
use Hyperf\Validation\Rule;

$this->validationFactory->make($request->all(), [
    'role_id' => Rule::prohibitedIf($request->user()->is_admin),
]);

$this->validationFactory->make($request->all(), [
    'role_id' => Rule::prohibitedIf(fn () => $request->user()->is_admin),
]);
```


##### missing

驗證的字段在輸入數據中必須不存在。

##### missing_if:anotherfield,value,…

如果 `_anotherfield_` 字段等於任何 `_value_` ，則驗證的字段必須不存在。

##### missing_unless:anotherfield,value

驗證的字段必須不存在，除非 `_anotherfield_` 字段等於任何 `_value_` 。

##### missing_with:foo,bar,…

如果任何其他指定的字段存在，則驗證的字段必須不存在。

##### missing_with_all:foo,bar,…

如果所有其他指定的字段都存在，則驗證的字段必須不存在。

##### multiple_of:value

驗證的字段必須是 `_value_` 的倍數。

##### doesnt_start_with:foo,bar,…

驗證的字段不能以給定值之一開頭。

##### doesnt_end_with:foo,bar,…

驗證的字段不能以給定值之一結尾。

##### different:field

驗證字段必須是一個和指定字段不同的值。

##### digits:value

驗證字段必須是數字且長度為 `value` 指定的值。

##### digits_between:min,max

驗證字段數值長度必須介於最小值和最大值之間。

##### dimensions

驗證的圖片尺寸必須滿足該規定參數指定的約束條件：

```php
'avatar' => 'dimensions:min_width=100,min_height=200'
```

有效的約束條件包括：`min_width`, `max_width`, `min_height`, `max_height`, `width`, `height`, `ratio`。

`ratio` 約束寬度/高度的比率，這可以通過表達式 `3/2` 或浮點數 `1.5` 來表示：

```php
'avatar' => 'dimensions:ratio=3/2'
```

由於該規則要求多個參數，可以使用 `Rule::dimensions` 方法來構造該規則：

```php
use Hyperf\Validation\Rule;

public function rules(): array
{
return [
           'avatar' => [
              'required',
              Rule::dimensions()->maxWidth(1000)->maxHeight(500)->ratio(3 / 2),
           ],
       ];
}
```
##### distinct

處理數組時，驗證字段不能包含重複值：

```php
'foo.*.id' => 'distinct'
```

##### email

驗證字段必須是格式正確的電子郵件地址。

##### exists:table,column

驗證字段必須存在於指定數據表。

基本使用：

```php
'state' => 'exists:states'
```

如果 `column` 選項沒有指定，將會使用字段名。

指定自定義列名：

```php
'state' => 'exists:states,abbreviation'
```

有時，你可能需要為 `exists` 查詢指定要使用的數據庫連接，這可以在表名前通過`.`前置數據庫連接來實現：

```php
'email' => 'exists:connection.staff,email'
```

如果你想要自定義驗證規則執行的查詢，可以使用 `Rule` 類來定義規則。在這個例子中，我們還以數組形式指定了驗證規則，而不是使用 `|` 字符來限定它們：

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'email' => [
        'required',
        Rule::exists('staff')->where(function ($query) {
            $query->where('account_id', 1);
        }),
    ],
]);
```

##### file

驗證字段必須是上傳成功的文件。

##### filled

驗證字段如果存在則不能為空。

##### gt:field

驗證字段必須大於給定 `field` 字段，這兩個字段類型必須一致，適用於字符串、數字、數組和文件，和 `size` 規則類似

##### gte:field

驗證字段必須大於等於給定 `field` 字段，這兩個字段類型必須一致，適用於字符串、數字、數組和文件，和 `size` 規則類似

##### image

驗證文件必須是圖片（`jpeg`、`png`、`bmp`、`gif` 或者 `svg`）

##### in:foo,bar…

驗證字段值必須在給定的列表中，由於該規則經常需要我們對數組進行 `implode`，我們可以使用 `Rule::in` 來構造這個規則：

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'zones' => [
        'required',
        Rule::in(['first-zone', 'second-zone']),
    ],
]);
```

##### in_array:anotherfield

驗證字段必須在另一個字段值中存在。

##### integer

驗證字段必須是整型（String 和 Integer 類型都可以通過驗證）。

##### integer:strict

驗證字段必須是整型（只有 Integer 類型都可以通過驗證）。

##### ip

驗證字段必須是 IP 地址。

##### ipv4

驗證字段必須是 IPv4 地址。

##### ipv6

驗證字段必須是 IPv6 地址。

##### json

驗證字段必須是有效的 JSON 字符串

##### lt:field

驗證字段必須小於給定 `field` 字段，這兩個字段類型必須一致，適用於字符串、數字、數組和文件，和 `size` 規則類似

##### lte:field

驗證字段必須小於等於給定 `field` 字段，這兩個字段類型必須一致，適用於字符串、數字、數組和文件，和 `size` 規則類似

##### max:value

驗證字段必須小於等於最大值，和字符串、數值、數組、文件字段的 `size` 規則使用方式一樣。

##### mimetypes：text/plain…

驗證文件必須匹配給定的 `MIME` 文件類型之一：

```php
'video' => 'mimetypes:video/avi,video/mpeg,video/quicktime'
```

為了判斷上傳文件的 `MIME` 類型，組件將會讀取文件內容來猜測 `MIME` 類型，這可能會和客户端 `MIME` 類型不同。

##### mimes:foo,bar,…

驗證文件的 `MIME` 類型必須是該規則列出的擴展類型中的一個
`MIME` 規則的基本使用：

```php
'photo' => 'mimes:jpeg,bmp,png'
```

儘管你只是指定了擴展名，該規則實際上驗證的是通過讀取文件內容獲取到的文件 `MIME` 類型。
完整的 `MIME` 類型列表及其相應的擴展可以在這裏找到：[mime types](http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

##### min:value

與 `max:value` 相對，驗證字段必須大於等於最小值，對字符串、數值、數組、文件字段而言，和 `size` 規則使用方式一致。

##### not_in:foo,bar,…

驗證字段值不能在給定列表中，和 `in` 規則類似，我們可以使用 `Rule::notIn` 方法來構建規則：

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'toppings' => [
        'required',
        Rule::notIn(['sprinkles', 'cherries']),
    ],
]);
```

##### not_regex:pattern

驗證字段不能匹配給定正則表達式

注：使用 `regex/not_regex` 模式時，規則必須放在數組中，而不能使用管道分隔符，尤其是正則表達式中包含管道符號時。

##### nullable

驗證字段可以是 `null`，這在驗證一些可以為 `null` 的原始數據如整型或字符串時很有用。

##### numeric

驗證字段必須是數值

##### present

驗證字段必須出現在輸入數據中但可以為空。

##### regex:pattern

驗證字段必須匹配給定正則表達式。
該規則底層使用的是 `PHP` 的 `preg_match` 函數。因此，指定的模式需要遵循 `preg_match` 函數所要求的格式並且包含有效的分隔符。例如:

```php
 'email' => 'regex:/^.+@.+$/i'
```

注：使用 `regex/not_regex` 模式時，規則必須放在數組中，而不能使用管道分隔符，尤其是正則表達式中包含管道符號時。

##### required

驗證字段值不能為空，以下情況字段值都為空：
值為`null`
值是空字符串
值是空數組或者空的 `Countable` 對象
值是上傳文件但路徑為空

##### required_if:anotherfield,value,…

驗證字段在 `anotherfield` 等於指定值 `value` 時必須存在且不能為空。
如果你想要為 `required_if` 規則構造更復雜的條件，可以使用 `Rule::requiredIf` 方法，該方法接收一個布爾值或閉包。當傳遞一個閉包時，會返回 `true` 或 `false` 以表明驗證字段是否是必須的：

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($request->all(), [
    'role_id' => Rule::requiredIf($request->user()->is_admin),
]);

$validator = $this->validationFactory->make($request->all(), [
    'role_id' => Rule::requiredIf(function () use ($request) {
        return $request->user()->is_admin;
    }),
]);
```

##### required_unless:anotherfield,value,…

除非 `anotherfield` 字段等於 `value`，否則驗證字段不能空。

##### required_with:foo,bar,…

驗證字段只有在任一其它指定字段存在的情況才是必須的。

##### required_with_all:foo,bar,…

驗證字段只有在所有指定字段存在的情況下才是必須的。

##### required_without:foo,bar,…

驗證字段只有當任一指定字段不存在的情況下才是必須的。

##### required_without_all:foo,bar,…

驗證字段只有當所有指定字段不存在的情況下才是必須的。

##### same:field

給定字段和驗證字段必須匹配。

##### size:value

驗證字段必須有和給定值 `value` 相匹配的尺寸/大小，對字符串而言，`value` 是相應的字符數目；對數值而言，`value` 是給定整型值；對數組而言，`value` 是數組長度；對文件而言，`value` 是相應的文件千字節數（KB）。

##### starts_with:foo,bar,...

驗證字段必須以某個給定值開頭。

##### string

驗證字段必須是字符串，如果允許字段為空，需要分配 `nullable` 規則到該字段。

##### timezone

驗證字符必須是基於 `PHP` 函數 `timezone_identifiers_list` 的有效時區標識

##### unique:table,column,except,idColumn

驗證字段在給定數據表上必須是唯一的，如果不指定 `column` 選項，字段名將作為默認 `column`。

1. 指定自定義列名：

```php
'email' => 'unique:users,email_address'
```

2. 自定義數據庫連接：
   有時候，你可能需要自定義驗證器生成的數據庫連接，正如上面所看到的，設置 `unique:users` 作為驗證規則將會使用默認數據庫連接來查詢數據庫。要覆蓋默認連接，在數據表名後使用“.”指定連接：

```php
'email' => 'unique:connection.users,email_address'
```

3. 強制一個忽略給定 `ID` 的唯一規則：
   有時候，你可能希望在唯一檢查時忽略給定 `ID`，例如，考慮一個包含用户名、郵箱地址和位置的”更新屬性“界面，你將要驗證郵箱地址是唯一的，然而，如果用户只改變用户名字段而並沒有改變郵箱字段，你不想要因為用户已經擁有該郵箱地址而拋出驗證錯誤，你只想要在用户提供的郵箱已經被別人使用的情況下才拋出驗證錯誤。

要告訴驗證器忽略用户 `ID`，可以使用 `Rule` 類來定義這個規則，我們還要以數組方式指定驗證規則，而不是使用 `|` 來界定規則：

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'email' => [
        'required',
        Rule::unique('users')->ignore($user->id),
    ],
]);
```

除了傳遞模型實例主鍵值到 `ignore` 方法之外，你還可以傳遞整個模型實例。組件會自動從模型實例中解析出主鍵值：

```php
Rule::unique('users')->ignore($user)
```

如果你的數據表使用主鍵字段不是 `id`，可以在調用 `ignore` 方法的時候指定字段名稱：

```php
'email' => Rule::unique('users')->ignore($user->id, 'user_id')
```

默認情況下，`unique` 規則會檢查與要驗證的屬性名匹配的列的唯一性。不過，你可以指定不同的列名作為 `unique` 方法的第二個參數：

```php
Rule::unique('users', 'email_address')->ignore($user->id),
```

4. 添加額外的 `where` 子句：

使用 `where` 方法自定義查詢的時候還可以指定額外查詢約束，例如，下面我們來添加一個驗證 `account_id` 為 1 的約束：

```php
'email' => Rule::unique('users')->where(function ($query) {
    $query->where('account_id', 1);
})
```

##### url

驗證字段必須是有效的 URL。

##### uuid

該驗證字段必須是有效的 RFC 4122（版本 1、3、4 或 5）全局唯一標識符（UUID）。

##### sometimes

添加條件規則
存在時驗證

在某些場景下，你可能想要只有某個字段存在的情況下進行驗證檢查，要快速實現這個，添加 `sometimes` 規則到規則列表：

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'sometimes|required|email',
]);
```

在上例中，`email` 字段只有存在於 `$data` 數組時才會被驗證。

注：如果你嘗試驗證一個總是存在但可能為空的字段時，參考可選字段注意事項。

複雜條件驗證

有時候你可能想要基於更復雜的條件邏輯添加驗證規則。例如，你可能想要只有在另一個字段值大於 100 時才要求一個給定字段是必須的，或者，你可能需要只有當另一個字段存在時兩個字段才都有給定值。添加這個驗證規則並不是一件頭疼的事。首先，創建一個永遠不會改變的靜態規則到 `Validator` 實例：

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'required|email',
    'games' => 'required|numeric',
]);
```

讓我們假定我們的 Web 應用服務於遊戲收藏者。如果一個遊戲收藏者註冊了我們的應用並擁有超過 100 個遊戲，我們想要他們解釋為什麼他們會有這麼多遊戲，例如，也許他們在運營一個遊戲二手店，又或者他們只是喜歡收藏。要添加這種條件，我們可以使用 `Validator` 實例上的 `sometimes` 方法：

```php
$v->sometimes('reason', 'required|max:500', function($input) {
    return $input->games >= 100;
});
```

傳遞給 `sometimes` 方法的第一個參數是我們需要有條件驗證的名稱字段，第二個參數是我們想要添加的規則，如果作為第三個參數的閉包返回 `true`，規則被添加。該方法讓構建複雜條件驗證變得簡單，你甚至可以一次為多個字段添加條件驗證：

```php
$v->sometimes(['reason', 'cost'], 'required', function($input) {
    return $input->games >= 100;
});
```

注：傳遞給閉包的 `$input` 參數是 `Hyperf\Support\Fluent` 的一個實例，可用於訪問輸入和文件。

### 驗證數組輸入

驗證表單數組輸入字段不再是件痛苦的事情，例如，如果進入的 HTTP 請求包含 `photos[profile]` 字段，可以這麼驗證：

```php
$validator = $this->validationFactory->make($request->all(), [
    'photos.profile' => 'required|image',
]);
```

我們還可以驗證數組的每個元素，例如，要驗證給定數組輸入中每個 email 是否是唯一的，可以這麼做（這種針對提交的數組字段是二維數組，如 `person[][email]` 或 `person[test][email]`）：

```php
$validator = $this->validationFactory->make($request->all(), [
    'person.*.email' => 'email|unique:users',
    'person.*.first_name' => 'required_with:person.*.last_name',
]);
```

類似地，在語言文件中你也可以使用 `*` 字符指定驗證消息，從而可以使用單個驗證消息定義基於數組字段的驗證規則：

```php
'custom' => [
    'person.*.email' => [
        'unique' => '每個人的郵箱地址必須是唯一的',
    ]
],
```

### 自定義驗證規則

#### 註冊自定義驗證規則

`Validation`  組件使用事件機制實現自定義驗證規則，我們定義了 `ValidatorFactoryResolved` 事件，您需要做的就是定義一個 `ValidatorFactoryResolved` 的監聽器並且在監聽器中實現驗證器的註冊，示例如下。

```php
namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\Event\ValidatorFactoryResolved;
use Hyperf\Validation\Validator;

#[Listener]
class ValidatorFactoryResolvedListener implements ListenerInterface
{

    public function listen(): array
    {
        return [
            ValidatorFactoryResolved::class,
        ];
    }

    public function process(object $event): void
    {
        /**  @var ValidatorFactoryInterface $validatorFactory */
        $validatorFactory = $event->validatorFactory;
        // 註冊了 foo 驗證器
        $validatorFactory->extend('foo', function (string $attribute, mixed $value, array $parameters, Validator $validator): bool {
            return $value == 'foo';
        });
        // 當創建一個自定義驗證規則時，你可能有時候需要為錯誤信息定義自定義佔位符這裏擴展了 :foo 佔位符
        $validatorFactory->replacer('foo', function (string $message, string $attribute, string $rule, array $parameters): array|string {
            return str_replace(':foo', $attribute, $message);
        });
    }
}
```

#### 自定義錯誤信息

你還需要為自定義規則定義錯誤信息。你可以使用內聯自定義消息數組或者在驗證語言文件中添加條目來實現這一功能。消息應該被放到數組的第一維，而不是在只用於存放屬性指定錯誤信息的 custom 數組內，以上一節的 `foo` 自定義驗證器為例:

`storage/languages/en/validation.php` 添加下面的內容到文件的數組中

```php
    'foo' => 'The :attribute must be foo',
```

`storage/languages/zh_CN/validation.php` 添加下面的內容到文件的數組中

```php    
    'foo' => ' :attribute 必須是 foo',
```

#### 自定義驗證器使用

```php
<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class DemoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // 使用 foo 驗證器
            'name' => 'foo'
        ];
    }
}
```
