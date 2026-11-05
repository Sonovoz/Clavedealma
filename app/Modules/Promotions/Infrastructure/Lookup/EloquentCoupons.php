<?php

namespace App\Modules\Promotions\Infrastructure\Lookup;

use App\Modules\Promotions\Contracts\CouponLine;
use App\Modules\Promotions\Contracts\CouponResult;
use App\Modules\Promotions\Contracts\Coupons;
use App\Modules\Promotions\Domain\CouponPolicy;
use App\Modules\Promotions\Domain\DiscountAllocator;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use Carbon\CarbonImmutable;

class EloquentCoupons implements Coupons
{
    public function evaluate(string $code, array $lines, ?string $email = null): CouponResult
    {
        $code = Coupon::normalize($code);
        if ($code === '') {
            return CouponResult::invalid($code, 'Escribe un código de cupón.');
        }

        $coupon = Coupon::query()->where('code', $code)->first();
        if ($coupon === null) {
            return CouponResult::invalid($code, "El cupón «{$code}» no existe.");
        }

        // Admite las líneas de su tienda (o todas) y, si se excluyen, no las rebajadas.
        $eligible = [];
        foreach ($lines as $line) {
            if (($coupon->store_id === null || $coupon->store_id === $line->storeId) && ! ($coupon->exclude_sale_items && $line->onPromotion)) {
                $eligible[$line->key] = $line->totalCents;
            }
        }

        $tz = 'Europe/Madrid';
        $error = CouponPolicy::rejection(
            code: $code,
            active: $coupon->active,
            startsAt: $coupon->starts_at,
            expiresAt: $coupon->expires_on === null ? null : CarbonImmutable::parse($coupon->expires_on->format('Y-m-d'), $tz)->endOfDay(),
            now: CarbonImmutable::now($tz),
            minimumCents: $coupon->minimum_cents,
            cartCents: array_sum(array_map(fn (CouponLine $l): int => $l->totalCents, $lines)),
            usageLimit: $coupon->usage_limit,
            uses: $coupon->usage_limit === null ? 0 : $coupon->uses(),
            perCustomerLimit: $coupon->usage_limit_per_customer,
            customerUses: $email === null || $coupon->usage_limit_per_customer === null ? null : $coupon->uses($email),
            // Un cupón solo de envío gratis vale aunque no descuente ningún artículo.
            eligibleCents: $coupon->amount === 0 && $coupon->free_shipping ? 1 : array_sum($eligible),
        );
        if ($error !== null) {
            return CouponResult::invalid($code, $error);
        }

        $amount = CouponPolicy::amount($coupon->type, $coupon->amount, array_sum($eligible));

        return new CouponResult(
            code: $code,
            discounts: DiscountAllocator::allocate($amount, $eligible),
            freeShipping: $coupon->free_shipping,
            label: $coupon->label(),
        );
    }
}
