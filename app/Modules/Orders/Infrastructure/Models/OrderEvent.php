<?php

namespace App\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Historial del pedido (solo se añaden filas).
 *
 * @property int $id
 * @property int $order_id
 * @property string $type
 * @property ?string $note
 * @property Carbon $created_at
 */
class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('El historial no se modifica.'));
    }

    public function label(): string
    {
        return match ($this->type) {
            'created' => 'Pedido creado',
            'payment_started' => 'Pago iniciado',
            'payment_failed' => 'Pago rechazado',
            'paid' => 'Pago confirmado',
            'production_started' => 'Fabricación empezada',
            'ready' => 'Listo para enviar',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'refunded' => 'Importe devuelto',
            'refund_failed' => 'Devolución fallida',
            'cancelled' => 'Cancelado',
            'review' => 'A revisar',
            'email_sent' => 'Email enviado al cliente',
            'email_failed' => 'No se pudo enviar el email',
            default => $this->type,
        };
    }
}
