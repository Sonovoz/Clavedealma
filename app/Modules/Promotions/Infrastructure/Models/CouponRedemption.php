<?php

namespace App\Modules\Promotions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uso de un cupón en un pedido pagado. Si el pedido se cancela, se libera (released_at)
 * y deja de contar para los límites; nunca se borra.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $order_id
 * @property string $email
 * @property int $discount_cents
 * @property ?Carbon $released_at
 */
class CouponRedemption extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['discount_cents' => 'integer', 'released_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
