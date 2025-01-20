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

class ModelWithPrimitiveCasts extends Model
{
    public array $fillable = ['id'];

    public array $casts = [
        'backed_enum' => CastableBackedEnum::class,
        'address' => Address::class,
    ];

    public array $attributes = [
        'address_line_one' => null,
        'address_line_two' => null,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->mergeCasts(self::makePrimitiveCastsArray());
    }

    public static function makePrimitiveCastsArray(): array
    {
        $toReturn = [];

        foreach (static::$primitiveCastTypes as $index => $primitiveCastType) {
            $toReturn['primitive_cast_' . $index] = $primitiveCastType;
        }

        return $toReturn;
    }

    public function getThisIsFineAttribute($value)
    {
        return 'ok';
    }

    public function thisIsAlsoFine(): Attribute
    {
        return Attribute::get(fn () => 'ok');
    }
}
