<?php

namespace App\Modules\Returns\Infrastructure\Models;

use App\Modules\Returns\Contracts\ReturnReason;
use App\Modules\Returns\Contracts\ReturnStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Devolución de piezas de un pedido. Las piezas y el importe no cambian; solo avanza el estado.
 *
 * @property int $id
 * @property ?string $reference
 * @property int $order_id
 * @property string $order_reference
 * @property ReturnStatus $status
 * @property ReturnReason $reason
 * @property ?string $comment
 * @property int $refund_cents
 * @property bool $includes_shipping
 * @property ?string $instructions
 * @property ?string $rejection_reason
 * @property bool $restocked
 * @property ?string $refund_error
 * @property ?Carbon $approved_at
 * @property ?Carbon $rejected_at
 * @property ?Carbon $received_at
 * @property ?Carbon $refunded_at
 * @property Carbon $created_at
 * @property-read Collection<int, ReturnLine> $lines
 */
class ReturnRequest extends Model
{
    private const MUTABLE = ['reference', 'status', 'instructions', 'rejection_reason', 'restocked', 'refund_error', 'approved_at', 'rejected_at', 'received_at', 'refunded_at', 'updated_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'includes_shipping' => 'boolean',
            'restocked' => 'boolean',
            'refund_cents' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'received_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (ReturnRequest $return): void {
            $changed = array_diff(array_keys($return->getDirty()), self::MUTABLE);
            if ($changed !== []) {
                throw new LogicException('Una devolución no se modifica: '.implode(', ', $changed).'.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Las devoluciones no se borran.'));
    }

    /**
     * @return HasMany<ReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReturnLine::class)->orderBy('id');
    }
}
