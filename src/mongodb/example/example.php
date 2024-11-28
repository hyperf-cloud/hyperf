<?php

require __DIR__ . '/../vendor/autoload.php';

$result = ExtensionGoods::query()
    ->select('*')
    ->limit(1)
    ->get()
    ->toArray();
var_dump($result);