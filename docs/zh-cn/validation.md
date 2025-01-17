# 验证器

## 前言

> [hyperf/validation](https://github.com/hyperf/validation) 衍生于 [illuminate/validation](https://github.com/illuminate/validation)，我们对它进行了一些改造，但保持了验证规则的相同。在这里感谢一下 Laravel 开发组，实现了如此强大好用的验证器组件。

## 安装

### 引入组件包

```bash
composer require hyperf/validation
```

### 添加中间件

您需要为使用到验证器组件的 Server 在 `config/autoload/middlewares.php` 配置文件加上一个全局中间件 `Hyperf\Validation\Middleware\ValidationMiddleware` 的配置，如下为 `http` Server 加上对应的全局中间件的示例：

```php
<?php
return [
    // 下面的 http 字符串对应 config/autoload/server.php 内每个 server 的 name 属性对应的值，意味着对应的中间件配置仅应用在该 Server 中
    'http' => [
        // 数组内配置您的全局中间件，顺序根据该数组的顺序
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
        // 这里隐藏了其它中间件
    ],
];
```

> 如没有正确设置全局中间件，可能会导致 `表单请求(FormRequest)` 的使用方式无效。

### 添加异常处理器

异常处理器主要对 `Hyperf\Validation\ValidationException` 异常进行处理，我们提供了一个 `Hyperf\Validation\ValidationExceptionHandler` 来进行处理，您需要手动将这个异常处理器配置到您的项目的 `config/autoload/exceptions.php` 文件内，当然，您也可以自定义您的异常处理器。

```php
<?php
return [
    'handler' => [
        // 这里对应您当前的 Server 名称
        'http' => [
            \Hyperf\Validation\ValidationExceptionHandler::class,
        ],
    ],
];
```

### 发布验证器语言文件

由于存在多语言的功能，故该组件依赖 [hyperf/translation](https://github.com/hyperf/translation) 组件，如您未曾添加过 Translation 组件的配置文件，请先执行下面的命令来发布 Translation 组件的配置文件，如您已经发布过或手动添加过，只需发布验证器组件的语言文件即可：

发布 Translation 组件的文件：

```bash
php bin/hyperf.php vendor:publish hyperf/translation
```

发布验证器组件的文件：

```bash
php bin/hyperf.php vendor:publish hyperf/validation
```

执行上面的命令会将验证器的语言文件 `validation.php` 发布到对应的语言文件目录，`en` 指英文语言文件，`zh_CN` 指中文简体的语言文件，您可以按照实际需要对 `validation.php` 文件内容进行修改和自定义。

```shell
/storage
    /languages
        /en
            validation.php
        /zh_CN
            validation.php

```

## 使用

### 表单请求验证

对于复杂的验证场景，您可以创建一个 `表单请求(FormRequest)`，表单请求是包含验证逻辑的一个自定义请求类，您可以通过执行下面的命令创建一个名为 `FooRequest` 的表单验证类：

```bash
php bin/hyperf.php gen:request FooRequest
```

表单验证类会生成于 `app\Request` 目录下，如果该目录不存在，运行命令时会自动创建目录。   
接下来我们添加一些验证规则到该类的 `rules` 方法：

```php
/**
 * 获取应用到请求的验证规则
 */
public function rules(): array
{
    return [
        'foo' => 'required|max:255',
        'bar' => 'required',
    ];
}
```

那么，验证规则要如何生效呢？您所要做的就是在控制器方法中通过类型提示声明该请求类为参数。这样在控制器方法被调用之前会验证传入的表单请求，这意味着你不需要在控制器中写任何验证逻辑并很好的解耦了这两部分的代码：

```php
<?php
namespace App\Controller;

use App\Request\FooRequest;

class IndexController
{
    public function index(FooRequest $request)
    {
        // 传入的请求通过验证...
        
        // 获取通过验证的数据...
        $validated = $request->validated();
    }
}
```

如果验证失败，验证器会抛一个 `Hyperf\Validation\ValidationException` 异常，您可以在通过添加一个自定义的异常处理类来处理该异常，与此同时，我们也提供了一个`Hyperf\Validation\ValidationExceptionHandler` 异常处理器来处理该异常，您也可以直接配置我们提供的异常处理器来处理。但默认提供的异常处理器不一定能够满足您的需求，您可以根据情况通过自定义异常处理器自定义处理验证失败后的行为。

#### 自定义错误消息

您可以通过重写 `messages` 方法来自定义表单请求使用的错误消息，该方法应该返回属性/规则对数组及其对应错误消息：

```php
/**
 * 获取已定义验证规则的错误消息
 */
public function messages(): array
{
    return [
        'foo.required' => 'foo is required',
        'bar.required'  => 'bar is required',
    ];
}
```

#### 自定义验证属性

如果您希望将验证消息中的 `:attribute` 部分替换为自定义的属性名，则可以通过重写 `attributes` 方法来指定自定义的名称。该方法会返回属性名及对应自定义名称键值对数组：

```php
/**
 * 获取验证错误的自定义属性
 */
public function attributes(): array
{
    return [
        'foo' => 'foo of request',
    ];
}
```

### 手动创建验证器

如果您不想使用 `表单请求(FormRequest)` 的自动验证功能，可以通过注入 `ValidatorFactoryInterface` 接口类来获得验证器工厂类，然后通过 `make` 方法手动创建一个验证器实例：

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

传给 `make` 方法的第一个参数是需要验证的数据，第二个参数则是该数据的验证规则。

#### 自定义错误消息

如果有需要，你也可以使用自定义错误信息代替默认值进行验证。有几种方法可以指定自定义信息。首先，你可以将自定义信息作为第三个参数传递给 `make` 方法：

```php
<?php
$messages = [
    'required' => 'The :attribute field is required.',
];

$validator = $this->validationFactory->make($request->all(), $rules, $messages);
```

在这个例子中， `:attribute` 占位符会被验证字段的实际名称替换。除此之外，你还可以在验证消息中使用其它占位符。例如：

```php
$messages = [
    'same'    => 'The :attribute and :other must match.',
    'size'    => 'The :attribute must be exactly :size.',
    'between' => 'The :attribute value :input is not between :min - :max.',
    'in'      => 'The :attribute must be one of the following types: :values',
];
```

#### 为给定属性指定自定义信息

有时候你可能只想为特定的字段自定义错误信息。只需在属性名称后使用「点」来指定验证的规则即可：

```php
$messages = [
    'email.required' => 'We need to know your e-mail address!',
];
```

#### 在 PHP 文件中指定自定义信息

在大多数情况下，您可能会在文件中指定自定义信息，而不是直接将它们传递给 `Validator` 。为此，需要把你的信息放置于 `storage/languages/xx/validation.php` 语言文件内的 `custom` 数组中。

#### 在 PHP 文件中指定自定义属性

如果你希望将验证信息的 `:attribute` 部分替换为自定义属性名称，你可以在 `storage/languages/xx/validation.php` 语言文件的 `attributes` 数组中指定自定义名称：

```php
'attributes' => [
    'email' => 'email address',
],
```

### 验证后钩子

验证器还允许你添加在验证成功之后允许的回调函数，以便你进行下一步的验证，甚至在消息集合中添加更多的错误消息。使用它只需在验证实例上使用 `after` 方法：

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

## 处理错误消息

通过 `Validator` 实例调用 `errors` 方法，会返回 `Hyperf\Support\MessageBag` 实例，它拥有各种方便的方法处理错误信息。

### 查看特定字段的第一个错误信息

要查看特定字段的第一个错误消息，可以使用 `first` 方法：

```php
$errors = $validator->errors();

echo $errors->first('foo');
```

### 查看特定字段的所有错误消息

如果你需要获取指定字段的所有错误信息的数组，则可以使用 `get` 方法：

```php
foreach ($errors->get('foo') as $message) {
    //
}
```

如果要验证表单的数组字段，你可以使用 `*` 来获取每个数组元素的所有错误消息：

```php
foreach ($errors->get('foo.*') as $message) {
    //
}
```

### 查看所有字段的所有错误消息

如果你想要得到所有字段的所有错误消息，可以使用 `all` 方法：

```php
foreach ($errors->all() as $message) {
    //
}
```

### 判断特定字段是否含有错误消息

`has` 方法可以被用来判断指定字段是否存在错误信息:

```php
if ($errors->has('foo')) {
    //
}
```

### 场景

验证器增加了场景功能，我们可以很方便的按需修改验证规则。

> 此功能需要本组件版本大于等于 2.2.7

创建一个 `SceneRequest` 如下：

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

当我们正常使用时，会使用所有的验证规则，即 `username` 和 `gender` 都是必填的。

我们可以设定场景，让此次请求只验证 `username` 必填。

如果我们配置了 `Hyperf\Validation\Middleware\ValidationMiddleware`，且将 `SceneRequest` 注入到方法上，
就会导致入参在中间件中直接进行验证，故场景值无法生效，所以我们需要在方法里从容器中获取对应的 `SceneRequest`，进行场景切换。

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

当然，我们也可以通过 `Scene` 注解切换场景

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

    #[Scene(scene:'bar2', argument: 'request')] // 绑定到 $request
    public function bar2(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }

    #[Scene(scene:'bar3', argument: 'request')]
    #[Scene(scene:'bar3', argument: 'req')] // 支持多个参数
    public function bar3(SceneRequest $request, DebugRequest $req)
    {
        return $this->response->success($request->all());
    }

    #[Scene()] // 默认 scene 为方法名，效果等于 #[Scene(scene: 'bar1')]
    public function bar1(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }
}
```

#### 不同场景下同名参数

在两个接口下定义相同的参数名是比较常见的，但此时不同场景的参数验证要求可能不相同，我们需要区分定义，可以尝试以下示例

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

## 验证规则

下面是有效规则及其函数列表：

##### accepted

验证字段的值必须是 `yes`、`on`、`1` 或 `true`，这在「同意服务协议」时很有用。

##### accepted_if:anotherfield,value,…
如果另一个正在验证的字段等于指定的值，则验证中的字段必须为 `yes`、`on`、`1` 或 `true`，这对于验证「服务条款」接受或类似字段很有用。

##### declined
正在验证的字段必须是 `no`、`off`、`0` 或者 `false`。

##### declined_if:anotherfield,value,…
如果另一个验证字段的值等于指定值，则验证字段的值必须为 `no`、`off`、`0` 或 `false`。

##### active_url

验证字段必须是基于 `PHP` 函数 `dns_get_record` 的，有 `A` 或 `AAAA` 记录的值。

##### after:date

验证字段必须是给定日期之后的一个值，日期将会通过 PHP 函数 strtotime 传递：

```php
'start_date' => 'required|date|after:tomorrow'
```

你可以指定另外一个与日期进行比较的字段，而不是传递一个日期字符串给 strtotime 执行：

```php
'finish_date' => 'required|date|after:start_date'
```

##### after_or_equal:date

验证字段必须是大于等于给定日期的值，更多信息，请参考 after:date 规则。

##### alpha

验证字段必须是字母(包含中文)。 为了将此验证规则限制在 ASCII 范围内的字符（a-z 和 A-Z），你可以为验证规则提供 ascii 选项：

```php
'username' => 'alpha:ascii',
```

##### alpha_dash

验证字段可以包含字母(包含中文)和数字，以及破折号和下划线。为了将此验证规则限制在 ASCII 范围内的字符（a-z 和 A-Z），你可以为验证规则提供 ascii 选项：

```php
'username' => 'alpha_dash:ascii',
```

##### alpha_num

验证字段必须是字母(包含中文)或数字。为了将此验证规则限制在 ASCII 范围内的字符（a-z 和 A-Z），你可以为验证规则提供 ascii 选项：

```php
'username' => 'alpha_num:ascii',
```

##### ascii

正在验证的字段必须完全是 7 位的 ASCII 字符。

##### array

验证字段必须是 PHP 数组。

##### required_array_keys:foo,bar,…

验证的字段必须是一个数组，并且必须至少包含指定的键。

##### bail

第一个验证规则验证失败则停止运行其它验证规则。

##### before:date

和 after:date 相对，验证字段必须是指定日期之前的一个数值，日期将会传递给 PHP strtotime 函数。

##### before_or_equal:date

验证字段必须小于等于给定日期。日期将会传递给 PHP 的 strtotime 函数。

##### between:min,max

验证字段大小在给定的最小值和最大值之间，字符串、数字、数组和文件都可以像使用 size 规则一样使用该规则：

'name' => 'required|between:1,20'

##### boolean

验证字段必须可以被转化为布尔值，接收 true, false, 1, 0, "1" 和 "0" 等输入。

##### boolean:strict

验证字段必须可以被转化为布尔值，仅接收 true 和 false。

##### confirmed

验证字段必须有一个匹配字段 foo_confirmation，例如，如果验证字段是 password，必须输入一个与之匹配的 password_confirmation 字段。

##### date

验证字段必须是一个基于 PHP strtotime 函数的有效日期

##### date_equals:date

验证字段必须等于给定日期，日期会被传递到 PHP strtotime 函数。

##### date_format:format

验证字段必须匹配指定格式，可以使用 PHP 函数 date 或 date_format 验证该字段。

##### decimal:min,max

验证字段必须是数值类型，并且必须包含指定的小数位数：

```php
// 必须正好有两位小数（例如 9.99）...
'price' => 'decimal:2'

// 必须有 2 到 4 位小数...
'price' => 'decimal:2,4'
```

##### lowercase

验证的字段必须是小写的。

##### uppercase

验证字段必须为大写。

##### mac_address

验证的字段必须是一个 MAC 地址。

##### max_digits:value

验证的整数必须具有最大长度 value。

##### min_digits:value

验证的整数必须具有至少_value_位数。

##### exclude

`validate` 和 `validated` 方法中会排除掉当前验证的字段。

##### exclude_if:anotherfield,value
如果 `anotherfield` 等于 `value` ，`validate` 和 `validated` 方法中会排除掉当前验证的字段。

在一些复杂的场景，也可以使用 `Rule::excludeIf` 方法，这个方法需要返回一个布尔值或者一个匿名函数。如果返回的是匿名函数，那么这个函数应该返回 `true` 或 `false` 去决定被验证的字段是否应该被排除掉：

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

需要验证的字段必须不存在或为空。如果符合以下条件之一，字段将被认为是 “空”：

1. 值为 `null`。
2. 值为空字符串。
3. 值为空数组或空的可计数对象。
4. 值为上传文件，但文件路径为空。

##### prohibited_if:anotherfield,value,…

如果 `anotherfield` 字段等于任何 `value`，则需要验证的字段必须不存在或为空。如果符合以下条件之一，字段将被认为是 “空”：

1. 值为 `null`。
2. 值为空字符串。
3. 值为空数组或空的可计数对象。
4. 值为上传文件，但文件路径为空。

如果需要复杂的条件禁止逻辑，则可以使用 `Rule::prohibitedIf` 方法。该方法接受一个布尔值或一个闭包。当给定一个闭包时，闭包应返回 `true` 或 `false`，以指示是否应禁止验证字段：


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

验证的字段在输入数据中必须不存在。

##### missing_if:anotherfield,value,…

如果 `_anotherfield_` 字段等于任何 `_value_` ，则验证的字段必须不存在。

##### missing_unless:anotherfield,value

验证的字段必须不存在，除非 `_anotherfield_` 字段等于任何 `_value_` 。

##### missing_with:foo,bar,…

如果任何其他指定的字段存在，则验证的字段必须不存在。

##### missing_with_all:foo,bar,…

如果所有其他指定的字段都存在，则验证的字段必须不存在。

##### multiple_of:value

验证的字段必须是 `_value_` 的倍数。

##### doesnt_start_with:foo,bar,…

验证的字段不能以给定值之一开头。

##### doesnt_end_with:foo,bar,…

验证的字段不能以给定值之一结尾。

##### different:field

验证字段必须是一个和指定字段不同的值。

##### digits:value

验证字段必须是数字且长度为 `value` 指定的值。

##### digits_between:min,max

验证字段数值长度必须介于最小值和最大值之间。

##### dimensions

验证的图片尺寸必须满足该规定参数指定的约束条件：

```php
'avatar' => 'dimensions:min_width=100,min_height=200'
```

有效的约束条件包括：`min_width`, `max_width`, `min_height`, `max_height`, `width`, `height`, `ratio`。

`ratio` 约束宽度/高度的比率，这可以通过表达式 `3/2` 或浮点数 `1.5` 来表示：

```php
'avatar' => 'dimensions:ratio=3/2'
```

由于该规则要求多个参数，可以使用 `Rule::dimensions` 方法来构造该规则：

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

处理数组时，验证字段不能包含重复值：

```php
'foo.*.id' => 'distinct'
```

##### email

验证字段必须是格式正确的电子邮件地址。

##### exists:table,column

验证字段必须存在于指定数据表。

基本使用：

```php
'state' => 'exists:states'
```

如果 `column` 选项没有指定，将会使用字段名。

指定自定义列名：

```php
'state' => 'exists:states,abbreviation'
```

有时，你可能需要为 `exists` 查询指定要使用的数据库连接，这可以在表名前通过`.`前置数据库连接来实现：

```php
'email' => 'exists:connection.staff,email'
```

如果你想要自定义验证规则执行的查询，可以使用 `Rule` 类来定义规则。在这个例子中，我们还以数组形式指定了验证规则，而不是使用 `|` 字符来限定它们：

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

验证字段必须是上传成功的文件。

##### filled

验证字段如果存在则不能为空。

##### gt:field

验证字段必须大于给定 `field` 字段，这两个字段类型必须一致，适用于字符串、数字、数组和文件，和 `size` 规则类似

##### gte:field

验证字段必须大于等于给定 `field` 字段，这两个字段类型必须一致，适用于字符串、数字、数组和文件，和 `size` 规则类似

##### image

验证文件必须是图片（`jpeg`、`png`、`bmp`、`gif` 或者 `svg`）

##### in:foo,bar…

验证字段值必须在给定的列表中，由于该规则经常需要我们对数组进行 `implode`，我们可以使用 `Rule::in` 来构造这个规则：

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

验证字段必须在另一个字段值中存在。

##### integer

验证字段必须是整型（String 和 Integer 类型都可以通过验证）。

##### integer:strict

验证字段必须是整型（只有 Integer 类型都可以通过验证）。

##### ip

验证字段必须是 IP 地址。

##### ipv4

验证字段必须是 IPv4 地址。

##### ipv6

验证字段必须是 IPv6 地址。

##### json

验证字段必须是有效的 JSON 字符串

##### lt:field

验证字段必须小于给定 `field` 字段，这两个字段类型必须一致，适用于字符串、数字、数组和文件，和 `size` 规则类似

##### lte:field

验证字段必须小于等于给定 `field` 字段，这两个字段类型必须一致，适用于字符串、数字、数组和文件，和 `size` 规则类似

##### max:value

验证字段必须小于等于最大值，和字符串、数值、数组、文件字段的 `size` 规则使用方式一样。

##### mimetypes：text/plain…

验证文件必须匹配给定的 `MIME` 文件类型之一：

```php
'video' => 'mimetypes:video/avi,video/mpeg,video/quicktime'
```

为了判断上传文件的 `MIME` 类型，组件将会读取文件内容来猜测 `MIME` 类型，这可能会和客户端 `MIME` 类型不同。

##### mimes:foo,bar,…

验证文件的 `MIME` 类型必须是该规则列出的扩展类型中的一个
`MIME` 规则的基本使用：

```php
'photo' => 'mimes:jpeg,bmp,png'
```

尽管你只是指定了扩展名，该规则实际上验证的是通过读取文件内容获取到的文件 `MIME` 类型。
完整的 `MIME` 类型列表及其相应的扩展可以在这里找到：[mime types](http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

##### min:value

与 `max:value` 相对，验证字段必须大于等于最小值，对字符串、数值、数组、文件字段而言，和 `size` 规则使用方式一致。

##### not_in:foo,bar,…

验证字段值不能在给定列表中，和 `in` 规则类似，我们可以使用 `Rule::notIn` 方法来构建规则：

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

验证字段不能匹配给定正则表达式

注：使用 `regex/not_regex` 模式时，规则必须放在数组中，而不能使用管道分隔符，尤其是正则表达式中包含管道符号时。

##### nullable

验证字段可以是 `null`，这在验证一些可以为 `null` 的原始数据如整型或字符串时很有用。

##### numeric

验证字段必须是数值

##### present

验证字段必须出现在输入数据中但可以为空。

##### regex:pattern

验证字段必须匹配给定正则表达式。
该规则底层使用的是 `PHP` 的 `preg_match` 函数。因此，指定的模式需要遵循 `preg_match` 函数所要求的格式并且包含有效的分隔符。例如:

```php
 'email' => 'regex:/^.+@.+$/i'
```

注：使用 `regex/not_regex` 模式时，规则必须放在数组中，而不能使用管道分隔符，尤其是正则表达式中包含管道符号时。

##### required

验证字段值不能为空，以下情况字段值都为空：
值为`null`
值是空字符串
值是空数组或者空的 `Countable` 对象
值是上传文件但路径为空

##### required_if:anotherfield,value,…

验证字段在 `anotherfield` 等于指定值 `value` 时必须存在且不能为空。
如果你想要为 `required_if` 规则构造更复杂的条件，可以使用 `Rule::requiredIf` 方法，该方法接收一个布尔值或闭包。当传递一个闭包时，会返回 `true` 或 `false` 以表明验证字段是否是必须的：

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

除非 `anotherfield` 字段等于 `value`，否则验证字段不能空。

##### required_with:foo,bar,…

验证字段只有在任一其它指定字段存在的情况才是必须的。

##### required_with_all:foo,bar,…

验证字段只有在所有指定字段存在的情况下才是必须的。

##### required_without:foo,bar,…

验证字段只有当任一指定字段不存在的情况下才是必须的。

##### required_without_all:foo,bar,…

验证字段只有当所有指定字段不存在的情况下才是必须的。

##### same:field

给定字段和验证字段必须匹配。

##### size:value

验证字段必须有和给定值 `value` 相匹配的尺寸/大小，对字符串而言，`value` 是相应的字符数目；对数值而言，`value` 是给定整型值；对数组而言，`value` 是数组长度；对文件而言，`value` 是相应的文件千字节数（KB）。

##### starts_with:foo,bar,...

验证字段必须以某个给定值开头。

##### string

验证字段必须是字符串，如果允许字段为空，需要分配 `nullable` 规则到该字段。

##### timezone

验证字符必须是基于 `PHP` 函数 `timezone_identifiers_list` 的有效时区标识

##### unique:table,column,except,idColumn

验证字段在给定数据表上必须是唯一的，如果不指定 `column` 选项，字段名将作为默认 `column`。

1. 指定自定义列名：

```php
'email' => 'unique:users,email_address'
```

2. 自定义数据库连接：
   有时候，你可能需要自定义验证器生成的数据库连接，正如上面所看到的，设置 `unique:users` 作为验证规则将会使用默认数据库连接来查询数据库。要覆盖默认连接，在数据表名后使用“.”指定连接：

```php
'email' => 'unique:connection.users,email_address'
```

3. 强制一个忽略给定 `ID` 的唯一规则：
   有时候，你可能希望在唯一检查时忽略给定 `ID`，例如，考虑一个包含用户名、邮箱地址和位置的”更新属性“界面，你将要验证邮箱地址是唯一的，然而，如果用户只改变用户名字段而并没有改变邮箱字段，你不想要因为用户已经拥有该邮箱地址而抛出验证错误，你只想要在用户提供的邮箱已经被别人使用的情况下才抛出验证错误。

要告诉验证器忽略用户 `ID`，可以使用 `Rule` 类来定义这个规则，我们还要以数组方式指定验证规则，而不是使用 `|` 来界定规则：

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'email' => [
        'required',
        Rule::unique('users')->ignore($user->id),
    ],
]);
```

除了传递模型实例主键值到 `ignore` 方法之外，你还可以传递整个模型实例。组件会自动从模型实例中解析出主键值：

```php
Rule::unique('users')->ignore($user)
```

如果你的数据表使用主键字段不是 `id`，可以在调用 `ignore` 方法的时候指定字段名称：

```php
'email' => Rule::unique('users')->ignore($user->id, 'user_id')
```

默认情况下，`unique` 规则会检查与要验证的属性名匹配的列的唯一性。不过，你可以指定不同的列名作为 `unique` 方法的第二个参数：

```php
Rule::unique('users', 'email_address')->ignore($user->id),
```

4. 添加额外的 `where` 子句：

使用 `where` 方法自定义查询的时候还可以指定额外查询约束，例如，下面我们来添加一个验证 `account_id` 为 1 的约束：

```php
'email' => Rule::unique('users')->where(function ($query) {
    $query->where('account_id', 1);
})
```

##### url

验证字段必须是有效的 URL。

##### uuid

该验证字段必须是有效的 RFC 4122（版本 1、3、4 或 5）全局唯一标识符（UUID）。

##### sometimes

添加条件规则
存在时验证

在某些场景下，你可能想要只有某个字段存在的情况下进行验证检查，要快速实现这个，添加 `sometimes` 规则到规则列表：

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'sometimes|required|email',
]);
```

在上例中，`email` 字段只有存在于 `$data` 数组时才会被验证。

注：如果你尝试验证一个总是存在但可能为空的字段时，参考可选字段注意事项。

复杂条件验证

有时候你可能想要基于更复杂的条件逻辑添加验证规则。例如，你可能想要只有在另一个字段值大于 100 时才要求一个给定字段是必须的，或者，你可能需要只有当另一个字段存在时两个字段才都有给定值。添加这个验证规则并不是一件头疼的事。首先，创建一个永远不会改变的静态规则到 `Validator` 实例：

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'required|email',
    'games' => 'required|numeric',
]);
```

