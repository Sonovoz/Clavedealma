<?php

namespace App\Modules\Invoicing\Application;

use App\Modules\Invoicing\Contracts\AeatStatus;
use App\Modules\Invoicing\Contracts\VerifactuSender;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Throwable;

/**
 * Envía el registro de una factura a la AEAT por el driver configurado. Si falla,
 * queda «pendiente» y se reintenta con verifactu:enviar.
 */
class SendToAeat
{
    public function __construct(private readonly VerifactuSender $sender) {}

    public function __invoke(Invoice $invoice): void
    {
        try {
            $result = $this->sender->send([
                'issuer_nif' => $invoice->issuer['nif'] ?? null,
                'issuer_name' => $invoice->issuer['legal_name'] ?? null,
                'number' => $invoice->code,
                'issue_date' => $invoice->issueDate(),
                'type' => $invoice->type->value,
                'rectifies' => $invoice->rectifies?->code,
                'base_cents' => $invoice->base_cents,
                'vat_cents' => $invoice->vat_cents,
                'total_cents' => $invoice->total_cents,
                'hash' => $invoice->hash,
                'previous_hash' => $invoice->previous_hash,
                'generated_at' => $invoice->record_generated_at,
            ]);
            $invoice->update(['aeat_status' => $result['status'], 'aeat_error' => $result['error'], 'aeat_sent_at' => $result['status'] === AeatStatus::NotConfigured ? null : now()]);
        } catch (Throwable $e) {
            report($e);
            $invoice->update(['aeat_status' => AeatStatus::Pending, 'aeat_error' => mb_substr($e->getMessage(), 0, 1000)]);
        }
    }
}
