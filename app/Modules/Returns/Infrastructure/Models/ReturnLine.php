<?php

namespace App\Modules\Returns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property int $id
 * @property int $return_request_id
 * @property int $order_line_id
 * @property int $product_variant_id
 * @property string $sku
 * @property string $product_name
 * @property ?string $option
 * @property int $quantity
 * @property int $unit_price_cents
 */
class ReturnLine extends Model
{
    protected $table = 'return_request_lines';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Las piezas de una devolución no se modifican.'));
    }
}
