# Dev Tool

## Installation

```
composer require hyperf/devtool
```

# Supported Commands

```bash
php bin/hyperf.php
```

All commands supported by Command can be listed by executing the above command. Series of commands under the `gen` and `vendor:publish` mainly provide support for the `devtool` component.

```
 gen
  gen:amqp-consumer  Create a new amqp consumer class
  gen:amqp-producer  Create a new amqp producer class
  gen:aspect         Create a new aspect class
  gen:command        Create a new command class
  gen:controller     Create a new controller class
  gen:job            Create a new job class
  gen:listener       Create a new listener class
  gen:middleware     Create a new middleware class
  gen:process        Create a new process class
 vendor
  vendor:publish     Publish any publishable configs from vendor packages.
```

## Quick Open

Added a very simple function to quickly open created files with the built-in `gen` command, supporting `sublime`, `textmate`, `cursor`, `emacs`, `macvim`, `phpstorm`, `idea`, `vscode`, `vscode-insiders`, `vscode-remote`, `vscode-insiders-remote`, `atom`, `nova`, `netbeans`, `xdebug`.

You also need to add this configuration block on `config/autoload/devtool.php`:

```php
return [
    /**
     * Supported IDEs: "sublime", "textmate", "cursor", "emacs", "macvim", "phpstorm", "idea",
     *        "vscode", "vscode-insiders", "vscode-remote", "vscode-insiders-remote",
     *        "atom", "nova", "netbeans", "xdebug"
     */
    'ide' => env('DEVTOOL_IDE', ''),
    //...
];
```