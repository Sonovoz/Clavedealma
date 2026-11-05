<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    $this->withoutVite();
    config()->set('payments.driver', 'fake');

    $store = Store::factory()->create(['subdomain' => 'luna', 'name' => 'Luna Bruma', 'status' => StoreStatus::Published]);
    $garment = BaseGarment::factory()->create(['store_id' => $store->id, 'weight_grams' => 250]);
    $product = Product::factory()->create(['store_id' => $store->id, 'base_garment_id' => $garment->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3500, 'vat_rate_bps' => 2100]);
    $this->variant = $product->variants()->create(['sku' => 'LB-01-M-NEGRO', 'size' => 'M', 'color' => 'Negro']);

    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::where('name', 'NACEX')->value('id'), 'active' => true, 'free_from_cents' => 10000]);
    $rate->bands()->create(['max_weight_grams' => 1000, 'price_cents' => 495]);

    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 1])->assertOk();
});

function datosFinalizar(array $over = []): array
{
    return array_merge([
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456',
        'address_line1' => 'Calle Mayor 1', 'postal_code' => '28013', 'city' => 'Madrid',
        'country' => 'ES', 'terms' => '1', 'note' => 'Dejar en portería',
    ], $over);
}

it('«Finalizar compra» reúne datos, «Tu pedido» y pago en una página', function () {
    $this->get('/pedido')->assertRedirect('/finalizar-compra');

    $this->get(route('checkout.show'))->assertOk()
        ->assertSee('Finalizar compra')->assertSee('Detalles de facturación')->assertSee('Información adicional')
        ->assertSee('Notas del pedido (opcional)')->assertSee('Tu pedido')->assertSee('La esencia')
        ->assertSee('× 1')->assertSee('Simular pago correcto')->assertSee('Realizar el pedido');
});

it('«Realizar el pedido» con pago simulado lleva a «Pedido recibido»', function () {
    $this->post(route('checkout.place'), datosFinalizar(['simulate' => 'ok']))->assertRedirect();

    $order = Order::query()->sole();
    expect($order->status)->toBe(OrderStatus::Paid);

    $this->get(route('checkout.thanks', $order->token))->assertOk()
        ->assertSee('Pedido recibido')->assertSee('Tu pedido ha sido recibido')
        ->assertSee('Número del pedido')->assertSee($order->reference)->assertSee('Método de pago')
        ->assertSee('Pago simulado')->assertSee('Dirección de envío')->assertSee('Dejar en portería');
});

it('si el pago se rechaza, reintentar con los mismos datos reutiliza el pedido', function () {
    $this->post(route('checkout.place'), datosFinalizar(['simulate' => 'ko']))
        ->assertRedirect(route('checkout.show'))->assertSessionHas('payment.error');
    $this->get(route('checkout.show'))->assertSee('El pago se ha rechazado');

    $this->post(route('checkout.place'), datosFinalizar(['simulate' => 'ok']))->assertRedirect();

    expect(Order::query()->count())->toBe(1)
        ->and(Order::query()->sole()->status)->toBe(OrderStatus::Paid);
});

it('si cambian los datos antes de pagar, anula el pedido anterior y su cobro', function () {
    $this->post(route('checkout.place'), datosFinalizar(['simulate' => 'ko']));
    $first = Order::query()->sole();

    $this->post(route('checkout.place'), datosFinalizar(['simulate' => 'ok', 'address_line1' => 'Calle Luna 7']))->assertRedirect();

    $second = Order::query()->latest('id')->first();
    expect($second->id)->not->toBe($first->id)
        ->and($second->status)->toBe(OrderStatus::Paid)
        ->and($first->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(Payment::where('order_id', $first->id)->value('status'))->not->toBe(PaymentStatus::Succeeded);
});

it('con JavaScript devuelve el cobro para pagar sin salir de la página', function () {
    $this->postJson(route('checkout.place'), datosFinalizar(['postal_code' => '1']))
        ->assertStatus(422)->assertJsonValidationErrors(['postal_code']);
    expect(Order::query()->count())->toBe(0);

    $this->postJson(route('checkout.place'), datosFinalizar())->assertOk()
        ->assertJson(['ok' => true, 'amount_cents' => 3995])
        ->assertJsonStructure(['client_secret', 'return_url', 'pay_url', 'reference']);

    // Pulsar otra vez «Realizar el pedido» (p. ej., tarjeta rechazada) no crea otro pedido.
    $this->postJson(route('checkout.place'), datosFinalizar())->assertOk();
    expect(Order::query()->count())->toBe(1);
});

it('una compra nueva después de pagar crea otro pedido', function () {
    $this->post(route('checkout.place'), datosFinalizar(['simulate' => 'ok']));
    $paid = Order::query()->sole();
    $this->get(route('checkout.thanks', $paid->token));

    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 1])->assertOk();
    $this->post(route('checkout.place'), datosFinalizar(['simulate' => 'ok']))->assertRedirect();

    expect(Order::query()->count())->toBe(2);
});
