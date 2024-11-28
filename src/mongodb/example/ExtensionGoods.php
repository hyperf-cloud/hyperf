<?php

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class ExtensionGoods extends Eloquent
{
    protected $connection = 'default';

    protected $collection = 'extension_goods';
}