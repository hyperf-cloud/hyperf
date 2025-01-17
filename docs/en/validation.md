# Validator

## Foreword

> [hyperf/validation](https://github.com/hyperf/validation) is derived from [illuminate/validation](https://github.com/illuminate/validation), we've made some modifications to it, but kept the same validation rules. Thanks to the Laravel development team for implementing such a powerful and easy-to-use validator component.

## Installation

### Import component package

```bash
composer require hyperf/validation
```

### Add middleware

You need to add a global middleware `Hyperf\Validation\Middleware\ValidationMiddleware` configuration to the `config/autoload/middlewares.php` configuration file for the server that uses the validator component. The following is `http` server plus the corresponding examples of global middleware:

```php
<?php
return [
    // The following http string corresponds to the value corresponding to the name attribute of each server in config/autoload/server.php, which means that the corresponding middleware configuration is only applied to the server
    'http' => [
        // Configure your global middleware in the array, the order is based on the order of the array
        \Hyperf\Validation\Middleware\ValidationMiddleware::class
        // Other middleware goes here
    ],
];
```

> If the global middleware is not set correctly, the use of `FormRequest` may be invalid.

### Add exception handler

The exception handler mainly deals with `Hyperf\Validation\ValidationException` exceptions. We provide a `Hyperf\Validation\ValidationExceptionHandler` for processing. You need to manually configure this exception handler to your project’s by adding it to the `config/autoload/exceptions.php` file, of course, you can also customize your exception handler.

```php
<?php
return [
    'handler' => [
        // This corresponds to your current server name
        'http' => [
            \Hyperf\Validation\ValidationExceptionHandler::class,
        ],
    ],
];
```

### Publish validator language files

Due to the multi-language function, this component relies on the [hyperf/translation](https://github.com/hyperf/translation) component. If you have not added the configuration file of the translation component, you can execute the following command to publish the configuration file of the translation component. If the configuration already exists, you only need to publish the language file of the validator component:

Publish the files of the translation component:

```bash
php bin/hyperf.php vendor:publish hyperf/translation
```

Publish the files of the validator component:

```bash
php bin/hyperf.php vendor:publish hyperf/validation
```

Executing the above command will publish the validator's language file `validation.php` to the corresponding language file directory, `en` refers to the English language file, and `zh_CN` refers to the simplified Chinese language file. You can customize the contents of the file.

```
/storage
    /languages
        /en
            validation.php
        /zh_CN
            validation.php

```

## Use

### Form request validation

For complex validation scenarios, you can create a `FormRequest`. The form request is a custom request class that contains validation logic. You can create a form validation class called FooRequest by executing the following command:

```bash
php bin/hyperf.php gen:request FooRequest
```

The form validation class will be generated in the `app\Request` directory. If the directory does not exist, the directory will be created automatically when running the command.
Next we add some validation rules to the `rules` method of this class:

```php
/**
 * Get the validation rules applied to the request
 */
public function rules(): array
{
    return [
        'foo' => 'required|max:255',
        'bar' => 'required',
    ];
}
```

So, how does the validation rule take effect? All you have to do is to declare the request class as a parameter through type hints in the controller method. This way, the incoming form request will be validated before the controller method is called, which means you don’t need to write any validation logic in the controller and decouple the two parts of the code well:

```php
<?php
namespace App\Controller;

use App\Request\FooRequest;

class IndexController
{
    public function index(FooRequest $request)
    {
        // The incoming request is verified...

        // Get the verified data...
        $validated = $request->validated();
    }
}
```

If the validation fails, the validator will throw a `Hyperf\Validation\ValidationException` exception. You can handle the exception by adding a custom exception handling class. At the same time, we also provide a `Hyperf\Validation\ValidationExceptionHandler` exception handler to handle the exception, you can also directly configure the exception handler provided by us to handle it. However, the default exception handler may not be able to meet your needs. You can customize the behavior after validation failure by customizing the exception handler according to the situation.

#### Custom error message

You can customize the error messages used by the form request by overriding the `messages` method. This method should return an array of attribute/rule pairs and their corresponding error messages:

```php
/**
 * Get the error message of the defined validation rule
 */
public function messages(): array
{
    return [
        'foo.required' => 'foo is required',
        'bar.required' => 'bar is required',
    ];
}
```

#### Custom authentication attributes

If you want to replace the `:attribute` part of the authentication message with a custom attribute name, you can override the `attributes` method to specify a custom name. This method will return an array of attribute names and corresponding custom name key-value pairs:

```php
/**
 * Get custom attributes for validation errors
 */
public function attributes(): array
{
    return [
        'foo' => 'foo of request',
    ];
}
```

### Creating a validator manually

If you don't want to use the automatic validation function of `FormRequest`, you can obtain the validator factory class by injecting the `ValidatorFactoryInterface` interface class, and then manually create a validator instance through the `make` method:

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

The first parameter passed to the `make` method is the data to be verified, and the second parameter is the validation rule for the data.

#### Custom error message

If necessary, you can also use custom error messages instead of default values ​​for validation. There are several ways to specify custom information. First, you can pass custom information as the third parameter to the `make` method:

```php
<?php
$messages = [
    'required' => 'The :attribute field is required.',
];

$validator = $this->validationFactory->make($request->all(), $rules, $messages);
```

In this example, the `:attribute` placeholder will be replaced by the actual name of the field under validation. In addition, you can also use other placeholders in the validation message. E.g:

```php
$messages = [
    'same' => 'The :attribute and :other must match.',
    'size' => 'The :attribute must be exactly :size.',
    'between' => 'The :attribute value :input is not between :min-:max.',
    'in' => 'The :attribute must be one of the following types: :values',
];
```

#### Specify custom information for a given attribute

Sometimes you may only want to customize error messages for specific fields. Just add `.` after the field name to specify the validation rules with custom messages:

```php
$messages = [
    'email.required' => 'We need to know your e-mail address!',
];
```

#### Specify custom information in the PHP file

In most cases, you may specify custom information in the file instead of passing them directly to the `Validator`. To do this, you need to put your information in the `custom` array in the `storage/languages/xx/validation.php` language file.

#### Specify custom attributes in PHP files

If you want to replace the `:attribute` part of the validation information with a custom attribute name, you can specify the custom name in the `attributes` array of the `storage/languages/xx/validation.php` language file:

```php
'attributes' => [
    'email' => 'email address',
],
```

### Post-validation hook

The validator also allows you to add callback functions that are allowed after the validation is successful, so that you can perform the next step of validation, and even add more error messages to the message collection. To use it, just use the `after` method on the validation instance:

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
                $validator->errors()->add('field','Something is wrong with this field!');
            }
        });

        if ($validator->fails()) {
            //
        }
    }
}
```

## Handling error messages

Calling the `errors` method through the `Validator` instance returns a `Hyperf\Support\MessageBag` instance, which has various convenient methods for handling error messages.

### View the first error message of a specific field

To view the first error message for a specific field, you can use the `first` method:

```php
$errors = $validator->errors();

