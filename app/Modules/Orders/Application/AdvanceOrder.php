<?php

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Contracts\Events\OrderShipped;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Domain\OrderFlow;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Contracts\CarrierDirectory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pasos del pedido pagado: fabricación → listo → enviado → entregado. Cada paso deja
 * constancia (quién y cuándo) en el historial. No se puede saltar ni volver atrás.
 */
class AdvanceOrder
{
    public function __construct(private readonly CarrierDirectory $carriers) {}

    public function startProduction(Order $order, ?string $by = null): Order
    {
        return $this->move($order, OrderStatus::InProduction, ['production_started_at' => now()], 'production_started', $by);
    }

    public function markReady(Order $order, ?string $by = null): Order
    {
        return $this->move($order, OrderStatus::ReadyToShip, ['ready_at' => now()], 'ready', $by);
    }

    public function ship(Order $order, string $carrier, string $trackingNumber, ?string $trackingUrl = null, ?string $by = null): Order
    {
        $carrier = trim($carrier);
        $trackingNumber = trim($trackingNumber);
        if ($carrier === '' || $trackingNumber === '') {
            throw new InvalidArgumentException('Indica el transportista y el número de seguimiento.');
        }
        $url = trim((string) $trackingUrl) !== '' ? trim((string) $trackingUrl) : $this->carriers->trackingUrl($carrier, $trackingNumber);
        if ($url !== null && ! preg_match('#^https?://#i', $url)) {
            throw new InvalidArgumentException('El enlace de seguimiento debe empezar por https://');
        }

        $order = $this->move($order, OrderStatus::Shipped, [
            'shipped_at' => now(),
            'tracking_carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $url,
        ], 'shipped', $by, $carrier.' · '.$trackingNumber);

        OrderShipped::dispatch($order->id);

        return $order;
    }

    public function markDelivered(Order $order, ?string $by = null): Order
    {
        return $this->move($order, OrderStatus::Delivered, ['delivered_at' => now()], 'delivered', $by);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function move(Order $order, OrderStatus $to, array $changes, string $event, ?string $by, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $changes, $event, $by, $note): Order {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! OrderFlow::can($fresh->status, $to)) {
                throw new InvalidArgumentException("El pedido está «{$fresh->status->label()}» y no puede pasar a «{$to->label()}».");
            }

            $fresh->update(['status' => $to] + $changes);
            $fresh->log($event, implode(' · ', array_filter([$note, $by === null ? null : 'por '.$by])) ?: null);

            return $fresh;
        });
    }
}
