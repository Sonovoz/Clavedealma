<?php

namespace App\Platform\Documents;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use App\Storefront\Checkout\Countries;

/**
 * Albarán de envío: va dentro del paquete. Dirección y piezas, sin precios.
 */
final class PackingSlip
{
    /**
     * @return array<string, mixed>
     */
    public static function data(Order $order): array
    {
        $order->loadMissing('lines');

        return [
            'order' => $order,
            'date' => ($order->shipped_at ?? now())->timezone('Europe/Madrid')->format('d/m/Y'),
            'country' => Countries::ALL[$order->country] ?? $order->country,
            'lines' => $order->lines->map(fn (OrderLine $l): array => [
                'sku' => $l->sku, 'name' => $l->product_name, 'option' => $l->label(), 'store' => $l->store_name,
                'quantity' => $l->quantity, 'personalized' => $l->personalized,
            ])->all(),
            'units' => $order->itemCount(),
            'orderUrl' => route('checkout.thanks', $order->token),
        ];
    }
}
