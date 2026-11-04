<?php

namespace App\Modules\Shipping\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tramo del baremo: hasta X gramos cuesta Y (céntimos, IVA incluido).
 *
 * @property int $id
 * @property int $shipping_zone_rate_id
 * @property int $max_weight_grams
 * @property int $price_cents
 */
class RateBand extends Model
{
    protected $table = 'shipping_rate_bands';

    protected $guarded = ['id'];

    protected $attributes = ['position' => 0];

    protected function casts(): array
    {
        return ['max_weight_grams' => 'integer', 'price_cents' => 'integer', 'position' => 'integer'];
    }
}
