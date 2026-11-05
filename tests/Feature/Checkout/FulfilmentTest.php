<?php

use App\Filament\Pages\Production;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Modules\Orders\Application\AdvanceOrder;
use App\Modules\Orders\Application\CancelPaidOrder;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Mail\OrderCancelledMail;
use App\Modules\Orders\Infrastructure\Mail\OrderShippedMail;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\RefundResult;
use App\Modules\Payments\Contracts\Refunds;
use App\Modules\Payments\Infrastructure\Drivers\FakeGateway;
use App\Modules\Payments\Infrastructure\Models\Refund;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->withoutVite();
    config()->set('payments.driver', 'fake');

    $store = Store::factory()->create(['name' => 'Luna Bruma', 'status' => StoreStatus::Published]);
    $product = Product::factory()->create(['store_id' => $store->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3000]);
    $this->variant = $product->variants()->create(['sku' => 'LB-01-M', 'size' => 'M', 'color' => 'Negro']);
    ProductStock::query()->updateOrCreate(['product_id' => $this->variant->product_id], ['managed' => true, 'quantity' => 2]);

    Carrier::where('name', 'NACEX')->update(['tracking_url' => 'https://seguimiento.example/?n={codigo}']);
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::where('name', 'NACEX')->value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);

    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 2]);
    $this->post(route('checkout.place'), [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1',
    ]);
    $this->order = Order::query()->sole();
    $this->post(route('checkout.simulate', $this->order->token), ['outcome' => 'ok']);
    $this->order->refresh();
    Filament::setCurrentPanel('admin');
});

it('avanza por los pasos, avisa al cliente al enviar y no deja saltarse ninguno', function () {
    $advance = app(AdvanceOrder::class);

    expect(fn () => $advance->markReady($this->order))->toThrow(InvalidArgumentException::class);

    $advance->startProduction($this->order, 'Taller');
    $advance->markReady($this->order->refresh());
    expect(fn () => $advance->ship($this->order->refresh(), 'NACEX', ''))->toThrow(InvalidArgumentException::class);
    $advance->ship($this->order->refresh(), 'NACEX', 'AB 123');

    $order = $this->order->refresh();
    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($order->tracking_url)->toBe('https://seguimiento.example/?n=AB%20123')
        ->and($order->events()->where('type', 'production_started')->value('note'))->toBe('por Taller');
    Mail::assertSent(OrderShippedMail::class, fn ($m) => $m->hasTo('ana@example.com'));

    $this->get(route('checkout.thanks', $order->token))->assertOk()
        ->assertSee('En camino')->assertSee('AB 123')->assertSee('https://seguimiento.example/?n=AB%20123', false);

    $advance->markDelivered($order);
    expect($order->refresh()->status)->toBe(OrderStatus::Delivered)
        ->and(fn () => app(CancelPaidOrder::class)($order, 'tarde'))->toThrow(InvalidArgumentException::class);
});

it('cancelar un pedido pagado devuelve el importe, repone el stock y avisa al cliente', function () {
    // Las 2 piezas salieron de las 2 unidades del producto.
    expect(ProductStock::where('product_id', $this->variant->product_id)->value('quantity'))->toBe(0);

    app(CancelPaidOrder::class)($this->order, 'Fallo en la tela', 'Admin');

    $order = $this->order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and(app(Refunds::class)->refundedCents($order->id))->toBe(6500)
        ->and(ProductStock::where('product_id', $this->variant->product_id)->value('quantity'))->toBe(2);
    Mail::assertSent(OrderCancelledMail::class, fn ($m) => $m->hasTo('ana@example.com') && $m->reason === 'Fallo en la tela');

    $this->get(route('checkout.thanks', $order->token))->assertSee('te hemos devuelto 65,00 €');

    // Repetir no devuelve dos veces.
    expect(app(Refunds::class)->refund($order->id, 6500, 'cda-cancel-'.$order->id, 'otra')->succeeded)->toBeTrue()
        ->and(Refund::count())->toBe(1);
});

it('si la devolución falla, el pedido no se cancela', function () {
    $failing = new class extends FakeGateway
    {
        public function refund(string $providerReference, int $amountCents, string $idempotencyKey): RefundResult
        {
            return new RefundResult(false, null, 'Tarjeta caducada');
        }
    };
    app()->instance(PaymentGateway::class, $failing);

    expect(fn () => app(CancelPaidOrder::class)($this->order, 'motivo'))->toThrow(InvalidArgumentException::class, 'Tarjeta caducada');
    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Refund::sole()->status)->toBe(Refund::FAILED);
});

it('el fabricante ve qué fabricar sin datos del cliente y marca los pasos', function () {
    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create(['name' => 'Taller Sur']));

    $this->get('/admin/fabricacion')->assertOk()->assertSee($this->order->reference)->assertSee('LB-01-M')
        ->assertDontSee('Ana Pérez')->assertDontSee('ana@example.com')->assertDontSee('Calle Mayor');
    $this->get('/admin/pedidos')->assertForbidden();

    Livewire::test(Production::class)
        ->callAction(TestAction::make('startProduction')->table($this->order))
        ->assertHasNoActionErrors();
    expect($this->order->refresh()->status)->toBe(OrderStatus::InProduction);

    Livewire::test(Production::class)->callAction(TestAction::make('markReady')->table($this->order));
    expect($this->order->refresh()->status)->toBe(OrderStatus::ReadyToShip)
        ->and($this->order->events()->where('type', 'ready')->value('note'))->toBe('por Taller Sur');
});

it('el gestor de catálogo no entra en Fabricación', function () {
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());

    $this->get('/admin/fabricacion')->assertForbidden();
});

it('el superadministrador envía y cancela desde la ficha del pedido', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    app(AdvanceOrder::class)->startProduction($this->order);
    app(AdvanceOrder::class)->markReady($this->order->refresh());

    Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
        ->assertActionVisible('ship')->assertActionHidden('startProduction')
        ->callAction('ship', ['carrier' => 'NACEX', 'tracking_number' => 'XY9'])
        ->assertHasNoActionErrors();
    expect($this->order->refresh()->status)->toBe(OrderStatus::Shipped)
        ->and($this->order->tracking_number)->toBe('XY9');

    Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])->assertActionHidden('cancelAndRefund');
});
