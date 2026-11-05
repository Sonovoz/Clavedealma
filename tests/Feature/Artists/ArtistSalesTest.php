<?php

use App\Filament\ArtistWidgets\ArtistKpis;
use App\Filament\ArtistWidgets\ArtistTopProducts;
use App\Filament\Pages\ArtistSalesDashboard;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Artists\ArtistSales;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** Pedido pagado con una línea, sin pasar por la compra (los de prueba no cuentan). */
function venta(Store $store, string $product, int $units, int $totalCents, string $paidAt, bool $test = false, OrderStatus $status = OrderStatus::Paid): Order
{
    $order = Order::query()->create([
        'token' => Str::random(48), 'status' => $status, 'is_test' => $test, 'email' => 'cliente@example.com',
        'customer_name' => 'Cliente Secreto', 'phone' => '600000000', 'address_line1' => 'Calle 1', 'postal_code' => '28001',
        'city' => 'Madrid', 'country' => 'ES', 'shipping_zone' => 'peninsula', 'shipping_carrier' => 'NACEX', 'shipping_cents' => 0,
        'delivery_min_days' => 1, 'delivery_max_days' => 2, 'lead_time_min_days' => 5, 'lead_time_max_days' => 7, 'weight_grams' => 300,
        'subtotal_cents' => $totalCents, 'total_cents' => $totalCents, 'vat_cents' => 0, 'terms_version' => 'v1', 'terms_accepted_at' => now(),
        'paid_at' => $paidAt,
    ]);
    $order->lines()->create([
        'store_id' => $store->id, 'store_name' => $store->name, 'product_id' => crc32($product) % 100000, 'product_variant_id' => 1,
        'sku' => Str::upper(Str::slug($product)), 'product_name' => $product, 'unit_price_cents' => intdiv($totalCents, $units),
        'quantity' => $units, 'total_cents' => $totalCents, 'vat_rate_bps' => 2100, 'commission_bps' => 2500, 'weight_grams' => 300, 'personalized' => false,
    ]);

    return $order;
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
    $this->user = User::factory()->withRole(Role::Artist)->create(['name' => 'Luna']);
    $this->artist = Artist::factory()->create(['display_name' => 'Luna Bruma', 'user_id' => $this->user->id]);
    $this->store = Store::factory()->create(['artist_id' => $this->artist->id, 'name' => 'Luna Bruma']);
    $other = Store::factory()->create(['artist_id' => Artist::factory()->create()->id, 'name' => 'Otra']);

    venta($this->store, 'La esencia', 2, 12100, '2026-11-19 10:00:00');          // 100 € sin IVA → 25 € de comisión
    venta($this->store, 'Gorra gira', 1, 6050, '2026-11-15 18:00:00');           // 50 € sin IVA
    venta($this->store, 'La esencia', 1, 6050, '2026-10-10 10:00:00');           // periodo anterior
    venta($this->store, 'La esencia', 5, 30250, '2026-11-18 10:00:00', test: true); // prueba: no cuenta
    venta($this->store, 'La esencia', 1, 6050, '2026-11-17 10:00:00', status: OrderStatus::Cancelled);
    venta($other, 'Ajena', 9, 54450, '2026-11-19 10:00:00');                     // de otra tienda
});

it('calcula unidades, importe sin IVA, comisión y la comparación con el periodo anterior', function () {
    $sales = ArtistSales::for([$this->store->id], '30d');

    expect($sales->totals())->toBe(['units' => 3, 'base' => 15000, 'commission' => 3750, 'orders' => 2])
        ->and($sales->previousTotals()['base'])->toBe(5000)
        ->and(ArtistSales::change(15000, 5000))->toBe(200)
        ->and(count($sales->daily()))->toBe(30)
        ->and(array_column($sales->byProduct(), 'name'))->toBe(['La esencia', 'Gorra gira'])
        ->and($sales->byProduct()[0]['share'])->toBe(67);
});

it('el artista entra directo a «Mis ventas» y solo ve lo suyo, sin datos de clientes', function () {
    $this->actingAs($this->user);

    $this->get('/admin')->assertRedirect(ArtistSalesDashboard::getUrl());
    $this->get(ArtistSalesDashboard::getUrl())->assertOk()
        ->assertSee('Mis ventas')->assertSee('Hola, Luna Bruma')
        ->assertDontSee('cliente@example.com')->assertDontSee('Cliente Secreto');

    Livewire::test(ArtistKpis::class, ['pageFilters' => ['period' => '30d']])
        ->assertSee('Unidades vendidas')->assertSee('150,00 €')->assertSee('37,50 €')->assertSee('+200 %');
    Livewire::test(ArtistTopProducts::class, ['pageFilters' => ['period' => '30d']])
        ->assertSee('La esencia')->assertSee('Gorra gira')->assertDontSee('Ajena');
});

it('el artista no ve nada más del panel', function () {
    $this->actingAs($this->user);

    foreach (['/admin/pedidos', '/admin/clientes', '/admin/productos', '/admin/informes', '/admin/fabricacion'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('el superadministrador lo ve como cualquier artista; el resto del equipo, no', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    $this->get(ArtistSalesDashboard::getUrl())->assertOk()->assertSee('Ventas por artista');

    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create());
    $this->get(ArtistSalesDashboard::getUrl())->assertForbidden();
});

it('un artista sin enlazar ve un aviso y ninguna venta', function () {
    $this->actingAs(User::factory()->withRole(Role::Artist)->create());

    $this->get(ArtistSalesDashboard::getUrl())->assertOk()->assertSee('aún no está enlazado');
    Livewire::test(ArtistTopProducts::class, ['pageFilters' => ['period' => '30d']])->assertSee('Aún no hay ventas');
});
