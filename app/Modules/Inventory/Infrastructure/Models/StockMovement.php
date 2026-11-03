<?php

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Inventory\Contracts\StockMovementReason;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Movimiento de stock. Inmutable: no se edita ni se borra.
 *
 * @property int $delta
 * @property int $quantity_after
 * @property StockMovementReason $reason
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reason' => StockMovementReason::class, 'delta' => 'integer', 'quantity_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de stock no se modifican.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de stock no se borran.'));
    }
}
