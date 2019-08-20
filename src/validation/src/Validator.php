<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Validation;

use BadMethodCallException;
use Hyperf\Di\Container;
use Hyperf\Translation\Contracts\Translator;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Fluent;
use Hyperf\Utils\Str;
use Hyperf\Validation\Contracts\Validation\ImplicitRule;
use Hyperf\Validation\Contracts\Validation\Rule as RuleContract;
use Hyperf\Validation\Contracts\Validation\Validator as ValidatorContract;
use Hyperf\Validation\Support\MessageBag;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Validator implements ValidatorContract
{
    use Concerns\FormatsMessages;
    use Concerns\ValidatesAttributes;

    /**
     * The array of custom error messages.
     *
     * @var array
     */
    public $customMessages = [];

    /**
     * The array of fallback error messages.
     *
     * @var array
     */
    public $fallbackMessages = [];

    /**
     * The array of custom attribute names.
     *
     * @var array
     */
    public $customAttributes = [];

    /**
     * The array of custom displayable values.
     *
     * @var array
     */
    public $customValues = [];

    /**
     * All of the custom validator extensions.
     *
     * @var array
     */
    public $extensions = [];

    /**
     * All of the custom replacer extensions.
     *
     * @var array
     */
    public $replacers = [];

    /**
     * The Translator implementation.
     *
     * @var \Hyperf\Translation\Contracts\Translator
     */
    protected $translator;

    /**
     * The container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The Presence Verifier implementation.
     *
     * @var \Hyperf\Validation\PresenceVerifierInterface
     */
    protected $presenceVerifier;

    /**
     * The failed validation rules.
     *
     * @var array
     */
    protected $failedRules = [];

    /**
     * The message bag instance.
     *
     * @var \Hyperf\Validation\Support\MessageBag
     */
    protected $messages;

    /**
     * The data under validation.
     *
     * @var array
     */
    protected $data;

    /**
     * The initial rules provided.
     *
     * @var array
     */
    protected $initialRules;

    /**
     * The rules to be applied to the data.
     *
     * @var array
     */
    protected $rules;

    /**
     * The current rule that is validating.
     *
     * @var string
     */
    protected $currentRule;

    /**
     * The array of wildcard attributes with their asterisks expanded.
     *
     * @var array
     */
    protected $implicitAttributes = [];

    /**
     * The cached data for the "distinct" rule.
     *
     * @var array
     */
    protected $distinctValues = [];

    /**
     * All of the registered "after" callbacks.
     *
     * @var array
     */
    protected $after = [];

    /**
     * The validation rules that may be applied to files.
     *
     * @var array
     */
    protected $fileRules = [
        'File', 'Image', 'Mimes', 'Mimetypes', 'Min',
        'Max', 'Size', 'Between', 'Dimensions',
    ];

    /**
     * The validation rules that imply the field is required.
     *
     * @var array
     */
    protected $implicitRules = [
        'Required', 'Filled', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout',
        'RequiredWithoutAll', 'RequiredIf', 'RequiredUnless', 'Accepted', 'Present',
    ];

    /**
     * The validation rules which depend on other fields as parameters.
     *
     * @var array
     */
    protected $dependentRules = [
        'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll',
        'RequiredIf', 'RequiredUnless', 'Confirmed', 'Same', 'Different', 'Unique',
        'Before', 'After', 'BeforeOrEqual', 'AfterOrEqual', 'Gt', 'Lt', 'Gte', 'Lte',
    ];

    /**
     * The size related validation rules.
     *
     * @var array
     */
    protected $sizeRules = ['Size', 'Between', 'Min', 'Max', 'Gt', 'Lt', 'Gte', 'Lte'];

    /**
     * The numeric related validation rules.
     *
     * @var array
     */
    protected $numericRules = ['Numeric', 'Integer'];

    /**
     * Create a new Validator instance.
     *
     * @param \Hyperf\Translation\Contracts\Translator $translator
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     */
    public function __construct(
        Translator $translator,
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        $this->initialRules = $rules;
        $this->translator = $translator;
        $this->customMessages = $messages;
        $this->data = $this->parseData($data);
        $this->customAttributes = $customAttributes;

        $this->setRules($rules);
    }

    /**
     * Handle dynamic calls to class methods.
     *
     * @param string $method
     * @param array $parameters
     * @throws \BadMethodCallException
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $rule = Str::snake(substr($method, 8));

        if (isset($this->extensions[$rule])) {
            return $this->callExtension($rule, $parameters);
        }

        throw new BadMethodCallException(sprintf(
            'Method %s::%s does not exist.',
            static::class,
            $method
        ));
    }

    /**
     * Parse the data array, converting dots to ->.
     *
     * @param array $data
     * @return array
     */
    public function parseData(array $data): array
    {
        $newData = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = $this->parseData($value);
            }

            // If the data key contains a dot, we will replace it with another character
            // sequence so it doesn't interfere with dot processing when working with
            // array based validation rules and array_dot later in the validations.
            if (Str::contains((string) $key, '.')) {
                $newData[str_replace('.', '->', $key)] = $value;
            } else {
                $newData[$key] = $value;
            }
        }

        return $newData;
    }

    /**
     * Add an after validation callback.
     *
     * @param callable|string $callback
     * @return $this
     */
    public function after($callback)
    {
        $this->after[] = function () use ($callback) {
            return call_user_func_array($callback, [$this]);
        };

        return $this;
    }

    /**
     * Determine if the data passes the validation rules.
     *
     * @return bool
     */
    public function passes(): bool
    {
        $this->messages = new MessageBag();

        [$this->distinctValues, $this->failedRules] = [[], []];

        // We'll spin through each rule, validating the attributes attached to that
        // rule. Any error messages will be added to the containers with each of
        // the other error messages, returning true if we don't have messages.
        foreach ($this->rules as $attribute => $rules) {
            $attribute = str_replace('\.', '->', $attribute);

            foreach ($rules as $rule) {
                $this->validateAttribute($attribute, $rule);

                if ($this->shouldStopValidating($attribute)) {
                    break;
                }
            }
        }

        // Here we will spin through all of the "after" hooks on this validator and
        // fire them off. This gives the callbacks a chance to perform all kinds
        // of other validation that needs to get wrapped up in this operation.
        foreach ($this->after as $after) {
            call_user_func($after);
        }

        return $this->messages->isEmpty();
    }

    /**
     * Determine if the data fails the validation rules.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * Run the validator's rules against its data.
     *
     * @throws \Hyperf\Validation\ValidationException
     * @return array
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this);
        }

        return $this->validated();
    }

    /**
     * Get the attributes and values that were validated.
     *
     * @throws \Hyperf\Validation\ValidationException
     * @return array
     */
    public function validated(): array
    {
        if ($this->invalid()) {
            throw new ValidationException($this);
        }

        $results = [];

        $missingValue = Str::random(10);

        foreach (array_keys($this->getRules()) as $key) {
            $value = data_get($this->getData(), $key, $missingValue);

            if ($value !== $missingValue) {
                Arr::set($results, $key, $value);
            }
        }

        return $results;
    }

    /**
     * Add a failed rule and error message to the collection.
     *
     * @param string $attribute
     * @param string $rule
     * @param array $parameters
     */
    public function addFailure(string $attribute, string $rule, array $parameters = [])
    {
        if (! $this->messages) {
            $this->passes();
        }

        $this->messages->add($attribute, $this->makeReplacements(
            $this->getMessage($attribute, $rule),
            $attribute,
            $rule,
            $parameters
        ));

        $this->failedRules[$attribute][$rule] = $parameters;
    }

    /**
     * Returns the data which was valid.
     *
     * @return array
     */
    public function valid(): array
    {
        if (! $this->messages) {
            $this->passes();
        }

        return array_diff_key(
            $this->data,
            $this->attributesThatHaveMessages()
        );
    }

    /**
     * Returns the data which was invalid.
     *
     * @return array
     */
    public function invalid(): array
    {
        if (! $this->messages) {
            $this->passes();
        }

        return array_intersect_key(
            $this->data,
            $this->attributesThatHaveMessages()
        );
    }

    /**
     * Get the failed validation rules.
     *
     * @return array
     */
    public function failed(): array
    {
        return $this->failedRules;
    }

    /**
     * Get the message container for the validator.
     *
     * @return MessageBag
     */
    public function messages()
    {
        if (! $this->messages) {
            $this->passes();
        }

        return $this->messages;
    }

    /**
     * An alternative more semantic shortcut to the message container.
     *
     * @return MessageBag
     */
    public function errors()
    {
        return $this->messages();
    }

    /**
     * Get the messages for the instance.
     *
     * @return MessageBag
     */
    public function getMessageBag()
    {
        return $this->messages();
    }

    /**
     * Determine if the given attribute has a rule in the given set.
     *
     * @param string $attribute
     * @param array|string $rules
     * @return bool
     */
    public function hasRule(string $attribute, $rules): bool
    {
        return ! is_null($this->getRule($attribute, $rules));
    }

    /**
     * Get the data under validation.
     *
     * @return array
     */
    public function attributes(): array
    {
        return $this->getData();
    }

    /**
     * Get the data under validation.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set the data under validation.
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $this->parseData($data);

        $this->setRules($this->initialRules);

        return $this;
    }

    /**
     * Get the validation rules.
     *
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Set the validation rules.
     *
     * @param array $rules
     * @return $this
     */
    public function setRules(array $rules)
    {
        $this->initialRules = $rules;

        $this->rules = [];

        $this->addRules($rules);

        return $this;
    }

    /**
     * Parse the given rules and merge them into current rules.
     *
     * @param array $rules
     */
    public function addRules(array $rules)
    {
        // The primary purpose of this parser is to expand any "*" rules to the all
        // of the explicit rules needed for the given data. For example the rule
        // names.* would get expanded to names.0, names.1, etc. for this data.
        $response = (new ValidationRuleParser($this->data))
            ->explode($rules);

        $this->rules = array_merge_recursive(
            $this->rules,
            $response->rules
        );

        $this->implicitAttributes = array_merge(
            $this->implicitAttributes,
            $response->implicitAttributes
        );
    }

    /**
     * Add conditions to a given field based on a Closure.
     *
     * @param array|string $attribute
     * @param array|string $rules
     * @param callable $callback
     * @return $this
     */
    public function sometimes($attribute, $rules, callable $callback)
    {
        $payload = new Fluent($this->getData());

        if (call_user_func($callback, $payload)) {
            foreach ((array) $attribute as $key) {
                $this->addRules([$key => $rules]);
            }
        }

        return $this;
    }

    /**
     * Register an array of custom validator extensions.
     *
     * @param array $extensions
     */
    public function addExtensions(array $extensions)
    {
        if ($extensions) {
            $keys = array_map('\Hyperf\Utils\Str::snake', array_keys($extensions));

            $extensions = array_combine($keys, array_values($extensions));
        }

        $this->extensions = array_merge($this->extensions, $extensions);
    }

    /**
     * Register an array of custom implicit validator extensions.
     *
     * @param array $extensions
     */
    public function addImplicitExtensions(array $extensions)
    {
        $this->addExtensions($extensions);

        foreach ($extensions as $rule => $extension) {
            $this->implicitRules[] = Str::studly($rule);
        }
    }

    /**
     * Register an array of custom implicit validator extensions.
     *
     * @param array $extensions
     */
    public function addDependentExtensions(array $extensions)
    {
        $this->addExtensions($extensions);

        foreach ($extensions as $rule => $extension) {
            $this->dependentRules[] = Str::studly($rule);
        }
    }

    /**
     * Register a custom validator extension.
     *
     * @param string $rule
     * @param \Closure|string $extension
     */
    public function addExtension(string $rule, $extension)
    {
        $this->extensions[Str::snake($rule)] = $extension;
    }

    /**
     * Register a custom implicit validator extension.
     *
     * @param string $rule
     * @param \Closure|string $extension
     */
    public function addImplicitExtension(string $rule, $extension)
    {
        $this->addExtension($rule, $extension);

        $this->implicitRules[] = Str::studly($rule);
    }

    /**
     * Register a custom dependent validator extension.
     *
     * @param string $rule
     * @param \Closure|string $extension
     */
    public function addDependentExtension(string $rule, $extension)
    {
        $this->addExtension($rule, $extension);

        $this->dependentRules[] = Str::studly($rule);
    }

    /**
     * Register an array of custom validator message replacers.
     *
     * @param array $replacers
     */
    public function addReplacers(array $replacers)
    {
        if ($replacers) {
            $keys = array_map('\Hyperf\Utils\Str::snake', array_keys($replacers));

            $replacers = array_combine($keys, array_values($replacers));
        }

        $this->replacers = array_merge($this->replacers, $replacers);
    }

    /**
     * Register a custom validator message replacer.
     *
     * @param string $rule
     * @param \Closure|string $replacer
     */
    public function addReplacer(string $rule, $replacer)
    {
        $this->replacers[Str::snake($rule)] = $replacer;
    }

    /**
     * Set the custom messages for the validator.
     *
     * @param array $messages
     * @return $this
     */
    public function setCustomMessages(array $messages)
    {
        $this->customMessages = array_merge($this->customMessages, $messages);

        return $this;
    }

    /**
     * Set the custom attributes on the validator.
     *
     * @param array $attributes
     * @return $this
     */
    public function setAttributeNames(array $attributes)
    {
        $this->customAttributes = $attributes;

        return $this;
    }

    /**
     * Add custom attributes to the validator.
     *
     * @param array $customAttributes
     * @return $this
     */
    public function addCustomAttributes(array $customAttributes)
    {
        $this->customAttributes = array_merge($this->customAttributes, $customAttributes);

        return $this;
    }

    /**
     * Set the custom values on the validator.
     *
     * @param array $values
     * @return $this
     */
    public function setValueNames(array $values)
    {
        $this->customValues = $values;

        return $this;
    }

    /**
     * Add the custom values for the validator.
     *
     * @param array $customValues
     * @return $this
     */
    public function addCustomValues(array $customValues)
    {
        $this->customValues = array_merge($this->customValues, $customValues);

        return $this;
    }

    /**
     * Set the fallback messages for the validator.
     *
     * @param array $messages
     */
    public function setFallbackMessages(array $messages)
    {
        $this->fallbackMessages = $messages;
    }

    /**
     * Get the Presence Verifier implementation.
     *
     * @throws \RuntimeException
     * @return \Hyperf\Validation\PresenceVerifierInterface
     */
    public function getPresenceVerifier()
    {
        if (! isset($this->presenceVerifier)) {
            throw new RuntimeException('Presence verifier has not been set.');
        }

        return $this->presenceVerifier;
    }

    /**
     * Get the Presence Verifier implementation.
     *
     * @param string $connection
     * @throws \RuntimeException
     * @return \Hyperf\Validation\PresenceVerifierInterface
     */
    public function getPresenceVerifierFor($connection)
    {
        return tap($this->getPresenceVerifier(), function ($verifier) use ($connection) {
            $verifier->setConnection($connection);
        });
    }

    /**
     * Set the Presence Verifier implementation.
     *
     * @param \Hyperf\Validation\PresenceVerifierInterface $presenceVerifier
     */
    public function setPresenceVerifier(PresenceVerifierInterface $presenceVerifier)
    {
        $this->presenceVerifier = $presenceVerifier;
    }

    /**
     * Get the Translator implementation.
     *
     * @return \Hyperf\Translation\Contracts\Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Set the Translator implementation.
     *
     * @param \Hyperf\Translation\Contracts\Translator $translator
     */
    public function setTranslator(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Set the IoC container instance.
     *
     * @param Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Validate a given attribute against a rule.
     *
     * @param string $attribute
     * @param string $rule
     */
    protected function validateAttribute($attribute, $rule)
    {
        $this->currentRule = $rule;

        [$rule, $parameters] = ValidationRuleParser::parse($rule);

        if ($rule == '') {
            return;
        }

        // First we will get the correct keys for the given attribute in case the field is nested in
        // an array. Then we determine if the given rule accepts other field names as parameters.
        // If so, we will replace any asterisks found in the parameters with the correct keys.
        if (($keys = $this->getExplicitKeys($attribute)) &&
            $this->dependsOnOtherFields($rule)) {
            $parameters = $this->replaceAsterisksInParameters($parameters, $keys);
        }

        $value = $this->getValue($attribute);

        // If the attribute is a file, we will verify that the file upload was actually successful
        // and if it wasn't we will add a failure for the attribute. Files may not successfully
        // upload if they are too large based on PHP's settings so we will bail in this case.
        if ($value instanceof UploadedFile && ! $value->isValid() &&
            $this->hasRule($attribute, array_merge($this->fileRules, $this->implicitRules))
        ) {
            return $this->addFailure($attribute, 'uploaded', []);
        }

        // If we have made it this far we will make sure the attribute is validatable and if it is
        // we will call the validation method with the attribute. If a method returns false the
        // attribute is invalid and we will add a failure message for this failing attribute.
        $validatable = $this->isValidatable($rule, $attribute, $value);

        if ($rule instanceof RuleContract) {
            return $validatable
                ? $this->validateUsingCustomRule($attribute, $value, $rule)
                : null;
        }

        $method = "validate{$rule}";

        if ($validatable && ! $this->{$method}($attribute, $value, $parameters, $this)) {
            $this->addFailure($attribute, $rule, $parameters);
        }
    }

    /**
     * Determine if the given rule depends on other fields.
     *
     * @param string $rule
     * @return bool
     */
    protected function dependsOnOtherFields($rule): bool
    {
        return in_array($rule, $this->dependentRules);
    }

    /**
     * Get the explicit keys from an attribute flattened with dot notation.
     *
     * E.g. 'foo.1.bar.spark.baz' -> [1, 'spark'] for 'foo.*.bar.*.baz'
     *
     * @param string $attribute
     * @return array
     */
    protected function getExplicitKeys(string $attribute): array
    {
        $pattern = str_replace('\*', '([^\.]+)', preg_quote($this->getPrimaryAttribute($attribute), '/'));

        if (preg_match('/^' . $pattern . '/', $attribute, $keys)) {
            array_shift($keys);

            return $keys;
        }

        return [];
    }

    /**
     * Get the primary attribute name.
     *
     * For example, if "name.0" is given, "name.*" will be returned.
     *
     * @param string $attribute
     * @return string
     */
    protected function getPrimaryAttribute(string $attribute): string
    {
        foreach ($this->implicitAttributes as $unparsed => $parsed) {
            if (in_array($attribute, $parsed)) {
                return $unparsed;
            }
        }

        return $attribute;
    }

    /**
     * Replace each field parameter which has asterisks with the given keys.
     *
     * @param array $parameters
     * @param array $keys
     * @return array
     */
    protected function replaceAsterisksInParameters(array $parameters, array $keys): array
    {
        return array_map(function ($field) use ($keys) {
            return vsprintf(str_replace('*', '%s', $field), $keys);
        }, $parameters);
    }

    /**
     * Determine if the attribute is validatable.
     *
     * @param object|string $rule
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    protected function isValidatable($rule, string $attribute, $value): bool
    {
        return $this->presentOrRuleIsImplicit($rule, $attribute, $value) &&
            $this->passesOptionalCheck($attribute) &&
            $this->isNotNullIfMarkedAsNullable($rule, $attribute) &&
            $this->hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute);
    }

    /**
     * Determine if the field is present, or the rule implies required.
     *
     * @param object|string $rule
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    protected function presentOrRuleIsImplicit($rule, string $attribute, $value): bool
    {
        if (is_string($value) && trim($value) === '') {
            return $this->isImplicit($rule);
        }

        return $this->validatePresent($attribute, $value) ||
            $this->isImplicit($rule);
    }

    /**
     * Determine if a given rule implies the attribute is required.
     *
     * @param object|string $rule
     * @return bool
     */
    protected function isImplicit($rule): bool
    {
        return $rule instanceof ImplicitRule ||
            in_array($rule, $this->implicitRules);
    }

    /**
     * Determine if the attribute passes any optional check.
     *
     * @param string $attribute
     * @return bool
     */
    protected function passesOptionalCheck(string $attribute): bool
    {
        if (! $this->hasRule($attribute, ['Sometimes'])) {
            return true;
        }

        $data = ValidationData::initializeAndGatherData($attribute, $this->data);

        return array_key_exists($attribute, $data)
            || array_key_exists($attribute, $this->data);
    }

    /**
     * Determine if the attribute fails the nullable check.
     *
     * @param string $rule
     * @param string $attribute
     * @return bool
     */
    protected function isNotNullIfMarkedAsNullable($rule, string $attribute): bool
    {
        if ($this->isImplicit($rule) || ! $this->hasRule($attribute, ['Nullable'])) {
            return true;
        }

        return ! is_null(Arr::get($this->data, $attribute, 0));
    }

    /**
     * Determine if it's a necessary presence validation.
     *
     * This is to avoid possible database type comparison errors.
     *
     * @param string $rule
     * @param string $attribute
     * @return bool
     */
    protected function hasNotFailedPreviousRuleIfPresenceRule($rule, string $attribute): bool
    {
        return in_array($rule, ['Unique', 'Exists']) ? ! $this->messages->has($attribute) : true;
    }

    /**
     * Validate an attribute using a custom rule object.
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Hyperf\Validation\Contracts\Validation\Rule $rule
     */
    protected function validateUsingCustomRule(string $attribute, $value, $rule)
    {
        if (! $rule->passes($attribute, $value)) {
            $this->failedRules[$attribute][get_class($rule)] = [];

            $messages = $rule->message() ? (array) $rule->message() : [get_class($rule)];

            foreach ($messages as $message) {
                $this->messages->add($attribute, $this->makeReplacements(
                    $message,
                    $attribute,
                    get_class($rule),
                    []
                ));
            }
        }
    }

    /**
     * Check if we should stop further validations on a given attribute.
     *
     * @param string $attribute
     * @return bool
     */
    protected function shouldStopValidating(string $attribute): bool
    {
        if ($this->hasRule($attribute, ['Bail'])) {
            return $this->messages->has($attribute);
        }

        if (isset($this->failedRules[$attribute]) &&
            array_key_exists('uploaded', $this->failedRules[$attribute])) {
            return true;
        }

        // In case the attribute has any rule that indicates that the field is required
        // and that rule already failed then we should stop validation at this point
        // as now there is no point in calling other rules with this field empty.
        return $this->hasRule($attribute, $this->implicitRules) &&
            isset($this->failedRules[$attribute]) &&
            array_intersect(array_keys($this->failedRules[$attribute]), $this->implicitRules);
    }

    /**
     * Generate an array of all attributes that have messages.
     *
     * @return array
     */
    protected function attributesThatHaveMessages(): array
    {
        return collect($this->messages()->toArray())->map(function ($message, $key) {
            return explode('.', $key)[0];
        })->unique()->flip()->all();
    }

    /**
     * Get a rule and its parameters for a given attribute.
     *
     * @param string $attribute
     * @param array|string $rules
     * @return null|array
     */
    protected function getRule(string $attribute, $rules)
    {
        if (! array_key_exists($attribute, $this->rules)) {
            return;
        }

        $rules = (array) $rules;

        foreach ($this->rules[$attribute] as $rule) {
            [$rule, $parameters] = ValidationRuleParser::parse($rule);

            if (in_array($rule, $rules)) {
                return [$rule, $parameters];
            }
        }
    }

    /**
     * Get the value of a given attribute.
     *
     * @param string $attribute
     * @return mixed
     */
    protected function getValue(string $attribute)
    {
        return Arr::get($this->data, $attribute);
    }

    /**
     * Call a custom validator extension.
     *
     * @param string $rule
     * @param array $parameters
     * @return null|bool
     */
    protected function callExtension(string $rule, array $parameters)
    {
        $callback = $this->extensions[$rule];

        if (is_callable($callback)) {
            return call_user_func_array($callback, $parameters);
        }
        if (is_string($callback)) {
            return $this->callClassBasedExtension($callback, $parameters);
        }
    }

    /**
     * Call a class based validator extension.
     *
     * @param string $callback
     * @param array $parameters
     * @return bool
     */
    protected function callClassBasedExtension(string $callback, array $parameters)
    {
        [$class, $method] = Str::parseCallback($callback, 'validate');

        return call_user_func_array([$this->container->make($class), $method], $parameters);
    }
}
