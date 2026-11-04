<?php

namespace App\Modules\Invoicing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $vat_rate_bps
 * @property int $base_cents
 * @property int $vat_cents
 * @property int $total_cents
 */
class InvoiceLine extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Las líneas de una factura no se modifican.'));
    }
}
