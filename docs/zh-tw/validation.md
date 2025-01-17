# 驗證器

## 前言

> [hyperf/validation](https://github.com/hyperf/validation) 衍生於 [illuminate/validation](https://github.com/illuminate/validation)，我們對它進行了一些改造，但保持了驗證規則的相同。在這裡感謝一下 Laravel 開發組，實現瞭如此強大好用的驗證器元件。

## 安裝

### 引入元件包

```bash
composer require hyperf/validation
```

### 新增中介軟體

您需要為使用到驗證器元件的 Server 在 `config/autoload/middlewares.php` 配置檔案加上一個全域性中介軟體 `Hyperf\Validation\Middleware\ValidationMiddleware` 的配置，如下為 `http` Server 加上對應的全域性中介軟體的示例：

```php
<?php
return [
    // 下面的 http 字串對應 config/autoload/server.php 內每個 server 的 name 屬性對應的值，意味著對應的中介軟體配置僅應用在該 Server 中
    'http' => [
        // 陣列內配置您的全域性中介軟體，順序根據該陣列的順序
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
        // 這裡隱藏了其它中介軟體
    ],
];
```

> 如沒有正確設定全域性中介軟體，可能會導致 `表單請求(FormRequest)` 的使用方式無效。

### 新增異常處理器

異常處理器主要對 `Hyperf\Validation\ValidationException` 異常進行處理，我們提供了一個 `Hyperf\Validation\ValidationExceptionHandler` 來進行處理，您需要手動將這個異常處理器配置到您的專案的 `config/autoload/exceptions.php` 檔案內，當然，您也可以自定義您的異常處理器。

```php
<?php
return [
    'handler' => [
        // 這裡對應您當前的 Server 名稱
        'http' => [
            \Hyperf\Validation\ValidationExceptionHandler::class,
        ],
    ],
];
```

### 釋出驗證器語言檔案

由於存在多語言的功能，故該元件依賴 [hyperf/translation](https://github.com/hyperf/translation) 元件，如您未曾新增過 Translation 元件的配置檔案，請先執行下面的命令來發布 Translation 元件的配置檔案，如您已經發布過或手動新增過，只需釋出驗證器元件的語言檔案即可：

釋出 Translation 元件的檔案：

```bash
php bin/hyperf.php vendor:publish hyperf/translation
```

釋出驗證器元件的檔案：

```bash
php bin/hyperf.php vendor:publish hyperf/validation
```

執行上面的命令會將驗證器的語言檔案 `validation.php` 釋出到對應的語言檔案目錄，`en` 指英文語言檔案，`zh_CN` 指中文簡體的語言檔案，您可以按照實際需要對 `validation.php` 檔案內容進行修改和自定義。

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

對於複雜的驗證場景，您可以建立一個 `表單請求(FormRequest)`，表單請求是包含驗證邏輯的一個自定義請求類，您可以透過執行下面的命令建立一個名為 `FooRequest` 的表單驗證類：

```bash
php bin/hyperf.php gen:request FooRequest
```

表單驗證類會生成於 `app\Request` 目錄下，如果該目錄不存在，執行命令時會自動建立目錄。   
接下來我們新增一些驗證規則到該類的 `rules` 方法：

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

那麼，驗證規則要如何生效呢？您所要做的就是在控制器方法中透過型別提示宣告該請求類為引數。這樣在控制器方法被呼叫之前會驗證傳入的表單請求，這意味著你不需要在控制器中寫任何驗證邏輯並很好的解耦了這兩部分的程式碼：

```php
<?php
namespace App\Controller;

use App\Request\FooRequest;

class IndexController
{
    public function index(FooRequest $request)
    {
        // 傳入的請求透過驗證...
        
        // 獲取透過驗證的資料...
        $validated = $request->validated();
    }
}
```

如果驗證失敗，驗證器會拋一個 `Hyperf\Validation\ValidationException` 異常，您可以在透過新增一個自定義的異常處理類來處理該異常，與此同時，我們也提供了一個`Hyperf\Validation\ValidationExceptionHandler` 異常處理器來處理該異常，您也可以直接配置我們提供的異常處理器來處理。但預設提供的異常處理器不一定能夠滿足您的需求，您可以根據情況透過自定義異常處理器自定義處理驗證失敗後的行為。

#### 自定義錯誤訊息

您可以透過重寫 `messages` 方法來自定義表單請求使用的錯誤訊息，該方法應該返回屬性/規則對陣列及其對應錯誤訊息：

```php
/**
 * 獲取已定義驗證規則的錯誤訊息
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

如果您希望將驗證訊息中的 `:attribute` 部分替換為自定義的屬性名，則可以透過重寫 `attributes` 方法來指定自定義的名稱。該方法會返回屬性名及對應自定義名稱鍵值對陣列：

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

### 手動建立驗證器

如果您不想使用 `表單請求(FormRequest)` 的自動驗證功能，可以透過注入 `ValidatorFactoryInterface` 介面類來獲得驗證器工廠類，然後透過 `make` 方法手動建立一個驗證器例項：

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

傳給 `make` 方法的第一個引數是需要驗證的資料，第二個引數則是該資料的驗證規則。

#### 自定義錯誤訊息

如果有需要，你也可以使用自定義錯誤資訊代替預設值進行驗證。有幾種方法可以指定自定義資訊。首先，你可以將自定義資訊作為第三個引數傳遞給 `make` 方法：

```php
<?php
$messages = [
    'required' => 'The :attribute field is required.',
];

$validator = $this->validationFactory->make($request->all(), $rules, $messages);
```

在這個例子中， `:attribute` 佔位符會被驗證欄位的實際名稱替換。除此之外，你還可以在驗證訊息中使用其它佔位符。例如：

```php
$messages = [
    'same'    => 'The :attribute and :other must match.',
    'size'    => 'The :attribute must be exactly :size.',
    'between' => 'The :attribute value :input is not between :min - :max.',
    'in'      => 'The :attribute must be one of the following types: :values',
];
```

#### 為給定屬性指定自定義資訊

有時候你可能只想為特定的欄位自定義錯誤資訊。只需在屬性名稱後使用「點」來指定驗證的規則即可：

```php
$messages = [
    'email.required' => 'We need to know your e-mail address!',
];
```

#### 在 PHP 檔案中指定自定義資訊

在大多數情況下，您可能會在檔案中指定自定義資訊，而不是直接將它們傳遞給 `Validator` 。為此，需要把你的資訊放置於 `storage/languages/xx/validation.php` 語言檔案內的 `custom` 陣列中。

#### 在 PHP 檔案中指定自定義屬性

如果你希望將驗證資訊的 `:attribute` 部分替換為自定義屬性名稱，你可以在 `storage/languages/xx/validation.php` 語言檔案的 `attributes` 陣列中指定自定義名稱：

```php
'attributes' => [
    'email' => 'email address',
],
```

### 驗證後鉤子

驗證器還允許你新增在驗證成功之後允許的回撥函式，以便你進行下一步的驗證，甚至在訊息集合中新增更多的錯誤訊息。使用它只需在驗證例項上使用 `after` 方法：

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

## 處理錯誤訊息

透過 `Validator` 例項呼叫 `errors` 方法，會返回 `Hyperf\Support\MessageBag` 例項，它擁有各種方便的方法處理錯誤資訊。

### 檢視特定欄位的第一個錯誤資訊

要檢視特定欄位的第一個錯誤訊息，可以使用 `first` 方法：

```php
$errors = $validator->errors();

echo $errors->first('foo');
```

### 檢視特定欄位的所有錯誤訊息

如果你需要獲取指定欄位的所有錯誤資訊的陣列，則可以使用 `get` 方法：

```php
foreach ($errors->get('foo') as $message) {
    //
}
```

如果要驗證表單的陣列欄位，你可以使用 `*` 來獲取每個陣列元素的所有錯誤訊息：

```php
foreach ($errors->get('foo.*') as $message) {
    //
}
```

### 檢視所有欄位的所有錯誤訊息

如果你想要得到所有欄位的所有錯誤訊息，可以使用 `all` 方法：

```php
foreach ($errors->all() as $message) {
    //
}
```

### 判斷特定欄位是否含有錯誤訊息

`has` 方法可以被用來判斷指定欄位是否存在錯誤資訊:

```php
if ($errors->has('foo')) {
    //
}
```

### 場景

驗證器增加了場景功能，我們可以很方便的按需修改驗證規則。

> 此功能需要本元件版本大於等於 2.2.7

建立一個 `SceneRequest` 如下：

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
就會導致入參在中介軟體中直接進行驗證，故場景值無法生效，所以我們需要在方法裡從容器中獲取對應的 `SceneRequest`，進行場景切換。

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

當然，我們也可以透過 `Scene` 註解切換場景

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

    #[Scene(scene:'bar2', argument: 'request')] // 繫結到 $request
    public function bar2(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }

    #[Scene(scene:'bar3', argument: 'request')]
    #[Scene(scene:'bar3', argument: 'req')] // 支援多個引數
    public function bar3(SceneRequest $request, DebugRequest $req)
    {
        return $this->response->success($request->all());
    }

    #[Scene()] // 預設 scene 為方法名，效果等於 #[Scene(scene: 'bar1')]
    public function bar1(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }
}
```

#### 不同場景下同名引數

在兩個介面下定義相同的引數名是比較常見的，但此時不同場景的引數驗證要求可能不相同，我們需要區分定義，可以嘗試以下示例

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

下面是有效規則及其函式列表：

##### accepted

驗證欄位的值必須是 `yes`、`on`、`1` 或 `true`，這在「同意服務協議」時很有用。

##### accepted_if:anotherfield,value,…
如果另一個正在驗證的欄位等於指定的值，則驗證中的欄位必須為 `yes`、`on`、`1` 或 `true`，這對於驗證「服務條款」接受或類似欄位很有用。

##### declined
正在驗證的欄位必須是 `no`、`off`、`0` 或者 `false`。

##### declined_if:anotherfield,value,…
如果另一個驗證欄位的值等於指定值，則驗證欄位的值必須為 `no`、`off`、`0` 或 `false`。

##### active_url

驗證欄位必須是基於 `PHP` 函式 `dns_get_record` 的，有 `A` 或 `AAAA` 記錄的值。

##### after:date

驗證欄位必須是給定日期之後的一個值，日期將會透過 PHP 函式 strtotime 傳遞：

```php
'start_date' => 'required|date|after:tomorrow'
```

你可以指定另外一個與日期進行比較的欄位，而不是傳遞一個日期字串給 strtotime 執行：

```php
'finish_date' => 'required|date|after:start_date'
```

##### after_or_equal:date

驗證欄位必須是大於等於給定日期的值，更多資訊，請參考 after:date 規則。

##### alpha

驗證欄位必須是字母(包含中文)。 為了將此驗證規則限制在 ASCII 範圍內的字元（a-z 和 A-Z），你可以為驗證規則提供 ascii 選項：

```php
'username' => 'alpha:ascii',
```

##### alpha_dash

驗證欄位可以包含字母(包含中文)和數字，以及破折號和下劃線。為了將此驗證規則限制在 ASCII 範圍內的字元（a-z 和 A-Z），你可以為驗證規則提供 ascii 選項：

```php
'username' => 'alpha_dash:ascii',
```

##### alpha_num

驗證欄位必須是字母(包含中文)或數字。為了將此驗證規則限制在 ASCII 範圍內的字元（a-z 和 A-Z），你可以為驗證規則提供 ascii 選項：

```php
'username' => 'alpha_num:ascii',
```

##### ascii

正在驗證的欄位必須完全是 7 位的 ASCII 字元。

##### array

驗證欄位必須是 PHP 陣列。

##### required_array_keys:foo,bar,…

驗證的欄位必須是一個數組，並且必須至少包含指定的鍵。

##### bail

第一個驗證規則驗證失敗則停止執行其它驗證規則。

##### before:date

和 after:date 相對，驗證欄位必須是指定日期之前的一個數值，日期將會傳遞給 PHP strtotime 函式。

##### before_or_equal:date

驗證欄位必須小於等於給定日期。日期將會傳遞給 PHP 的 strtotime 函式。

##### between:min,max

驗證欄位大小在給定的最小值和最大值之間，字串、數字、陣列和檔案都可以像使用 size 規則一樣使用該規則：

'name' => 'required|between:1,20'

##### boolean

驗證欄位必須可以被轉化為布林值，接收 true, false, 1, 0, "1" 和 "0" 等輸入。

##### boolean:strict

驗證欄位必須可以被轉化為布林值，僅接收 true 和 false。

##### confirmed

驗證欄位必須有一個匹配欄位 foo_confirmation，例如，如果驗證欄位是 password，必須輸入一個與之匹配的 password_confirmation 欄位。

##### date

驗證欄位必須是一個基於 PHP strtotime 函式的有效日期

##### date_equals:date

驗證欄位必須等於給定日期，日期會被傳遞到 PHP strtotime 函式。

##### date_format:format

驗證欄位必須匹配指定格式，可以使用 PHP 函式 date 或 date_format 驗證該欄位。

##### decimal:min,max

驗證欄位必須是數值型別，並且必須包含指定的小數位數：

```php
// 必須正好有兩位小數（例如 9.99）...
'price' => 'decimal:2'

// 必須有 2 到 4 位小數...
'price' => 'decimal:2,4'
```

##### lowercase

驗證的欄位必須是小寫的。

##### uppercase

驗證欄位必須為大寫。

##### mac_address

驗證的欄位必須是一個 MAC 地址。

##### max_digits:value

驗證的整數必須具有最大長度 value。

##### min_digits:value

驗證的整數必須具有至少_value_位數。

##### exclude

`validate` 和 `validated` 方法中會排除掉當前驗證的欄位。

##### exclude_if:anotherfield,value
如果 `anotherfield` 等於 `value` ，`validate` 和 `validated` 方法中會排除掉當前驗證的欄位。

在一些複雜的場景，也可以使用 `Rule::excludeIf` 方法，這個方法需要返回一個布林值或者一個匿名函式。如果返回的是匿名函式，那麼這個函式應該返回 `true` 或 `false` 去決定被驗證的欄位是否應該被排除掉：

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

需要驗證的欄位必須不存在或為空。如果符合以下條件之一，欄位將被認為是 “空”：

1. 值為 `null`。
2. 值為空字串。
3. 值為空陣列或空的可計數物件。
4. 值為上傳檔案，但檔案路徑為空。

##### prohibited_if:anotherfield,value,…

如果 `anotherfield` 欄位等於任何 `value`，則需要驗證的欄位必須不存在或為空。如果符合以下條件之一，欄位將被認為是 “空”：

1. 值為 `null`。
2. 值為空字串。
3. 值為空陣列或空的可計數物件。
4. 值為上傳檔案，但檔案路徑為空。

如果需要複雜的條件禁止邏輯，則可以使用 `Rule::prohibitedIf` 方法。該方法接受一個布林值或一個閉包。當給定一個閉包時，閉包應返回 `true` 或 `false`，以指示是否應禁止驗證欄位：


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

驗證的欄位在輸入資料中必須不存在。

##### missing_if:anotherfield,value,…

如果 `_anotherfield_` 欄位等於任何 `_value_` ，則驗證的欄位必須不存在。

##### missing_unless:anotherfield,value

驗證的欄位必須不存在，除非 `_anotherfield_` 欄位等於任何 `_value_` 。

##### missing_with:foo,bar,…

如果任何其他指定的欄位存在，則驗證的欄位必須不存在。

##### missing_with_all:foo,bar,…

如果所有其他指定的欄位都存在，則驗證的欄位必須不存在。

##### multiple_of:value

驗證的欄位必須是 `_value_` 的倍數。

##### doesnt_start_with:foo,bar,…

驗證的欄位不能以給定值之一開頭。

##### doesnt_end_with:foo,bar,…

驗證的欄位不能以給定值之一結尾。

##### different:field

驗證欄位必須是一個和指定欄位不同的值。

##### digits:value

驗證欄位必須是數字且長度為 `value` 指定的值。

##### digits_between:min,max

驗證欄位數值長度必須介於最小值和最大值之間。

##### dimensions

驗證的圖片尺寸必須滿足該規定引數指定的約束條件：

```php
'avatar' => 'dimensions:min_width=100,min_height=200'
```

有效的約束條件包括：`min_width`, `max_width`, `min_height`, `max_height`, `width`, `height`, `ratio`。

`ratio` 約束寬度/高度的比率，這可以透過表示式 `3/2` 或浮點數 `1.5` 來表示：

```php
'avatar' => 'dimensions:ratio=3/2'
```

由於該規則要求多個引數，可以使用 `Rule::dimensions` 方法來構造該規則：

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

處理陣列時，驗證欄位不能包含重複值：

```php
'foo.*.id' => 'distinct'
```

##### email

驗證欄位必須是格式正確的電子郵件地址。

##### exists:table,column

驗證欄位必須存在於指定資料表。

基本使用：

```php
'state' => 'exists:states'
```

如果 `column` 選項沒有指定，將會使用欄位名。

指定自定義列名：

```php
'state' => 'exists:states,abbreviation'
```

有時，你可能需要為 `exists` 查詢指定要使用的資料庫連線，這可以在表名前透過`.`前置資料庫連線來實現：

```php
'email' => 'exists:connection.staff,email'
```

如果你想要自定義驗證規則執行的查詢，可以使用 `Rule` 類來定義規則。在這個例子中，我們還以陣列形式指定了驗證規則，而不是使用 `|` 字元來限定它們：

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

驗證欄位必須是上傳成功的檔案。

##### filled

驗證欄位如果存在則不能為空。

##### gt:field

驗證欄位必須大於給定 `field` 欄位，這兩個欄位型別必須一致，適用於字串、數字、陣列和檔案，和 `size` 規則類似

##### gte:field

驗證欄位必須大於等於給定 `field` 欄位，這兩個欄位型別必須一致，適用於字串、數字、陣列和檔案，和 `size` 規則類似

##### image

驗證檔案必須是圖片（`jpeg`、`png`、`bmp`、`gif` 或者 `svg`）

##### in:foo,bar…

驗證欄位值必須在給定的列表中，由於該規則經常需要我們對陣列進行 `implode`，我們可以使用 `Rule::in` 來構造這個規則：

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

驗證欄位必須在另一個欄位值中存在。

##### integer

驗證欄位必須是整型（String 和 Integer 型別都可以透過驗證）。

##### integer:strict

驗證欄位必須是整型（只有 Integer 型別都可以透過驗證）。

##### ip

驗證欄位必須是 IP 地址。

##### ipv4

驗證欄位必須是 IPv4 地址。

##### ipv6

驗證欄位必須是 IPv6 地址。

##### json

驗證欄位必須是有效的 JSON 字串

##### lt:field

驗證欄位必須小於給定 `field` 欄位，這兩個欄位型別必須一致，適用於字串、數字、陣列和檔案，和 `size` 規則類似

##### lte:field

驗證欄位必須小於等於給定 `field` 欄位，這兩個欄位型別必須一致，適用於字串、數字、陣列和檔案，和 `size` 規則類似

##### max:value

驗證欄位必須小於等於最大值，和字串、數值、陣列、檔案欄位的 `size` 規則使用方式一樣。

##### mimetypes：text/plain…

驗證檔案必須匹配給定的 `MIME` 檔案型別之一：

```php
'video' => 'mimetypes:video/avi,video/mpeg,video/quicktime'
```

為了判斷上傳檔案的 `MIME` 型別，元件將會讀取檔案內容來猜測 `MIME` 型別，這可能會和客戶端 `MIME` 型別不同。

##### mimes:foo,bar,…

驗證檔案的 `MIME` 型別必須是該規則列出的擴充套件型別中的一個
`MIME` 規則的基本使用：

```php
'photo' => 'mimes:jpeg,bmp,png'
```

儘管你只是指定了副檔名，該規則實際上驗證的是透過讀取檔案內容獲取到的檔案 `MIME` 型別。
完整的 `MIME` 型別列表及其相應的擴充套件可以在這裡找到：[mime types](http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

##### min:value

與 `max:value` 相對，驗證欄位必須大於等於最小值，對字串、數值、陣列、檔案欄位而言，和 `size` 規則使用方式一致。

##### not_in:foo,bar,…

驗證欄位值不能在給定列表中，和 `in` 規則類似，我們可以使用 `Rule::notIn` 方法來構建規則：

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

驗證欄位不能匹配給定正則表示式

注：使用 `regex/not_regex` 模式時，規則必須放在陣列中，而不能使用管道分隔符，尤其是正則表示式中包含管道符號時。

##### nullable

驗證欄位可以是 `null`，這在驗證一些可以為 `null` 的原始資料如整型或字串時很有用。

##### numeric

驗證欄位必須是數值

##### present

驗證欄位必須出現在輸入資料中但可以為空。

##### regex:pattern

驗證欄位必須匹配給定正則表示式。
該規則底層使用的是 `PHP` 的 `preg_match` 函式。因此，指定的模式需要遵循 `preg_match` 函式所要求的格式並且包含有效的分隔符。例如:

```php
 'email' => 'regex:/^.+@.+$/i'
```

注：使用 `regex/not_regex` 模式時，規則必須放在陣列中，而不能使用管道分隔符，尤其是正則表示式中包含管道符號時。

##### required

驗證欄位值不能為空，以下情況欄位值都為空：
值為`null`
值是空字串
值是空陣列或者空的 `Countable` 物件
值是上傳檔案但路徑為空

##### required_if:anotherfield,value,…

驗證欄位在 `anotherfield` 等於指定值 `value` 時必須存在且不能為空。
如果你想要為 `required_if` 規則構造更復雜的條件，可以使用 `Rule::requiredIf` 方法，該方法接收一個布林值或閉包。當傳遞一個閉包時，會返回 `true` 或 `false` 以表明驗證欄位是否是必須的：

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

除非 `anotherfield` 欄位等於 `value`，否則驗證欄位不能空。

##### required_with:foo,bar,…

驗證欄位只有在任一其它指定欄位存在的情況才是必須的。

##### required_with_all:foo,bar,…

驗證欄位只有在所有指定欄位存在的情況下才是必須的。

##### required_without:foo,bar,…

驗證欄位只有當任一指定欄位不存在的情況下才是必須的。

##### required_without_all:foo,bar,…

驗證欄位只有當所有指定欄位不存在的情況下才是必須的。

##### same:field

給定欄位和驗證欄位必須匹配。

##### size:value

驗證欄位必須有和給定值 `value` 相匹配的尺寸/大小，對字串而言，`value` 是相應的字元數目；對數值而言，`value` 是給定整型值；對陣列而言，`value` 是陣列長度；對檔案而言，`value` 是相應的檔案千位元組數（KB）。

##### starts_with:foo,bar,...

驗證欄位必須以某個給定值開頭。

##### string

驗證欄位必須是字串，如果允許欄位為空，需要分配 `nullable` 規則到該欄位。

##### timezone

驗證字元必須是基於 `PHP` 函式 `timezone_identifiers_list` 的有效時區標識

##### unique:table,column,except,idColumn

驗證欄位在給定資料表上必須是唯一的，如果不指定 `column` 選項，欄位名將作為預設 `column`。

1. 指定自定義列名：

```php
'email' => 'unique:users,email_address'
```

2. 自定義資料庫連線：
   有時候，你可能需要自定義驗證器生成的資料庫連線，正如上面所看到的，設定 `unique:users` 作為驗證規則將會使用預設資料庫連線來查詢資料庫。要覆蓋預設連線，在資料表名後使用“.”指定連線：

```php
'email' => 'unique:connection.users,email_address'
```

3. 強制一個忽略給定 `ID` 的唯一規則：
   有時候，你可能希望在唯一檢查時忽略給定 `ID`，例如，考慮一個包含使用者名稱、郵箱地址和位置的”更新屬性“介面，你將要驗證郵箱地址是唯一的，然而，如果使用者只改變使用者名稱欄位而並沒有改變郵箱欄位，你不想要因為使用者已經擁有該郵箱地址而丟擲驗證錯誤，你只想要在使用者提供的郵箱已經被別人使用的情況下才丟擲驗證錯誤。

要告訴驗證器忽略使用者 `ID`，可以使用 `Rule` 類來定義這個規則，我們還要以陣列方式指定驗證規則，而不是使用 `|` 來界定規則：

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'email' => [
        'required',
        Rule::unique('users')->ignore($user->id),
    ],
]);
```

除了傳遞模型例項主鍵值到 `ignore` 方法之外，你還可以傳遞整個模型例項。元件會自動從模型例項中解析出主鍵值：

```php
Rule::unique('users')->ignore($user)
```

如果你的資料表使用主鍵欄位不是 `id`，可以在呼叫 `ignore` 方法的時候指定欄位名稱：

```php
'email' => Rule::unique('users')->ignore($user->id, 'user_id')
```

預設情況下，`unique` 規則會檢查與要驗證的屬性名匹配的列的唯一性。不過，你可以指定不同的列名作為 `unique` 方法的第二個引數：

```php
Rule::unique('users', 'email_address')->ignore($user->id),
```

4. 新增額外的 `where` 子句：

使用 `where` 方法自定義查詢的時候還可以指定額外查詢約束，例如，下面我們來新增一個驗證 `account_id` 為 1 的約束：

```php
'email' => Rule::unique('users')->where(function ($query) {
    $query->where('account_id', 1);
})
```

##### url

驗證欄位必須是有效的 URL。

##### uuid

該驗證欄位必須是有效的 RFC 4122（版本 1、3、4 或 5）全域性唯一識別符號（UUID）。

##### sometimes

新增條件規則
存在時驗證

在某些場景下，你可能想要只有某個欄位存在的情況下進行驗證檢查，要快速實現這個，新增 `sometimes` 規則到規則列表：

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'sometimes|required|email',
]);
```

在上例中，`email` 欄位只有存在於 `$data` 陣列時才會被驗證。

注：如果你嘗試驗證一個總是存在但可能為空的欄位時，參考可選欄位注意事項。

複雜條件驗證

有時候你可能想要基於更復雜的條件邏輯新增驗證規則。例如，你可能想要只有在另一個欄位值大於 100 時才要求一個給定欄位是必須的，或者，你可能需要只有當另一個欄位存在時兩個欄位才都有給定值。新增這個驗證規則並不是一件頭疼的事。首先，建立一個永遠不會改變的靜態規則到 `Validator` 例項：

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'required|email',
    'games' => 'required|numeric',
]);
```

讓我們假定我們的 Web 應用服務於遊戲收藏者。如果一個遊戲收藏者註冊了我們的應用並擁有超過 100 個遊戲，我們想要他們解釋為什麼他們會有這麼多遊戲，例如，也許他們在運營一個遊戲二手店，又或者他們只是喜歡收藏。要新增這種條件，我們可以使用 `Validator` 例項上的 `sometimes` 方法：

```php
$v->sometimes('reason', 'required|max:500', function($input) {
    return $input->games >= 100;
});
```

傳遞給 `sometimes` 方法的第一個引數是我們需要有條件驗證的名稱欄位，第二個引數是我們想要新增的規則，如果作為第三個引數的閉包返回 `true`，規則被新增。該方法讓構建複雜條件驗證變得簡單，你甚至可以一次為多個欄位新增條件驗證：

```php
$v->sometimes(['reason', 'cost'], 'required', function($input) {
    return $input->games >= 100;
});
```

注：傳遞給閉包的 `$input` 引數是 `Hyperf\Support\Fluent` 的一個例項，可用於訪問輸入和檔案。

### 驗證陣列輸入

驗證表單陣列輸入欄位不再是件痛苦的事情，例如，如果進入的 HTTP 請求包含 `photos[profile]` 欄位，可以這麼驗證：

```php
$validator = $this->validationFactory->make($request->all(), [
    'photos.profile' => 'required|image',
]);
```

我們還可以驗證陣列的每個元素，例如，要驗證給定陣列輸入中每個 email 是否是唯一的，可以這麼做（這種針對提交的陣列欄位是二維陣列，如 `person[][email]` 或 `person[test][email]`）：

```php
$validator = $this->validationFactory->make($request->all(), [
    'person.*.email' => 'email|unique:users',
    'person.*.first_name' => 'required_with:person.*.last_name',
]);
```

類似地，在語言檔案中你也可以使用 `*` 字元指定驗證訊息，從而可以使用單個驗證訊息定義基於陣列欄位的驗證規則：

```php
'custom' => [
    'person.*.email' => [
        'unique' => '每個人的郵箱地址必須是唯一的',
    ]
],
```

### 自定義驗證規則

#### 註冊自定義驗證規則

`Validation`  元件使用事件機制實現自定義驗證規則，我們定義了 `ValidatorFactoryResolved` 事件，您需要做的就是定義一個 `ValidatorFactoryResolved` 的監聽器並且在監聽器中實現驗證器的註冊，示例如下。

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
        // 當建立一個自定義驗證規則時，你可能有時候需要為錯誤資訊定義自定義佔位符這裡擴充套件了 :foo 佔位符
        $validatorFactory->replacer('foo', function (string $message, string $attribute, string $rule, array $parameters): array|string {
            return str_replace(':foo', $attribute, $message);
        });
    }
}
```

#### 自定義錯誤資訊

你還需要為自定義規則定義錯誤資訊。你可以使用內聯自定義訊息陣列或者在驗證語言檔案中新增條目來實現這一功能。訊息應該被放到陣列的第一維，而不是在只用於存放屬性指定錯誤資訊的 custom 陣列內，以上一節的 `foo` 自定義驗證器為例:

`storage/languages/en/validation.php` 新增下面的內容到檔案的陣列中

```php
    'foo' => 'The :attribute must be foo',
```

`storage/languages/zh_CN/validation.php` 新增下面的內容到檔案的陣列中

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
