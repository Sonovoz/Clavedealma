<?php

namespace App\Modules\Invoicing\Infrastructure\Listeners;

use App\Modules\Invoicing\Application\IssueInvoices;
use App\Modules\Invoicing\Contracts\IssuerIncomplete;
use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Payments\Contracts\Events\RefundIssued;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Factura al cobrar y rectificativa al devolver dinero. Si falla (por ejemplo, faltan los
 * datos fiscales), el cobro no se deshace: queda pendiente y se emite con facturas:emitir-pendientes.
 */
class InvoiceListeners
{
    public function __construct(private readonly IssueInvoices $issue, private readonly OrderLookup $orders) {}

    public function paid(OrderPaid $event): void
    {
        $this->safely($event->orderId, fn () => $this->issue->forOrder($event->orderId));
    }

    public function refunded(RefundIssued $event): void
    {
        $this->safely($event->orderId, fn () => $this->issue->forRefund($event->refundId, $event->orderId, $event->amountCents, $event->reason));
    }

    private function safely(int $orderId, callable $do): void
    {
        try {
            $do();
        } catch (IssuerIncomplete $e) {
            $this->orders->note($orderId, 'invoice_pending', $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            Log::error('No se pudo emitir la factura del pedido #'.$orderId.': '.$e->getMessage());
            $this->orders->note($orderId, 'invoice_pending', 'Error al facturar: '.$e->getMessage());
        }
    }
}
