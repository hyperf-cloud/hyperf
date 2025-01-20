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

use Hyperf\Database\Model\Casts\Attribute;
use Hyperf\Database\Model\Model;

class ModelWithMutators extends Model
{
    public array $attributes = [
        'first_name' => null,
        'last_name' => null,
        'address_line_one' => null,
        'address_line_two' => null,
    ];

    public function setFullAddressAttribute($fullAddress)
    {
        [$addressLineOne, $addressLineTwo] = explode(', ', $fullAddress);

        $this->attributes['address_line_one'] = $addressLineOne;
        $this->attributes['address_line_two'] = $addressLineTwo;
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            set: function (string $fullName) {
                [$firstName, $lastName] = explode(' ', $fullName);

                return [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ];
            }
        );
    }
}
