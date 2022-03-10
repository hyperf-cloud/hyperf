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
namespace HyperfTest\Database\Stubs\Model;

/**
 * @property int $id
 * @property string $bit
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserBit extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'user_bit';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['id', 'bit', 'created_at', 'updated_at'];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = ['id' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
