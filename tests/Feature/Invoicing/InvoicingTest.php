<?php

use App\Filament\Pages\InvoicingSettings;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Modules\Cart\Application\PriceCart;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Contracts\AeatStatus;
use App\Modules\Invoicing\Contracts\InvoiceType;
use App\Modules\Invoicing\Domain\VatSplit;
use App\Modules\Invoicing\Domain\Verifactu;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceIssuer;
use App\Modules\Orders\Application\CancelPaidOrder;
use App\Modules\Orders\Application\PlaceOrder;
use App\Modules\Orders\Contracts\CheckoutDetails;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\HandleGatewayEvent;
use App\Modules\Payments\Application\StartPayment;
use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Shipping\Contracts\ShippingQuote;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Documents\InvoiceDocument;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config()->set('payments.driver', 'fake');
    $store = Store::factory()->create(['name' => 'Luna Bruma', 'status' => StoreStatus::Published]);
    $product = Product::factory()->create(['store_id' => $store->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3630, 'vat_rate_bps' => 2100]);
    $this->variant = $product->variants()->create(['sku' => 'LB-01-M', 'size' => 'M']);
    Filament::setCurrentPanel('admin');
});

function datosFiscales(): void
{
    InvoiceIssuer::current()->update(['legal_name' => 'Ejemplo Tienda S.L.', 'nif' => 'B00000000', 'address' => 'Calle Prueba 1', 'postal_code' => '28001', 'city' => 'Madrid']);
}

/** Pedido real (no de prueba) cobrado: 2 × 36,30 € + 4,95 € de envío. */
function pedidoCobrado($test): Order
{
    $cart = app(PriceCart::class)([$test->variant->id => 2]);
    $order = app(PlaceOrder::class)($cart, new CheckoutDetails('ana@example.com', 'Ana Pérez', '600123456', 'Calle Mayor 1', null, '28013', 'Madrid', null, 'ES', null, 'v1'),
        new ShippingQuote(ShippingZone::Peninsula, 495, false, 1, 2, 'NACEX'), false);
    $payment = app(StartPayment::class)($order->id, (string) $order->reference, $order->total_cents, 'EUR', $order->email);
    app(HandleGatewayEvent::class)('fake', new GatewayEvent('evt-'.$order->id, GatewayEvent::SUCCEEDED, $payment->providerReference, $order->total_cents, 'EUR'));

    return $order->refresh();
}

it('la huella coincide con los ejemplos de la AEAT y se encadena', function () {
    $first = Verifactu::hash('89890001K', '12345678/G33', '01-01-2024', 'F1', 1235, 12345, null, '2024-01-01T19:20:30+01:00');
    expect($first)->toBe('3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60');
    expect(Verifactu::hash('89890001K', '12345679/G34', '01-01-2024', 'F1', 1235, 12345, $first, '2024-01-01T19:20:35+01:00'))
        ->toBe('F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97');

    expect(Verifactu::amount(-1050))->toBe('-10.50')
        ->and(Verifactu::qrUrl(false, 'B00000000', 'T2026-000001', '04-11-2026', 7755))
        ->toBe('https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B00000000&numserie=T2026-000001&fecha=04-11-2026&importe=77.55');
});

it('reparte un importe entre tipos de IVA cuadrando al céntimo', function () {
    expect(VatSplit::allocate(1000, [2100 => 7260, 1000 => 2740]))->toBe([2100 => 726, 1000 => 274])
        ->and(array_sum(VatSplit::allocate(333, [2100 => 1, 1000 => 1, 400 => 1])))->toBe(333)
        ->and(VatSplit::fromGross(-3630, 2100))->toBe(['base' => -3000, 'vat' => -630]);
});

it('sin datos fiscales no factura: queda pendiente y se emite después', function () {
    $order = pedidoCobrado($this);

    expect(Invoice::count())->toBe(0)
        ->and($order->events()->where('type', 'invoice_pending')->exists())->toBeTrue();

    $this->artisan('facturas:emitir-pendientes')->assertFailed();
    datosFiscales();
    $this->artisan('facturas:emitir-pendientes')->expectsOutputToContain('Facturas emitidas: 1')->assertSuccessful();
    expect(Invoice::sole()->order_id)->toBe($order->id);
});

