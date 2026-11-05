<?php

namespace App\Modules\Orders\Application;

use App\Modules\Cart\Contracts\PricedCart;
use App\Modules\Orders\Contracts\CheckoutDetails;
use App\Modules\Orders\Contracts\OrderDiscount;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Domain\OrderTotals;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Contracts\ShippingQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Crea el pedido «pendiente de pago» con la cesta ya calculada en el servidor y el
 * envío presupuestado. Copia en cada línea lo vendido (precio, IVA, comisión, peso).
 */
class PlaceOrder
{
    /** IVA del envío: el mismo tipo general que las prendas. */
    public const SHIPPING_VAT_BPS = 2100;

    public function __invoke(PricedCart $cart, CheckoutDetails $details, ShippingQuote $shipping, bool $test = false, ?OrderDiscount $discount = null): Order
    {
        if ($cart->isEmpty() || $cart->hasProblems()) {
            throw new InvalidArgumentException('El carrito ha cambiado: revísalo antes de pagar.');
        }

        $lines = [];
        foreach ($cart->buyableLines() as $line) {
            $v = $line->variant;
            // Cupón (ADR 0024): el total de la línea ya lleva su parte del descuento.
            $off = min($discount?->forVariant($v->variantId) ?? 0, $line->totalCents());
            $lines[] = [
                'store_id' => $v->storeId,
                'store_name' => $line->store->name,
                'product_id' => $v->productId,
                'product_variant_id' => $v->variantId,
                'sku' => $v->sku,
                'product_name' => $v->productName,
                'reference' => $v->reference,
                'size' => $v->size,
                'color' => $v->color,
                'unit_price_cents' => $v->unitPriceCents,
                'discount_cents' => $off,
                'quantity' => $line->quantity,
                'total_cents' => $line->totalCents() - $off,
                'vat_rate_bps' => $v->vatRateBps,
                'commission_bps' => $v->commissionBps ?? $line->store->commissionBps,
                'weight_grams' => ($v->weightGrams ?? $cart->defaultWeightGrams) * $line->quantity,
                'personalized' => $v->personalized,
                'image_path' => $v->imagePath,
            ];
        }

        $totals = OrderTotals::compute($lines, $shipping->priceCents, self::SHIPPING_VAT_BPS);
        $discountCents = array_sum(array_column($lines, 'discount_cents'));

        return DB::transaction(function () use ($cart, $details, $shipping, $test, $lines, $totals, $discount, $discountCents): Order {
            $order = Order::query()->create([
                'token' => Str::random(48),
                'status' => OrderStatus::PendingPayment,
                'is_test' => $test,
                'user_id' => $details->customerId,
                'email' => mb_strtolower(trim($details->email)),
                'customer_name' => $details->name,
                'phone' => $details->phone,
                'address_line1' => $details->addressLine1,
                'address_line2' => $details->addressLine2,
                'postal_code' => $details->postalCode,
                'city' => $details->city,
                'province' => $details->province,
                'country' => strtoupper($details->country),
                'customer_note' => $details->note,
                'shipping_zone' => $shipping->zone,
                'shipping_carrier' => $shipping->carrier,
                'shipping_cents' => $shipping->priceCents,
                'shipping_free' => $shipping->free,
                'delivery_min_days' => $shipping->minDays,
                'delivery_max_days' => $shipping->maxDays,
                'lead_time_min_days' => $cart->leadTimeMinDays(),
                'lead_time_max_days' => $cart->leadTimeMaxDays(),
                'weight_grams' => $cart->weightGrams(),
                'terms_version' => $details->termsVersion,
                'terms_accepted_at' => now(),
                'coupon_code' => $discountCents > 0 || $discount !== null ? $discount?->code : null,
                'discount_cents' => $discountCents,
            ] + $totals);

            $order->update(['reference' => OrderTotals::reference($order->id, (int) now()->format('Y'))]);
            $order->lines()->createMany($lines);
            $order->log('created', $test ? 'Compra de prueba del equipo.' : null);

            return $order;
        });
    }
}
