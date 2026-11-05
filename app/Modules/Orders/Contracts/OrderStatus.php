<?php

namespace App\Modules\Orders\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * Estados del pedido. Solo se avanza por los pasos de OrderFlow (Domain).
 *
 * En el panel se nombran como en WooCommerce: «Procesando» es un pedido pagado que
 * aún no ha salido (por fabricar, en fabricación o listo) y «Completado», el entregado.
 */
enum OrderStatus: string implements HasLabel
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case InProduction = 'in_production';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::Paid => 'Procesando · por fabricar',
            self::InProduction => 'Procesando · en fabricación',
            self::ReadyToShip => 'Procesando · listo para enviar',
            self::Shipped => 'Enviado',
            self::Delivered => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    /** El nombre corto de WooCommerce, el que ve el cliente en «Mi cuenta → Pedidos». */
    public function wooLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::Paid, self::InProduction, self::ReadyToShip => 'Procesando',
            self::Shipped => 'Enviado',
            self::Delivered => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Pagado y todavía sin enviar: lo que WooCommerce llama «Procesando». */
    public function isProcessing(): bool
    {
        return in_array($this, [self::Paid, self::InProduction, self::ReadyToShip], true);
    }

    /**
     * @return list<self>
     */
    public static function processing(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s): bool => $s->isProcessing()));
    }

    /** Cómo lo lee el cliente en la página de su pedido. */
    public function customerLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Esperando el pago',
            self::Paid => 'Pago recibido',
            self::InProduction => 'Fabricando tus piezas',
            self::ReadyToShip => 'Preparando el envío',
            self::Shipped => 'En camino',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            // Colores de WooCommerce: pendiente gris, procesando verde, completado azul.
            self::PendingPayment => 'gray',
            self::Paid => 'success',
            self::InProduction => 'success',
            self::ReadyToShip => 'success',
            self::Shipped => 'primary',
            self::Delivered => 'info',
            self::Cancelled => 'gray',
        };
    }

    /** El cobro está hecho (y no se ha cancelado). */
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::InProduction, self::ReadyToShip, self::Shipped, self::Delivered], true);
    }

    /**
     * @return list<self>
     */
    public static function paid(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s): bool => $s->isPaid()));
    }
}