echo $errors->first('foo');
```

### View all error messages for a specific field

If you need to get an array of all error messages for a specified field, you can use the `get` method:

```php
foreach ($errors->get('foo') as $message) {
    //
}
```

If you want to validate the array fields of the form, you can use `*` to get all error messages for each array element:

```php
foreach ($errors->get('foo.*') as $message) {
    //
}
```

### View all error messages for all fields

If you want to get all error messages for all fields, you can use the `all` method:

```php
foreach ($errors->all() as $message) {
    //
}
```

### Determine whether a specific field contains an error message

The `has` method can be used to determine whether there is an error message in the specified field:

```php
if ($errors->has('foo')) {
    //
}
```

### Scene

The validator adds a scenario function, so we can easily modify the validation rules on demand.

> This feature requires a version of this component greater than or equal to 2.2.7
Create a `SceneRequest` as follows:

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

When we use it normally, all validation rules are used, i.e. `username` and `gender` are required.

We can set the scenario so that this request only validates the `username` mandatory field.

If we configure `Hyperf\Validation\Middleware\ValidationMiddleware` and inject `SceneRequest` to the method,
it will cause the entry to be validated directly in the middleware,
so we need to get the `SceneRequest` from the container in the method to switch the scene.

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

But, we can use annotation `Scene` to switch it.

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

    #[Scene(scene:'bar2', argument: 'request')] // bind $request
    public function bar2(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }

    #[Scene(scene:'bar3', argument: 'request')] // bind $request
    #[Scene(scene:'bar3', argument: 'req')] // bind $req
    public function bar3(SceneRequest $request, DebugRequest $req)
    {
        return $this->response->success($request->all());
    }

    #[Scene()] // the default scene is method name, The effect is equivalent to #[Scene(scene: 'bar1')]
    public function bar1(SceneRequest $request)
    {
        return $this->response->success($request->all());
    }
}
```