it('al cobrar emite la factura simplificada con IVA, envío, numeración y registro Veri*Factu', function () {
    datosFiscales();
    $order = pedidoCobrado($this);
    $second = pedidoCobrado($this);

    $invoice = Invoice::query()->where('order_id', $order->id)->sole();
    $year = now()->timezone('Europe/Madrid')->format('Y');
    expect($invoice->code)->toBe("T{$year}-000001")
        ->and($invoice->type)->toBe(InvoiceType::Simplified)
        ->and($invoice->total_cents)->toBe($order->total_cents)
        ->and($invoice->vat_cents)->toBe($order->vat_cents)
        ->and($invoice->base_cents + $invoice->vat_cents)->toBe($invoice->total_cents)
        ->and($invoice->lines)->toHaveCount(2)
        ->and($invoice->lines->last()->description)->toBe('Envío')
        ->and($invoice->issuer['nif'])->toBe('B00000000')
        ->and($invoice->previous_hash)->toBeNull()
        ->and($invoice->hash)->toBe(Verifactu::hash('B00000000', $invoice->code, $invoice->issueDate(), 'F2', $invoice->vat_cents, $invoice->total_cents, null, $invoice->record_generated_at))
        ->and($invoice->verifactu)->toBeFalse() // todavía no es obligatorio
        ->and($invoice->aeat_status)->toBe(AeatStatus::Disabled);

    $next = Invoice::query()->where('order_id', $second->id)->sole();
    expect($next->code)->toBe("T{$year}-000002")->and($next->previous_hash)->toBe($invoice->hash);
});

it('al devolver dinero emite la rectificativa en negativo', function () {
    datosFiscales();
    $order = pedidoCobrado($this);

    app(CancelPaidOrder::class)($order, 'Fallo en la tela');

    $r = Invoice::query()->where('type', InvoiceType::RectifyingSimplified)->sole();
    $original = Invoice::query()->where('type', InvoiceType::Simplified)->sole();
    expect($r->code)->toStartWith('R')
        ->and($r->rectifies_id)->toBe($original->id)
        ->and($r->total_cents)->toBe(-$order->total_cents)
        ->and($r->vat_cents)->toBe(-$original->vat_cents)
        ->and($r->previous_hash)->toBe($original->hash);
});

it('las compras de prueba no se facturan y las facturas son inmutables', function () {
    datosFiscales();
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id]); // pago simulado = prueba
    expect(Invoice::count())->toBe(0);

    $invoice = Invoice::query()->where('order_id', pedidoCobrado($this)->id)->sole();
    expect(fn () => $invoice->update(['total_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $invoice->delete())->toThrow(LogicException::class);
});

it('el cliente descarga su factura; el panel la enseña solo al superadministrador', function () {
    datosFiscales();
    $order = pedidoCobrado($this);
    $invoice = Invoice::sole();

    $this->withoutVite()->get(route('checkout.thanks', $order->token))->assertSee($invoice->code);
    $this->get(route('checkout.invoice', [$order->token, $invoice->code]))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('checkout.invoice', [str_repeat('x', 48), $invoice->code]))->assertNotFound();

    $html = view('pdf.invoice', InvoiceDocument::data($invoice) + ['generatedAt' => now()])->render();
    // Veri*Factu desactivado: ni QR ni leyenda.
    expect($html)->toContain('Factura simplificada')->toContain('B00000000')->not->toContain('VERI*FACTU')->not->toContain('data:image/svg+xml;base64,');

    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])->assertOk()->assertSee($invoice->hash)
        ->callAction('invoicePdf')->assertFileDownloaded('factura-'.$invoice->code.'.pdf');
    $this->get('/admin/datos-de-facturacion')->assertOk()->assertSee('Ejemplo Tienda S.L.');

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get('/admin/facturas')->assertForbidden();
    $this->get('/admin/datos-de-facturacion')->assertForbidden();
});

it('con Veri*Factu activado las facturas nuevas llevan QR y leyenda y quedan para enviar', function () {
    config()->set('invoicing.verifactu.enabled', true);
    datosFiscales();
    $invoice = Invoice::query()->where('order_id', pedidoCobrado($this)->id)->sole();

    expect($invoice->verifactu)->toBeTrue()
        ->and($invoice->aeat_status)->toBe(AeatStatus::NotConfigured); // driver «none»: falta el certificado
    $html = view('pdf.invoice', InvoiceDocument::data($invoice) + ['generatedAt' => now()])->render();
    expect($html)->toContain('VERI*FACTU')->toContain('data:image/svg+xml;base64,');
});

it('los datos fiscales se editan en el panel', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    Livewire::test(InvoicingSettings::class)
        ->callAction('edit', ['legal_name' => 'Otra S.L.', 'nif' => 'b11111111', 'address' => 'C/ A 1', 'postal_code' => '41001', 'city' => 'Sevilla', 'simplified_series' => 'T', 'rectifying_series' => 'R'])
        ->assertHasNoActionErrors();

    expect(InvoiceIssuer::current()->nif)->toBe('B11111111')->and(InvoiceIssuer::current()->complete())->toBeTrue();
});
