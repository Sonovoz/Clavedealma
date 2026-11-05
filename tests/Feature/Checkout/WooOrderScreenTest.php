<?php

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Support\ActiveStore;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Collection;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Application\ConfigureStock;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Orders\Infrastructure\Mail\NewOrderAlert;
use App\Modules\Orders\Infrastructure\Mail\OrderConfirmation;
use App\Modules\Orders\Infrastructure\Mail\OrderNoteMail;
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
    config()->set('clavedealma.superadmin.email', 'equipo@clavedealma.test');
    $this->store = Store::factory()->create(['name' => 'Luna Bruma', 'status' => StoreStatus::Published]);
    $this->product = Product::factory()->create(['store_id' => $this->store->id, 'name' => 'La esencia', 'reference' => 'LB-01', 'status' => ProductStatus::Published, 'price_cents' => 3000]);
    $variant = $this->product->variants()->create(['sku' => 'LB-01-M', 'size' => 'M']);
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);

    $this->postJson(route('cart.add'), ['variant_id' => $variant->id]);
    $this->post(route('checkout.place'), [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1', 'simulate' => 'ok',
    ]);
    $this->order = Order::query()->sole();
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create(['name' => 'Equipo Uno']));
});

it('«Añadir nota»: la privada solo la ve el equipo; la del cliente le llega y la ve en su pedido', function () {
    Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->callAction('addNote', ['text' => 'Llamar antes de enviar.', 'kind' => 'private'])->assertHasNoActionErrors()
        ->callAction('addNote', ['text' => 'Tu camiseta ya está en el taller.', 'kind' => 'customer'])->assertHasNoActionErrors()
        ->assertSee('Notas del pedido')->assertSee('Llamar antes de enviar.')->assertSee('por Equipo Uno')->assertSee('para el cliente');

    expect($this->order->events()->where('type', 'note')->sole()->author)->toBe('Equipo Uno');
    Mail::assertSent(OrderNoteMail::class, fn (OrderNoteMail $m): bool => $m->hasTo('ana@example.com') && $m->text === 'Tu camiseta ya está en el taller.');
    Mail::assertSent(OrderNoteMail::class, 1);

    $this->get(route('checkout.thanks', $this->order->token))->assertOk()
        ->assertSee('Actualizaciones del pedido')->assertSee('Tu camiseta ya está en el taller.')
        ->assertDontSee('Llamar antes de enviar.');
});

it('«Acciones del pedido»: reenviar la confirmación al cliente y el aviso al equipo', function () {
    Mail::fake();

    Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertSee('Acciones del pedido')
        ->callAction('resendCustomer')->assertHasNoActionErrors()
        ->callAction('resendTeam')->assertHasNoActionErrors();

    Mail::assertSent(OrderConfirmation::class, fn ($m): bool => $m->hasTo('ana@example.com'));
    Mail::assertSent(NewOrderAlert::class, fn ($m): bool => $m->hasTo('equipo@clavedealma.test'));
    expect($this->order->events()->where('type', 'email_sent')->where('author', 'Equipo Uno')->count())->toBe(2);
});

it('el listado de productos tiene las columnas y filtros de WooCommerce', function () {
    ActiveStore::set($this->store->id);
    $gira = Collection::create(['store_id' => $this->store->id, 'name' => 'Gira', 'slug' => 'gira', 'position' => 1]);
    $this->product->collections()->attach($gira->id, ['position' => 1]);
    $sold = Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Gorra agotada', 'status' => ProductStatus::Published]);
    app(ConfigureStock::class)($sold->id, StockStatus::OutOfStock, false);

    Livewire::test(ListProducts::class)
        ->assertSee('SKU')->assertSee('LB-01')->assertSee('Categorías')->assertSee('Gira')->assertSee('Inventario')
        ->filterTable('stock', 'out')->assertCanSeeTableRecords([$sold])->assertCanNotSeeTableRecords([$this->product])
        ->resetTableFilters()
        ->filterTable('collections', $gira->id)->assertCanSeeTableRecords([$this->product])->assertCanNotSeeTableRecords([$sold]);
});