#### Parameters of the same name in different scenarios

It is common to define the same parameter name under both interfaces, but at this point different scenarios may require different parameter validation requirements, we need to distinguish between definitions, you can try the following example

```php
public function rules(): array
{
    return [
        'foo' => 'bail|required',
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

## Validation rules

The following is a list of valid rules and their functions:

##### accepted

The value of the validation field must be `yes`, `on`, `1` or `true`, which is useful when "agreeing to the service agreement".

##### active_url

The validation field must be based on the `PHP` function `dns_get_record`, with the value recorded by `A` or `AAAA`.

##### after:date

The field under validation must be a value after the given date, and the date will be passed through the PHP function `strtotime`:

```php
'start_date' => 'required|date|after:tomorrow'
```

Instead of passing a date string to `strtotime`, you can specify another field to compare with the date:

```php
'finish_date' => 'required|date|after:start_date'
```

##### after_or_equal:date

The field under validation must be a value greater than or equal to the given date. For more information, please refer to the `after:date` rule.

##### alpha

The validation field must be letters (including Chinese).

##### alpha_dash

The validation field can contain letters (including Chinese) and numbers, as well as dashes and underscores.

##### alpha_num

The validation field must be letters (including Chinese) or numbers.

##### ascii

The field being validated must be exactly 7-bit ASCII characters.

##### array

The validation field must be a PHP array.

##### bail

If the first validation rule fails to verify, stop running other validation rules.

##### before:date

Contrary to `after:date,` the validation field must be a value before the specified date, and the date will be passed to the PHP `strtotime` function.

##### before_or_equal:date

The field under validation must be less than or equal to the given date. The date will be passed to PHP's `strtotime` function.

##### between:min,max

Verify that the field size is between the given minimum and maximum values. Strings, numbers, arrays, and files can all use this rule like the size rule:

'name' =>'required|between:1,20'

##### boolean

The field under validation must be able to be converted to a boolean value and accept input such as true, false, 1, 0, "1" and "0".

##### confirmed

The validation field must have a matching field foo_confirmation. For example, if the validation field is password, you must enter a matching password_confirmation field.

##### date

The field under validation must be a valid date based on the PHP `strtotime` function

 ##### date_equals:date

 The field under validation must be equal to the given date, and the date will be passed to the PHP `strtotime` function.

 ##### date_format:format

 The field under validation must match the specified format. You can use the PHP function `date` or `date_format` to validate the field.

 ##### different:field

 The field under validation must be a different value from the specified field.

 ##### digits:value

 The field under validation must be numeric and the length must be the value specified by value.

 ##### digits_between:min,max

 The length of the field under validation must be between the minimum and maximum values.

 ##### dimensions

 The size of the verified image must meet the constraints specified by the specified parameters:

 ```php
 'avatar' => 'dimensions:min_width=100,min_height=200'
 ```

 Valid constraints include: `min_width`, `max_width`, `min_height`, `max_height`, `width`, `height`, `ratio`.

 `ratio` constrains the width/height ratio, which can be expressed by the expression `3/2` or the floating point number `1.5`:

 ```php
 'avatar' => 'dimensions:ratio=3/2'
 ```

 Since this rule requires multiple parameters, you can use the `Rule::dimensions` method to construct the rule:

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

 When processing arrays, the validation field cannot contain duplicate values:

 ```php
 'foo.*.id' => 'distinct'
 ```

 ##### email

 The validation field must be a properly formatted email address.

 ##### exists:table,column

 The validation field must exist in the specified data table.

 Basic use:

 ```php
 'state' => 'exists:states'
