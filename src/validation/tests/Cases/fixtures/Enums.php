<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Validation\Tests\Cases;

enum StringStatus: string
{
    case pending = 'pending';
    case done = 'done';
}

enum IntegerStatus: int
{
    case pending = 1;
    case done = 2;
}

enum PureEnum
{
    case one;
    case two;
}

enum ArrayKeys
{
    case key_1;
    case key_2;
    case key_3;
}

enum ArrayKeysBacked: string
{
    case key_1 = 'key_1';
    case key_2 = 'key_2';
    case key_3 = 'key_3';
}
