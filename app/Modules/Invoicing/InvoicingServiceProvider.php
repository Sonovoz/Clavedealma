<?php

namespace App\Modules\Invoicing;

use App\Modules\Invoicing\Contracts\VerifactuSender;
use App\Modules\Invoicing\Infrastructure\Console\IssuePendingInvoicesCommand;
use App\Modules\Invoicing\Infrastructure\Console\SendVerifactuCommand;
use App\Modules\Invoicing\Infrastructure\Listeners\InvoiceListeners;
use App\Modules\Invoicing\Infrastructure\Verifactu\NullVerifactuSender;
use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Payments\Contracts\Events\RefundIssued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Facturación: facturas simplificadas, rectificativas y registro Veri*Factu (ADR 0018).
 */
class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VerifactuSender::class, fn (): VerifactuSender => match (config('invoicing.verifactu.driver')) {
            'none', null, '' => new NullVerifactuSender,
            default => throw new RuntimeException('Driver de Veri*Factu no disponible todavía: ['.config('invoicing.verifactu.driver').'].'),
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(OrderPaid::class, [InvoiceListeners::class, 'paid']);
        Event::listen(RefundIssued::class, [InvoiceListeners::class, 'refunded']);

        if ($this->app->runningInConsole()) {
            $this->commands([IssuePendingInvoicesCommand::class, SendVerifactuCommand::class]);
        }
    }
}