```

If the `column` option is not specified, the field name will be used.

Specify a custom column name:

```php
'state' => 'exists:states,abbreviation'
```

Sometimes, you may need to specify the database connection to be used for the `exists` query. This can be achieved by using the `.` pre-database connection before the table name:

```php
'email' => 'exists:connection.staff,email'
```

If you want to customize the query executed by the validation rules, you can use the `Rule` class to define the rules. In this example, we also specify the validation rules in the form of an array, instead of using `|` characters to qualify them:

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

The validation field must be a successfully uploaded file.

##### filled

The validation field cannot be empty if it exists.

##### gt:field

The field under validation must be larger than the given `field` field, and the two field types must be the same, applicable to strings, numbers, arrays and files, similar to the `size` rule

##### gte:field

The field under validation must be greater than or equal to the given `field` field, and the two field types must be the same, applicable to strings, numbers, arrays and files, similar to the `size` rule

##### image

The validation file must be an image (`jpeg`, `png`, `bmp`, `gif` or `svg`)

##### in:foo,bar...

The field value under validation must be in the given list. Since this rule often requires us to implode the array, we can use Rule::in to construct this rule:

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'zones' => [
        'required',
        Rule::in(['first-zone','second-zone']),
    ],
]);
```

##### in_array:anotherfield

The field under validation must exist in another field value.

##### integer

The field under validation must be an integer.

##### ip

The validation field must be an IP address.

##### ipv4

The validation field must be an IPv4 address.

##### ipv6

The validation field must be an IPv6 address.

##### json

The validation field must be a valid JSON string

##### lt:field

The field under validation must be smaller than the given `field` field, and the two field types must be the same, applicable to strings, numbers, arrays and files, similar to the `size` rule

##### lte:field

The validation field must be less than or equal to the given `field` field, and the two field types must be the same, applicable to strings, numbers, arrays and files, similar to the `size` rule

##### max:value

The field under validation must be less than or equal to the maximum value, which is the same as the use of the `size` rules for string, numeric, array, and file fields.

##### mimetypes: text/plain...

The validation file must match one of the given `MIME` file types:

```php
'video' => 'mimetypes:video/avi,video/mpeg,video/quicktime'
```

In order to determine the `MIME` type of the uploaded file, the component will read the file content to guess the `MIME` type, which may be different from the client's `MIME` type.

##### mimes:foo,bar,...

The `MIME` type of the validation file must be one of the extension types listed in the rule
Basic usage of `MIME` rules:

```php
'photo' => 'mimes:jpeg,bmp,png'
```

Although you only specify the extension, this rule actually verifies the file `MIME` type obtained by reading the file content.
The complete list of `MIME` types and their corresponding extensions can be found here: [mime types](http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types)

##### min:value

In contrast to `max:value`, the validation field must be greater than or equal to the minimum value. For string, numeric, array, and file fields, it is consistent with the use of the `size` rule.

##### not_in:foo,bar,...

The field value under validation cannot be in the given list. Similar to the `in` rule, we can use the `Rule::notIn` method to construct the rule:

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'toppings' => [
        'required',
        Rule::notIn(['sprinkles','cherries']),
    ],
]);
```

##### not_regex:pattern

The field under validation cannot match the given regular expression

Note: When using the `regex/not_regex` mode, the rules must be placed in an array instead of pipe separators, especially when the regular expression contains pipe symbols.

##### nullable

The validation field can be `null`, which is useful when validating some primitive data that can be `null` such as integers or strings.

##### numeric

The field under validation must be numeric

##### present

The validation field must appear in the input data but can be empty.

##### regex:pattern

The field under validation must match the given regular expression.
The bottom layer of this rule is the `preg_match` function of `PHP`. Therefore, the specified pattern needs to follow the format required by the `preg_match` function and contain a valid separator. E.g:

```php
 'email' => 'regex:/^.+@.+$/i'
