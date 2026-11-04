<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Inventory\Infrastructure\Models\StockMovement;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Mail\NewOrderAlert;
use App\Modules\Orders\Infrastructure\Mail\OrderConfirmation;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    $this->withoutVite();
    config()->set('payments.driver', 'fake');
    config()->set('clavedealma.superadmin.email', 'equipo@clavedealma.test');

    $this->store = Store::factory()->create(['subdomain' => 'luna', 'name' => 'Luna Bruma', 'status' => StoreStatus::Published, 'commission_bps' => 2500]);
    $garment = BaseGarment::factory()->create(['store_id' => $this->store->id, 'name' => 'Camiseta oversize', 'weight_grams' => 250]);
    $this->product = Product::factory()->create(['store_id' => $this->store->id, 'base_garment_id' => $garment->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3500, 'vat_rate_bps' => 2100]);
    $this->variant = $this->product->variants()->create(['sku' => 'LB-01-M-NEGRO', 'size' => 'M', 'color' => 'Negro']);

    // Envío a la Península: 4,95 € hasta 1 kg, gratis desde 100 €.
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::where('name', 'NACEX')->value('id'), 'active' => true, 'free_from_cents' => 10000, 'min_days' => 1, 'max_days' => 2]);
    $rate->bands()->create(['max_weight_grams' => 1000, 'price_cents' => 495]);
});

function datosEnvio(array $over = []): array
{
    return array_merge([
        'email' => 'Ana@Example.com', 'name' => 'Ana Pérez', 'phone' => '600 123 456',
        'address_line1' => 'Calle Mayor 1, 2º B', 'postal_code' => '28013', 'city' => 'Madrid',
        'province' => 'Madrid', 'country' => 'ES', 'terms' => '1',
    ], $over);
}

function comprar($test, ProductVariant $variant, int $qty = 1, array $over = []): Order
{
    $test->postJson(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => $qty])->assertOk();
    $test->post(route('checkout.place'), datosEnvio($over))->assertRedirect();

    return Order::query()->latest('id')->firstOrFail();
}

it('añade a la cesta con el precio del servidor y la muestra', function () {
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 2])
        ->assertOk()->assertJson(['ok' => true, 'count' => 2]);

    $this->get(route('cart.show'))->assertOk()
        ->assertSee('La esencia')->assertSee('M · Negro')->assertSee('Luna Bruma')
        ->assertSee('70,00 €')->assertSee('noindex', false);
});

it('no deja comprar borradores, tiendas ocultas (salvo al equipo) ni variantes agotadas', function () {
    $this->product->update(['status' => ProductStatus::Draft]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id])->assertStatus(422);

    $this->product->update(['status' => ProductStatus::Published]);
    $this->store->update(['status' => StoreStatus::Hidden]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id])->assertStatus(422);
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())
        ->postJson(route('cart.add'), ['variant_id' => $this->variant->id])->assertOk();

    $this->store->update(['status' => StoreStatus::Published]);
    StockLevel::where('product_variant_id', $this->variant->id)->update(['mode' => StockMode::LimitedThenSoldOut, 'quantity' => 0]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id])->assertStatus(422)->assertJson(['message' => 'Agotado.']);
});

it('calcula el envío por destino y no vende aún a Canarias', function () {
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id]);

    $this->getJson(route('checkout.quote', ['country' => 'ES', 'postal_code' => '28013']))
        ->assertJson(['ok' => true, 'shipping' => '4,95 €', 'total' => '39,95 €', 'zone' => 'Península']);
    $this->getJson(route('checkout.quote', ['country' => 'ES', 'postal_code' => '35001']))
        ->assertJson(['ok' => false, 'message' => 'Todavía no enviamos a Canarias.']);
    $this->getJson(route('checkout.quote', ['country' => 'FR', 'postal_code' => '75001']))
        ->assertJson(['ok' => false]);

    $this->post(route('checkout.place'), datosEnvio(['postal_code' => '35001', 'city' => 'Las Palmas']))
        ->assertSessionHasErrors('postal_code');
    expect(Order::count())->toBe(0);
});

