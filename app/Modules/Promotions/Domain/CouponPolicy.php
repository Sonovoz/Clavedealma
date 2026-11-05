<?php

namespace App\Modules\Promotions\Domain;

use App\Modules\Promotions\Contracts\CouponType;
use App\Support\Money;
use DateTimeInterface;

/**
 * Reglas de un cupón, como las de WooCommerce: fechas, gasto mínimo, límites de uso,
 * tienda y artículos rebajados. Sin framework: se prueba aislada. Devuelve el motivo
 * por el que no vale, con el texto que ve el cliente, o null si vale.
 */
final class CouponPolicy
{
    public static function rejection(
        string $code,
        bool $active,
        ?DateTimeInterface $startsAt,
        ?DateTimeInterface $expiresAt,
        DateTimeInterface $now,
        ?int $minimumCents,
        int $cartCents,
        ?int $usageLimit,
        int $uses,
        ?int $perCustomerLimit,
        ?int $customerUses,
        int $eligibleCents,
    ): ?string {
        return match (true) {
            ! $active => "El cupón «{$code}» no existe.",
            $startsAt !== null && $now < $startsAt => "El cupón «{$code}» todavía no se puede usar.",
            $expiresAt !== null && $now > $expiresAt => 'Este cupón ha caducado.',
            $usageLimit !== null && $uses >= $usageLimit => 'Se ha alcanzado el límite de uso de este cupón.',
            $perCustomerLimit !== null && $customerUses !== null && $customerUses >= $perCustomerLimit => 'Ya has usado este cupón el máximo de veces permitido.',
            $minimumCents !== null && $cartCents < $minimumCents => 'El gasto mínimo para usar este cupón es de '.Money::fromCents($minimumCents)->format().'.',
            $eligibleCents <= 0 => 'Este cupón no es válido para los productos de tu carrito.',
            default => null,
        };
    }

    /** Descuento total del cupón sobre lo que admite. */
    public static function amount(CouponType $type, int $value, int $eligibleCents): int
    {
        return match ($type) {
            CouponType::Percent => DiscountAllocator::percent($eligibleCents, min($value, 10000)),
            CouponType::FixedCart => min($value, $eligibleCents),
        };
    }

    public static function label(CouponType $type, int $value, bool $freeShipping): string
    {
        $discount = match ($type) {
            CouponType::Percent => rtrim(rtrim(number_format($value / 100, 2, ',', ''), '0'), ',').' %',
            CouponType::FixedCart => Money::fromCents($value)->format(),
        };

        return $value === 0 && $freeShipping ? 'Envío gratis' : $discount.($freeShipping ? ' + envío gratis' : '');
    }
}
