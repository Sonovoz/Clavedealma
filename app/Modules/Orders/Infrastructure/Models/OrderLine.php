<?php

namespace App\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Línea de pedido: copia de lo que se vendió (nombre, talla, color, precio, comisión)
 * en el momento de la compra. Inmutable.
 *
 * @property int $id
 * @property int $order_id
 * @property int $store_id
 * @property string $store_name
 * @property int $product_id
 * @property int $product_variant_id
 * @property string $sku
 * @property string $product_name
 * @property ?string $reference
 * @property ?string $size
 * @property ?string $color
 * @property int $unit_price_cents
 * @property int $discount_cents
 * @property int $quantity
 * @property int $total_cents
 * @property int $vat_rate_bps
 * @property int $commission_bps
 * @property int $weight_grams
 * @property bool $personalized
 * @property ?string $image_path
 */
class OrderLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['personalized' => 'boolean', 'discount_cents' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Las líneas de un pedido no se modifican.'));
        static::deleting(fn (): never => throw new LogicException('Las líneas de un pedido no se borran.'));
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function label(): string
    {
        return implode(' · ', array_filter([$this->size, $this->color]));
    }
}
