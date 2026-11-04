<?php

namespace App\Modules\Invoicing\Infrastructure\Models;

use App\Modules\Invoicing\Contracts\AeatStatus;
use App\Modules\Invoicing\Contracts\InvoiceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Factura emitida. Inmutable: solo cambia el estado del envío a la AEAT. Un error se
 * corrige con una factura rectificativa, nunca editando ni borrando.
 *
 * @property int $id
 * @property string $series
 * @property int $year
 * @property int $number
 * @property string $code
 * @property InvoiceType $type
 * @property int $order_id
 * @property string $order_reference
 * @property ?int $rectifies_id
 * @property ?int $refund_id
 * @property ?string $reason
 * @property Carbon $issued_at
 * @property array<string, ?string> $issuer
 * @property ?string $customer_name
 * @property ?string $customer_email
 * @property int $base_cents
 * @property int $vat_cents
 * @property int $total_cents
 * @property string $currency
 * @property bool $verifactu
 * @property string $hash
 * @property ?string $previous_hash
 * @property string $record_generated_at
 * @property string $qr_url
 * @property AeatStatus $aeat_status
 * @property ?string $aeat_error
 * @property ?Carbon $aeat_sent_at
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read ?Invoice $rectifies
 */
class Invoice extends Model
{
    private const MUTABLE = ['aeat_status', 'aeat_error', 'aeat_sent_at', 'updated_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'aeat_status' => AeatStatus::class,
            'issuer' => 'array',
            'issued_at' => 'datetime',
            'aeat_sent_at' => 'datetime',
            'base_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'verifactu' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $changed = array_diff(array_keys($invoice->getDirty()), self::MUTABLE);
            if ($changed !== []) {
                throw new LogicException('Una factura no se modifica: se corrige con una rectificativa.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Las facturas no se borran.'));
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function rectifies(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rectifies_id');
    }

    /** Fecha de expedición en el formato de Veri*Factu (dd-mm-aaaa, hora de Madrid). */
    public function issueDate(): string
    {
        return $this->issued_at->copy()->timezone('Europe/Madrid')->format('d-m-Y');
    }
}