让我们假定我们的 Web 应用服务于游戏收藏者。如果一个游戏收藏者注册了我们的应用并拥有超过 100 个游戏，我们想要他们解释为什么他们会有这么多游戏，例如，也许他们在运营一个游戏二手店，又或者他们只是喜欢收藏。要添加这种条件，我们可以使用 `Validator` 实例上的 `sometimes` 方法：

```php
$v->sometimes('reason', 'required|max:500', function($input) {
    return $input->games >= 100;
});
```

传递给 `sometimes` 方法的第一个参数是我们需要有条件验证的名称字段，第二个参数是我们想要添加的规则，如果作为第三个参数的闭包返回 `true`，规则被添加。该方法让构建复杂条件验证变得简单，你甚至可以一次为多个字段添加条件验证：

```php
$v->sometimes(['reason', 'cost'], 'required', function($input) {
    return $input->games >= 100;
});
```

注：传递给闭包的 `$input` 参数是 `Hyperf\Support\Fluent` 的一个实例，可用于访问输入和文件。

### 验证数组输入

验证表单数组输入字段不再是件痛苦的事情，例如，如果进入的 HTTP 请求包含 `photos[profile]` 字段，可以这么验证：

```php
$validator = $this->validationFactory->make($request->all(), [
    'photos.profile' => 'required|image',
]);
```

