<?php

namespace App\Platform\Minimedia;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Cierre mensual de la comisión de Minimedia (ADR 0029). Las cifras no cambian una vez
 * cerrado: solo se apunta el envío del email.
 *
 * @property int $id
 * @property string $month
 * @property int $rate_bps
 * @property int $orders
 * @property int $sales_base_cents
 * @property int $refunds_base_cents
 * @property int $net_base_cents
 * @property int $commission_cents
 * @property list<array{store_id: int, store: string, orders: int, sales_base: int, refunds_base: int, net_base: int, commission: int}> $stores
 * @property ?string $sent_to
 * @property ?Carbon $sent_at
 * @property ?string $send_error
 * @property Carbon $created_at
 */
class MinimediaStatement extends Model
{
    private const SENDING = ['sent_to', 'sent_at', 'send_error', 'updated_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer', 'orders' => 'integer', 'sales_base_cents' => 'integer', 'refunds_base_cents' => 'integer',
            'net_base_cents' => 'integer', 'commission_cents' => 'integer', 'stores' => 'array', 'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $statement): void {
            if (array_diff(array_keys($statement->getDirty()), self::SENDING) !== []) {
                throw new LogicException('Un cierre de Minimedia no se modifica.');
            }
        });
        static::deleting(fn () => throw new LogicException('Un cierre de Minimedia no se borra.'));
    }

    public function label(): string
    {
        return MinimediaCommission::label($this->month);
    }
}