it('compra completa con pago simulado: pedido pagado, stock descontado, emails y cesta vacía', function () {
    StockLevel::where('product_variant_id', $this->variant->id)->update(['mode' => StockMode::LimitedThenSoldOut, 'quantity' => 5]);

    $order = comprar($this, $this->variant, 2);
    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->reference)->toMatch('/^CDA-\d{2}-\d{6}$/')
        ->and($order->email)->toBe('ana@example.com')
        ->and($order->subtotal_cents)->toBe(7000)
        ->and($order->shipping_cents)->toBe(495)
        ->and($order->total_cents)->toBe(7495)
        ->and($order->vat_cents)->toBe(1301) // 1215 de las prendas + 86 del envío
        ->and($order->weight_grams)->toBe(500)
        ->and($order->is_test)->toBeTrue();

    $line = $order->lines()->sole();
    expect($line->sku)->toBe('LB-01-M-NEGRO')->and($line->unit_price_cents)->toBe(3500)
        ->and($line->commission_bps)->toBe(2500)->and($line->store_name)->toBe('Luna Bruma');

    $this->get(route('checkout.pay', $order->token))->assertOk()->assertSee('Simular pago correcto')->assertSee('74,95 €');
    $this->post(route('checkout.simulate', $order->token), ['outcome' => 'ok'])->assertRedirect(route('checkout.thanks', $order->token));

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)->and($order->paid_at)->not->toBeNull()
        ->and(StockLevel::where('product_variant_id', $this->variant->id)->value('quantity'))->toBe(3)
        ->and(StockMovement::where('note', 'Pedido '.$order->reference)->value('delta'))->toBe(-2)
        ->and(Payment::where('order_id', $order->id)->sole()->status)->toBe(PaymentStatus::Succeeded);

    Mail::assertSent(OrderConfirmation::class, fn ($m) => $m->hasTo('ana@example.com'));
    Mail::assertSent(NewOrderAlert::class, fn ($m) => $m->hasTo('equipo@clavedealma.test'));

    $this->get(route('checkout.thanks', $order->token))->assertOk()->assertSee('Gracias, Ana')->assertSee($order->reference);
    $this->get(route('cart.show'))->assertSee('Tu carrito está vacío');

    // Un segundo aviso del mismo cobro no descuenta otra vez.
    $this->post(route('checkout.simulate', $order->token), ['outcome' => 'ok'])->assertStatus(409);
    expect(StockLevel::where('product_variant_id', $this->variant->id)->value('quantity'))->toBe(3);
});

it('un pago rechazado deja el pedido esperando y se puede reintentar', function () {
    $order = comprar($this, $this->variant);

    $this->post(route('checkout.simulate', $order->token), ['outcome' => 'ko'])->assertRedirect(route('checkout.pay', $order->token));
    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->events()->pluck('type')->all())->toContain('payment_failed');

    $this->post(route('checkout.simulate', $order->token), ['outcome' => 'ok']);
    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Payment::where('order_id', $order->id)->count())->toBe(1);
});

it('envío gratis desde el importe del panel', function () {
    $order = comprar($this, $this->variant, 3); // 105 € ≥ 100 €
    expect($order->shipping_cents)->toBe(0)->and($order->shipping_free)->toBeTrue()->and($order->total_cents)->toBe(10500);
});

it('si se agota mientras se paga, el segundo pedido queda «a revisar»', function () {
    StockLevel::where('product_variant_id', $this->variant->id)->update(['mode' => StockMode::LimitedThenSoldOut, 'quantity' => 1]);

    $first = comprar($this, $this->variant);
    session()->flush();
    $second = comprar($this, $this->variant);

    $this->post(route('checkout.simulate', $first->token), ['outcome' => 'ok']);
    $this->post(route('checkout.simulate', $second->token), ['outcome' => 'ok']);

    expect($first->refresh()->review_reason)->toBeNull()
        ->and($second->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($second->review_reason)->toContain('se agotó')
        ->and(StockLevel::where('product_variant_id', $this->variant->id)->value('quantity'))->toBe(0);
});

it('los pedidos son inmutables', function () {
    $order = comprar($this, $this->variant);

    expect(fn () => $order->update(['total_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $order->lines()->sole()->update(['quantity' => 9]))->toThrow(LogicException::class)
        ->and(fn () => $order->delete())->toThrow(LogicException::class);
});

it('cancela los pedidos sin pagar pasado el tiempo y anula su cobro', function () {
    $order = comprar($this, $this->variant);
    $this->get(route('checkout.pay', $order->token));

    $this->travel(3)->hours();
    $this->artisan('pedidos:cancelar-caducados')->expectsOutputToContain('Pedidos cancelados: 1')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Payment::where('order_id', $order->id)->sole()->status)->toBe(PaymentStatus::Cancelled);
    $this->get(route('checkout.pay', $order->token))->assertRedirect(route('checkout.thanks', $order->token));
    $this->get(route('checkout.thanks', $order->token))->assertSee('Pedido cancelado');
});

it('el pago simulado no existe en producción', function () {
    app()->detectEnvironment(fn () => 'production');
    app()->forgetInstance(PaymentGateway::class);

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});

it('la ficha tiene el botón de compra y la cabecera cuenta las piezas', function () {
    $this->get(Urls::product($this->store, $this->product->slug))
        ->assertOk()->assertSee('data-add-form', false)->assertSee(route('cart.add'), false);

    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 2]);
    $this->get(Urls::home($this->store))->assertSee('Carrito (<span data-cart-count>2</span>)', false);
});