我们还可以验证数组的每个元素，例如，要验证给定数组输入中每个 email 是否是唯一的，可以这么做（这种针对提交的数组字段是二维数组，如 `person[][email]` 或 `person[test][email]`）：

```php
$validator = $this->validationFactory->make($request->all(), [
    'person.*.email' => 'email|unique:users',
    'person.*.first_name' => 'required_with:person.*.last_name',
]);
```

类似地，在语言文件中你也可以使用 `*` 字符指定验证消息，从而可以使用单个验证消息定义基于数组字段的验证规则：

```php
'custom' => [
    'person.*.email' => [
        'unique' => '每个人的邮箱地址必须是唯一的',
    ]
],
```

### 自定义验证规则

#### 注册自定义验证规则

`Validation`  组件使用事件机制实现自定义验证规则，我们定义了 `ValidatorFactoryResolved` 事件，您需要做的就是定义一个 `ValidatorFactoryResolved` 的监听器并且在监听器中实现验证器的注册，示例如下。

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
        // 注册了 foo 验证器
        $validatorFactory->extend('foo', function (string $attribute, mixed $value, array $parameters, Validator $validator): bool {
            return $value == 'foo';
        });
        // 当创建一个自定义验证规则时，你可能有时候需要为错误信息定义自定义占位符这里扩展了 :foo 占位符
        $validatorFactory->replacer('foo', function (string $message, string $attribute, string $rule, array $parameters): array|string {
            return str_replace(':foo', $attribute, $message);
        });
    }
}
```

#### 自定义错误信息

你还需要为自定义规则定义错误信息。你可以使用内联自定义消息数组或者在验证语言文件中添加条目来实现这一功能。消息应该被放到数组的第一维，而不是在只用于存放属性指定错误信息的 custom 数组内，以上一节的 `foo` 自定义验证器为例:

`storage/languages/en/validation.php` 添加下面的内容到文件的数组中

```php
    'foo' => 'The :attribute must be foo',
```

`storage/languages/zh_CN/validation.php` 添加下面的内容到文件的数组中

```php    
    'foo' => ' :attribute 必须是 foo',
```

#### 自定义验证器使用

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
            // 使用 foo 验证器
            'name' => 'foo'
        ];
    }
}
```
