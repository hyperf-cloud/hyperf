# 開發者工具

## 安裝

```
composer require hyperf/devtool
```

## 支援的命令

```bash
php bin/hyperf.php
```

透過執行上面的命令可獲得 Command 所支援的所有命令，其中返回結果 `gen` 系列命令和 `vendor:publish` 命令主要為 `devtool` 元件提供支援

```bash
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

## 快速開啟

增加了一個非常簡單的功能，用內建的 `gen` 命令快速開啟建立的檔案，支援 `sublime`, `textmate`, `cursor`, `emacs`, `macvim`, `phpstorm`, `idea`, `vscode`, `vscode-insiders`, `vscode-remote`, `vscode-insiders-remote`, `atom`, `nova`, `netbeans`, `xdebug`。

還需要在 `config/autoload/devtool.php` 上新增這個配置塊：

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