```

Note: When using the `regex/not_regex` mode, the rules must be placed in an array instead of pipe separators, especially when the regular expression contains pipe symbols.

##### required

The validation field value cannot be empty, and the field value is empty in the following cases:
- Value is `null`
- Value is an empty string
- The value is an empty array or an empty `Countable` object
- The value is uploaded file but the path is empty

##### required_if:anotherfield,value,…

The validation field must exist when `anotherfield` is equal to the specified value `value` and cannot be empty.
If you want to construct more complex conditions for the `required_if` rule, you can use the `Rule::requiredIf` method, which accepts a boolean or closure. When passing a closure, it will return `true` or `false` to indicate whether the validation field is required:

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

Unless the `anotherfield` field is equal to `value`, the validation field cannot be empty.

##### required_with:foo,bar,…

The validation field is only necessary if any other specified field exists.

##### required_with_all:foo,bar,…

The validation field is only necessary if all the specified fields exist.

##### required_without:foo,bar,…

The validation field is only necessary if any specified field does not exist.

##### required_without_all:foo,bar,…

The validation field is only necessary if all the specified fields do not exist.

##### same:field

The given field and the validation field must match.

##### size:value

The validation field must have a size/size that matches the given value `value`. For strings, `value` is the number of characters; for numbers, `value` is a given integer value; for arrays In terms of `value` is the length of the array; for files, `value` is the number of kilobytes (KB) of the corresponding file.

##### starts_with:foo,bar,...

The field under validation must start with a given value.

##### string

The validation field must be a string. If the field is allowed to be empty, you need to assign the `nullable` rule to the field.

##### timezone

The validation character must be a valid time zone identifier based on the `PHP` function `timezone_identifiers_list`

##### unique:table,column,except,idColumn

The field under validation must be unique on a given data table. If the `column` option is not specified, the field name will be used as the default `column`.

1. Specify the custom column name:

```php
'email' => 'unique:users,email_address'
```

2. Custom database connection:
Sometimes, you may need to customize the database connection generated by the validator. As you can see above, setting `unique:users` as the authentication rule will use the default database connection to query the database. To override the default connection, use "." after the data table name to specify the connection:

```php
'email' => 'unique:connection.users,email_address'
```

3. Force a unique rule that ignores a given `ID`:
Sometimes, you may wish to ignore a given `ID` during the unique check. For example, consider an "update properties" interface that includes a user name, email address, and location. You will want to verify that the email address is unique. Changing the username field does not change the email field. You don't want to throw a validation error because the user already has the email address. You only want to throw a validation error when the email provided by the user has been used by others.

To tell the validator to ignore the user ID, you can use the Rule class to define this rule. We also need to specify the validation rule in an array instead of using the `|` to define the rule:

```php
use Hyperf\Validation\Rule;

$validator = $this->validationFactory->make($data, [
    'email' => [
        'required',
        Rule::unique('users')->ignore($user->id),
    ],
]);
```

In addition to passing the primary key value of the model instance to the `ignore` method, you can also pass the entire model instance. The component will automatically parse out the primary key value from the model instance:

```php
Rule::unique('users')->ignore($user)
```

If your data table uses a primary key field other than `id`, you can specify the field name when calling the `ignore` method:

```php
'email' => Rule::unique('users')->ignore($user->id,'user_id')
```

By default, the `unique` rule checks the uniqueness of the column matching the attribute name to be verified. However, you can specify different column names as the second parameter of the unique method:

```php
Rule::unique('users','email_address')->ignore($user->id),
```

4. Add an additional `where` clause:

You can also specify additional query constraints when using the `where` method to customize the query. For example, let's add a constraint that verifies that `account_id` is 1:

```php
'email' => Rule::unique('users')->where(function ($query) {
    $query->where('account_id', 1);
})
```

##### url

The validation field must be a valid URL.

##### uuid

The validation field must be a valid RFC 4122 (version 1, 3, 4, or 5) universally unique identifier (UUID).

##### sometimes

Add conditional rules
Verify when it exists

In some scenarios, you may want to perform validation checks when only a certain field exists. To quickly implement this, add the `sometimes` rule to the rule list:

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'sometimes|required|email',
]);
```

