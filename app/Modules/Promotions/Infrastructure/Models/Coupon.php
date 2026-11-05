<?php

namespace App\Modules\Promotions\Infrastructure\Models;

use App\Modules\Promotions\Contracts\CouponType;
use App\Modules\Promotions\Domain\CouponPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cupón de descuento (Marketing → Cupones, ADR 0024). El código se guarda en mayúsculas
 * y se compara sin distinguir mayúsculas.
 *
 * @property int $id
 * @property string $code
 * @property ?string $description
 * @property CouponType $type
 * @property int $amount
 * @property bool $free_shipping
 * @property ?Carbon $starts_at
 * @property ?Carbon $expires_on
 * @property ?int $minimum_cents
 * @property ?int $store_id
 * @property bool $exclude_sale_items
 * @property ?int $usage_limit
 * @property ?int $usage_limit_per_customer
 * @property bool $active
 */
class Coupon extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['type' => 'percent', 'amount' => 0, 'free_shipping' => false, 'exclude_sale_items' => false, 'active' => true];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class, 'amount' => 'integer', 'free_shipping' => 'boolean', 'starts_at' => 'datetime',
            'expires_on' => 'date', 'minimum_cents' => 'integer', 'store_id' => 'integer', 'exclude_sale_items' => 'boolean',
            'usage_limit' => 'integer', 'usage_limit_per_customer' => 'integer', 'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon): void {
            $coupon->code = self::normalize($coupon->code);
        });
    }

    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Usos que cuentan: pedidos pagados que no se han cancelado. */
    public function uses(?string $email = null): int
    {
        return $this->redemptions()->whereNull('released_at')
            ->when($email !== null, fn ($q) => $q->where('email', mb_strtolower(trim((string) $email))))
            ->count();
    }

    public function label(): string
    {
        return CouponPolicy::label($this->type, $this->amount, $this->free_shipping);
    }
}
