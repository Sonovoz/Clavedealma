<?php

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    config()->set('payments.driver', 'fake');
    $store = Store::factory()->create(['name' => 'Luna Bruma', 'status' => StoreStatus::Published]);
    $product = Product::factory()->create(['store_id' => $store->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3000]);
    $variant = $product->variants()->create(['sku' => 'LB-01-M', 'size' => 'M']);
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);

    $this->postJson(route('cart.add'), ['variant_id' => $variant->id]);
    $this->post(route('checkout.place'), [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1',
    ]);
    $this->order = Order::query()->sole();
    $this->post(route('checkout.simulate', $this->order->token), ['outcome' => 'ok']);
    Filament::setCurrentPanel('admin');
});

it('el superadministrador ve los pedidos y su detalle, sin poder editarlos', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    Livewire::test(ListOrders::class)->assertOk()->assertCanSeeTableRecords([$this->order])->assertSee('Ana Pérez');
    Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertOk()
        ->assertSee('La esencia')->assertSee('Luna Bruma')->assertSee('35,00 €')->assertSee('Pago confirmado')->assertSee('Compra de prueba');

    $this->get('/admin/pedidos/'.$this->order->id.'/edit')->assertNotFound();
});

it('el gestor de catálogo no ve pedidos ni datos de clientes', function () {
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());

    $this->get('/admin/pedidos')->assertForbidden();
    $this->get('/admin/pedidos/'.$this->order->id)->assertForbidden();
});
