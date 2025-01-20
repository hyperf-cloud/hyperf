# WebSocket server

Hyperf provides an encapsulation of WebSocket Server. A WebSocket application can be quickly built based on [hyperf/websocket-server](https://github.com/hyperf/websocket-server).

## Installation

```bash
composer require hyperf/websocket-server
```

## Configure Server

Modify `config/autoload/server.php` and add the following configuration.

```php
<?php

return [
    'servers' => [
        [
            'name' => 'ws',
            'type' => Server::SERVER_WEBSOCKET,
            'host' => '0.0.0.0',
            'port' => 9502,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_HAND_SHAKE => [Hyperf\WebSocketServer\Server::class, 'onHandShake'],
                Event::ON_MESSAGE => [Hyperf\WebSocketServer\Server::class, 'onMessage'],
                Event::ON_CLOSE => [Hyperf\WebSocketServer\Server::class, 'onClose'],
            ],
        ],
    ],
];
```

## Configure Router

> So far, only the config file way is supported. The annotation way will come soon.

In the `config/routes.php` file, add the router configuration of the Server of corresponding `ws`, where `ws` is the `name` of the WebSocket Server in `config/autoload/server.php`.


```php
<?php

Router::addServer('ws', function () {
    Router::get('/', 'App\Controller\WebSocketController');
});
```

## Configure Middleware

In the `config/autoload/middlewares.php` file, add the middleware configuration of the Server of corresponding `ws`, where `ws` is the `name` of the WebSocket Server in `config/autoload/server.php`.


```php
<?php

return [
    'ws' => [
        yourMiddleware::class
    ]
];
```

## Create corresponding controller

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Swoole\Http\Request;
use Swoole\Server;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;

class WebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    public function onMessage($server, Frame $frame): void
    {
        $server->push($frame->fd, 'Recv: ' . $frame->data);
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        var_dump('closed');
    }

    public function onOpen($server, Request $request): void
    {
        $server->push($request->fd, 'Opened');
    }
}
```

Start the Server, then you can see a WebSocket Server is started and listen to port of 9502. You can then use any WebSocket Client to communicate with this WebSocket Server.

```
$ php bin/hyperf.php start

[INFO] Worker#0 started.
[INFO] WebSocket Server listening at 0.0.0.0:9502
[INFO] HTTP Server listening at 0.0.0.0:9501
```

!> When we listen the 9501 of the HTTP Server and the 9502 of the WebSocket Server at the same time, the WebSocket Client can connect to the WebSocket Server through the two ports 9501 and 9502, that is, connecting to `ws://0.0.0.0:9501` and `ws:/ /0.0.0.0:9502` both works.

Due to the `Swoole\WebSocket\Server` inherits from `Swoole\Http\Server`, you can use HTTP to perform all WebSocket pushes. For more details, please refer the callback of `onRequest` in [Swoole Doc](https://wiki.swoole.com/#/websocket_server?id=websocketserver)

If you need to close it, you can add the `open_websocket_protocol` configuration item to the `http` service in `config/autoload/server.php` file.


```php
<?php
return [
    // Unrelated configs are ignored
    'servers' => [
        [
            'name' => 'http',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => 9501,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
            'settings' => [
                'open_websocket_protocol' => false,
            ]
        ],
    ]
];
```

## Connected Context

Callbacks for onOpen, onMessage, and onClose of WebSocket are not triggered in the same coroutine, so that they cannot directly use the stored information of context. **Connected Context** is provided by WebSocket Server component, and API is same as coroutine context's.

```php
<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\WebSocketServer\Context;
use Swoole\Http\Request;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;

class WebSocketController implements OnMessageInterface, OnOpenInterface
{
    public function onMessage($server, Frame $frame): void
    {
        $server->push($frame->fd, 'Username: ' . Context::get('username'));
    }

    public function onOpen($server, Request $request): void
    {
        Context::set('username', $request->cookie['username']);
    }
}
```

## Multiple Server Configuration

```
# /etc/nginx/conf.d/ng_socketio.conf
# multiple ws server
upstream io_nodes {
    server ws1:9502;
    server ws2:9502;
}
server {
  listen 9502;
  # server_name your.socket.io;
  location / {
    proxy_set_header Upgrade "websocket";
    proxy_set_header Connection "upgrade";
    # proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    # proxy_set_header Host $host;
    # proxy_http_version 1.1;
    # Forward to multiple ws server
    proxy_pass http://io_nodes;
  }
}
```

## Sender

When you want to close `WebSocket` connection in `HTTP` service, you can used`Hyperf\WebSocketServer\Sender`.

`Sender` will check if `fd` is carried by the current `Worker`, if so, then directly send the message, otherwise, send message to all other `Worker` through `PipeMessage`. Other `Worker`s will do the same as mentioned above.

`Sender` supports `push` and `disconnect`. 

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\WebSocketServer\Sender;
use function Hyperf\Coroutine\go;

#[AutoController]
class ServerController
{
    #[Inject]
    protected Sender $sender;

    public function close(int $fd)
    {
        go(function () use ($fd) {
            sleep(1);
            $this->sender->disconnect($fd);
        });

        return '';
    }

    public function send(int $fd)
    {
        $this->sender->push($fd, 'Hello World.');

        return '';
    }
}

```


## Handle Http Request in Websocket Server

In addition to separating HTTP services and WebSocket services through ports, we can also listen for HTTP requests in WebSocket.

Because `server.servers.*.callbacks` configuration items are all singleton，so we need define a new singleton config in `dependencies`.

```php
<?php
return [
    'HttpServer' => Hyperf\HttpServer\Server::class,
];
```

Then modify the `callbacks` configuration in our `WebSocket` service. The following hides irrelevant configurations

```php
<?php

declare(strict_types=1);

use Hyperf\Server\Event;
use Hyperf\Server\Server;

return [
    'mode' => SWOOLE_BASE,
    'servers' => [
        [
            'name' => 'ws',
            'type' => Server::SERVER_WEBSOCKET,
            'host' => '0.0.0.0',
            'port' => 9502,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => ['HttpServer', 'onRequest'],
                Event::ON_HAND_SHAKE => [Hyperf\WebSocketServer\Server::class, 'onHandShake'],
                Event::ON_MESSAGE => [Hyperf\WebSocketServer\Server::class, 'onMessage'],
                Event::ON_CLOSE => [Hyperf\WebSocketServer\Server::class, 'onClose'],
            ],
        ],
    ],
];

```

Finally, we can add `HTTP` routing in `ws`.