In the above example, the `email` field will only be validated if it exists in the `$data` array.

Note: If you try to verify a field that always exists but may be empty, refer to the optional field considerations.

Complex condition validation

Sometimes you may want to add validation rules based on more complex conditional logic. For example, you may want to require a given field to be required only when the value of another field is greater than 100, or you may need to require both fields to have a given value only when the other field exists. Adding this validation rule is not a headache. First, create a static rule that will never change to the `Validator` instance:

```php
$validator = $this->validationFactory->make($data, [
    'email' => 'required|email',
    'games' => 'required|numeric',
]);
```

Let us assume that our web application serves game collectors. If a game collector signs up for our app and owns more than 100 games, we want them to explain why they have so many games. For example, maybe they are running a second-hand game store, or they just like collecting. To add this condition, we can use the `sometimes` method on the `Validator` instance:

```php
$v->sometimes('reason','required|max:500', function($input) {
    return $input->games >= 100;
});
```

The first parameter passed to the `sometimes` method is the name field we need to conditionally validate, and the second parameter is the rule we want to add. If the closure as the third parameter returns `true`, the rule is added . This method makes it easy to build complex conditional validation, and you can even add conditional validation for multiple fields at once:

```php
$v->sometimes(['reason','cost'],'required', function($input) {
    return $input->games >= 100;
});
```

Note: The `$input` parameter passed to the closure is an instance of `Hyperf\Support\Fluent` and can be used to access inputs and files.

### Validate array input

It is no longer a pain to verify the input fields of the form array. For example, if the incoming HTTP request contains the `photos[profile]` field, you can verify it like this:

```php
$validator = $this->validationFactory->make($request->all(), [
    'photos.profile' => 'required|image',
]);
```

We can also verify each element of the array. For example, to verify that each email in a given array input is unique, we can do so (this kind of submitted array field is a two-dimensional array, such as `person[][email ]` or `person[test][email]`):

```php
$validator = $this->validationFactory->make($request->all(), [
    'person.*.email' => 'email|unique:users',
    'person.*.first_name' => 'required_with:person.*.last_name',
]);
```

Similarly, in the language file, you can also use the `*` character to specify the validation message, so that you can use a single validation message to define validation rules based on array fields:

```php
'custom' => [
    'person.*.email' => [
        'unique' => 'E-mail address of each person must be unique',
    ]
],
```

### Custom Validation Rules

#### Register custom validation rules

The `Validation` component uses an event mechanism to implement custom validation rules. We have defined the `ValidatorFactoryResolved` event. All you need to do is define a listener for `ValidatorFactoryResolved` and implement the registration of the validator in the listener. The example is as follows.

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
        /** @var ValidatorFactoryInterface $validatorFactory */
        $validatorFactory = $event->validatorFactory;
        // registered foo validator
        $validatorFactory->extend('foo', function (string $attribute, mixed $value, array $parameters, Validator $validator): bool {
            return $value == 'foo';
        });
        // When creating a custom validation rule, you may sometimes need to define a custom placeholder for error messages. Here is an extension of the :foo placeholder
        $validatorFactory->replacer('foo', function (string $message, string $attribute, string $rule, array $parameters): array|string {
            return str_replace(':foo', $attribute, $message);
        });
    }
}
```

#### Custom error message

You also need to define error messages for custom rules. You can use inline custom message arrays or add entries in the validation language file to achieve this functionality. The message should be placed in the first dimension of the array, not in the custom array, which is only used to store the attribute-specified error information. Take the `foo` custom validator in the previous section as an example:

`storage/languages/en/validation.php` add the following content to the file array

```php
    'foo' => 'The :attribute must be foo',
```

`storage/languages/zh_CN/validation.php` add the following content to the file array

```php
    'foo' => ':attribute must be foo',
```

#### Custom validator usage

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
            // use foo validator
            'name' => 'foo'
        ];
    }
}
```
