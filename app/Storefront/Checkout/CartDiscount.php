<?php

namespace App\Storefront\Checkout;

use App\Modules\Cart\Application\CartStore;
use App\Modules\Cart\Contracts\PricedCart;
use App\Modules\Orders\Contracts\OrderDiscount;
use App\Modules\Promotions\Contracts\CouponLine;
use App\Modules\Promotions\Contracts\CouponResult;
use App\Modules\Promotions\Contracts\Coupons;
use App\Modules\Shipping\Contracts\ShippingQuote;
use App\Support\Money;

/**
 * El cupón del carrito (ADR 0024), calculado siempre en el servidor con los precios del
 * momento: lo usan el carrito, el precio del envío y «Finalizar compra». Si el cupón ha
 * dejado de valer (el carrito cambió, caducó…), se quita y se dice por qué.
 */
final class CartDiscount
{
    public function __construct(private readonly CartStore $cart, private readonly Coupons $coupons) {}

    public function apply(string $code, PricedCart $priced, ?string $email = null): CouponResult
    {
        return $this->coupons->evaluate($code, $this->lines($priced), $email);
    }

    /** El cupón guardado, si sigue valiendo; si no, lo quita y devuelve el motivo. */
    public function current(PricedCart $priced, ?string $email = null): ?CouponResult
    {
        $code = $this->cart->coupon();
        if ($code === null) {
            return null;
        }

        $result = $this->apply($code, $priced, $email);
        if (! $result->ok()) {
            $this->cart->setCoupon(null);
        }

        return $result;
    }

    public static function forOrder(?CouponResult $result): ?OrderDiscount
    {
        return $result !== null && $result->ok() ? new OrderDiscount($result->code, $result->discounts) : null;
    }

    public static function shipping(ShippingQuote $quote, ?CouponResult $result): ShippingQuote
    {
        return $result !== null && $result->ok() && $result->freeShipping ? $quote->withFreeShipping() : $quote;
    }

    /**
     * Fila «Cupón: CÓDIGO −X €» de los totales.
     *
     * @return array{code: string, label: string, amount: string, cents: int, free_shipping: bool}|null
     */
    public static function row(?CouponResult $result): ?array
    {
        if ($result === null || ! $result->ok()) {
            return null;
        }

        return [
            'code' => $result->code,
            'label' => $result->label,
            'amount' => '−'.Money::fromCents($result->discountCents())->format(),
            'cents' => $result->discountCents(),
            'free_shipping' => $result->freeShipping,
        ];
    }

    /**
     * @return list<CouponLine>
     */
    private function lines(PricedCart $priced): array
    {
        return array_map(fn ($l): CouponLine => new CouponLine($l->variant->variantId, $l->variant->storeId, $l->totalCents(), $l->variant->onPromotion), $priced->buyableLines());
    }
}
