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

namespace HyperfTest\Database\Stubs;

use Hyperf\Contract\Castable;
use Hyperf\Contract\CastsAttributes;

class Address implements Castable
{
    public static function castUsing(): CastsAttributes
    {
        return new class implements CastsAttributes {
            public function get($model, string $key, mixed $value, array $attributes): Address
            {
                return new Address(
                    $attributes['address_line_one'],
                    $attributes['address_line_two']
                );
            }

            public function set($model, string $key, mixed $value, array $attributes): array
            {
                return [
                    'address_line_one' => $value->lineOne ?? null,
                    'address_line_two' => $value->lineTwo ?? null,
                ];
            }
        };
    }
}
