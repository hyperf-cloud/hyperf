#!/usr/bin/env sh

SW_VER=$(php -r "echo SWOOLE_VERSION_ID;")

if [ $SW_VER -lt 50000 ]; then
  composer require hyperf/engine:^2.0
fi

composer update -oW

