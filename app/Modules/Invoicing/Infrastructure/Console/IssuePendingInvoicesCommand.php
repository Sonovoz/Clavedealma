<?php

namespace App\Modules\Invoicing\Infrastructure\Console;

use App\Modules\Invoicing\Application\IssueInvoices;
use App\Modules\Invoicing\Contracts\IssuerIncomplete;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Payments\Contracts\Refunds;
use Illuminate\Console\Command;

class IssuePendingInvoicesCommand extends Command
{
    protected $signature = 'facturas:emitir-pendientes';

    protected $description = 'Emite las facturas y rectificativas que quedaron pendientes (p. ej. por faltar los datos fiscales).';

    public function handle(IssueInvoices $issue, OrderLookup $orders, Refunds $refunds): int
    {
        $count = 0;
        try {
            $invoiced = Invoice::query()->whereNull('refund_id')->pluck('order_id')->flip();
            foreach ($orders->paidOrderIds() as $orderId) {
                if (! $invoiced->has($orderId) && $issue->forOrder($orderId) !== null) {
                    $count++;
                }
            }
            $done = Invoice::query()->whereNotNull('refund_id')->pluck('refund_id')->flip();
            foreach ($refunds->succeeded() as $r) {
                if (! $done->has($r['id']) && $issue->forRefund($r['id'], $r['order_id'], $r['amount_cents'], $r['reason']) !== null) {
                    $count++;
                }
            }
        } catch (IssuerIncomplete $e) {
            $this->error($e->getMessage().' Rellénalos en el panel: Ajustes → Datos de facturación.');

            return self::FAILURE;
        }

        $this->info($count === 0 ? 'No hay facturas pendientes.' : "Facturas emitidas: {$count}.");

        return self::SUCCESS;
    }
}
