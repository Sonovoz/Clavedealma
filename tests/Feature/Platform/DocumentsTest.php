<?php

use App\Filament\Pages\Production;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Application\AdvanceOrder;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Documents\PackingSlip;
use App\Platform\Documents\ProductionSheet;
use App\Platform\Documents\SalesReport;
use App\Platform\Documents\ShippingReport;
use App\Platform\Pdf\PdfRenderer;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config()->set('payments.driver', 'fake');
    $store = Store::factory()->create(['name' => 'Luna Bruma', 'status' => StoreStatus::Published, 'commission_bps' => 2500]);
    $product = Product::factory()->create(['store_id' => $store->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3630, 'vat_rate_bps' => 2100]);
    $variant = $product->variants()->create(['sku' => 'LB-01-M', 'size' => 'M', 'color' => 'Negro']);
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);

    $this->postJson(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2]);
    $this->post(route('checkout.place'), [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1',
    ]);
    $this->order = Order::query()->sole();
    $this->post(route('checkout.simulate', $this->order->token), ['outcome' => 'ok']);
    $this->order->refresh();
    Filament::setCurrentPanel('admin');
});

function html(string $view, array $data): string
{
    return view($view, $data + ['generatedAt' => now()])->render();
}

it('la orden de fabricación lista las piezas y nunca datos del cliente', function () {
    $html = html('pdf.production', ProductionSheet::data());

    expect($html)->toContain($this->order->reference)->toContain('LB-01-M')->toContain('M · Negro')->toContain('Debe estar listo')
        ->not->toContain('Ana Pérez')->not->toContain('ana@example.com')->not->toContain('Calle Mayor')->not->toContain('600123456');
});

it('el albarán lleva la dirección y las piezas, sin precios', function () {
    $html = html('pdf.packing-slip', PackingSlip::data($this->order));

    expect($html)->toContain('Ana Pérez')->toContain('Calle Mayor 1')->toContain('28013 Madrid')->toContain('LB-01-M')
        ->not->toContain('€');
});

it('el informe de ventas suma, desglosa por tienda y excluye las pruebas salvo que se pidan', function () {
    $today = CarbonImmutable::now('Europe/Madrid');

    $without = SalesReport::data($today->startOfMonth(), $today);
    expect($without['orders'])->toBe(0); // la compra con pago simulado es de prueba

    $with = SalesReport::data($today->startOfMonth(), $today, includeTests: true);
    expect($with['orders'])->toBe(1)
        ->and($with['units'])->toBe(2)
        ->and($with['gross'])->toBe('77,60 €')
        ->and($with['shipping'])->toBe('5,00 €')
        ->and($with['stores'][0]['store'])->toBe('Luna Bruma')
        ->and($with['stores'][0]['base'])->toBe('60,00 €')
        ->and($with['stores'][0]['commission'])->toBe('15,00 €');

    expect(html('pdf.sales', $with))->toContain('Informe de ventas')->toContain('Luna Bruma')->toContain('15,00 €');
});

it('el estado de envíos separa lo listo y lo que va en camino', function () {
    $advance = app(AdvanceOrder::class);
    $advance->startProduction($this->order);
    $advance->markReady($this->order->refresh());
    expect(ShippingReport::data()['ready'])->toHaveCount(1);

    $advance->ship($this->order->refresh(), Carrier::value('name'), 'TRK9');
    $data = ShippingReport::data();
    expect($data['ready'])->toBeEmpty()->and($data['shipped'][0]['tracking'])->toBe('TRK9');
    expect(html('pdf.shipping', $data))->toContain('TRK9');
});

it('genera PDF de verdad', function () {
    $pdf = app(PdfRenderer::class)->render('pdf.production', ProductionSheet::data() + ['generatedAt' => now()]);

    expect(substr($pdf, 0, 5))->toBe('%PDF-');
});

it('se descargan desde el panel según el rol', function () {
    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create());
    Livewire::test(Production::class)->callAction(TestAction::make('productionSheetPdf')->table())->assertFileDownloaded();
    Livewire::test(Production::class)->callAction(TestAction::make('productionOrderPdf')->table($this->order))
        ->assertFileDownloaded('orden-fabricacion-'.$this->order->reference.'.pdf');
    $this->get('/admin/informes')->assertForbidden();

    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->callAction('packingSlipPdf')->assertFileDownloaded('albaran-'.$this->order->reference.'.pdf');
    Livewire::test(ListOrders::class)->callAction('shippingReportPdf')->assertFileDownloaded();
    $this->get('/admin/informes')->assertOk()->assertSee('Informes');
    Livewire::test(Reports::class)
        ->callAction('salesReportPdf', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(), 'tests' => true])
        ->assertHasNoActionErrors()->assertFileDownloaded();
});
