<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentRequest;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Contracts\PaymentUnavailable;
use App\Modules\Payments\Contracts\StartedPayment;
use App\Modules\Payments\Infrastructure\Drivers\StripeGateway;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\WebhookEvent;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config()->set('payments.driver', 'stripe');
    config()->set('payments.stripe', ['key' => 'pk_test_x', 'secret' => 'sk_test_x', 'webhook_secret' => 'whsec_prueba']);
    app()->forgetInstance(PaymentGateway::class);

    $store = Store::factory()->create(['status' => StoreStatus::Published]);
    $product = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Published, 'price_cents' => 2000]);
    $variant = $product->variants()->create(['sku' => 'W-1', 'size' => 'M']);
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);

    $this->postJson(route('cart.add'), ['variant_id' => $variant->id]);
    $this->post(route('checkout.place'), [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1',
    ]);
    $this->order = Order::query()->sole();
    // El cobro que habría creado Stripe al abrir la página de pago.
    Payment::create(['order_id' => $this->order->id, 'provider' => 'stripe', 'provider_reference' => 'pi_prueba', 'amount_cents' => 2500, 'currency' => 'EUR', 'status' => PaymentStatus::Pending]);
});

function avisoStripe(string $type, int $amount, string $id = 'evt_1'): array
{
    $payload = json_encode([
        'id' => $id, 'object' => 'event', 'type' => $type, 'api_version' => '2026-09-30.endive', 'created' => time(),
        'data' => ['object' => [
            'id' => 'pi_prueba', 'object' => 'payment_intent', 'amount' => $amount, 'amount_received' => $type === 'payment_intent.succeeded' ? $amount : 0,
            'currency' => 'eur', 'status' => $type === 'payment_intent.succeeded' ? 'succeeded' : 'requires_payment_method',
            'last_payment_error' => $type === 'payment_intent.succeeded' ? null : ['message' => 'Tarjeta rechazada', 'type' => 'card_error'],
        ]],
    ]);
    $t = time();

    return [$payload, "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_prueba')];
}

function enviarAviso($test, array $aviso)
{
    return $test->call('POST', route('payments.webhook.stripe'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => $aviso[1], 'CONTENT_TYPE' => 'application/json'], $aviso[0]);
}

it('el webhook firmado marca el pedido como pagado una sola vez', function () {
    $aviso = avisoStripe('payment_intent.succeeded', 2500);

    enviarAviso($this, $aviso)->assertOk();
    enviarAviso($this, $aviso)->assertOk(); // repetido: se ignora

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->order->review_reason)->toBeNull()
        ->and(WebhookEvent::count())->toBe(1)
        ->and($this->order->events()->where('type', 'paid')->count())->toBe(1);
});

it('rechaza avisos sin firma válida', function () {
    [$payload] = avisoStripe('payment_intent.succeeded', 2500);

    enviarAviso($this, [$payload, 't=1,v1=falsa'])->assertStatus(400);
    expect($this->order->refresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('un cobro por otro importe queda pagado pero «a revisar»', function () {
    enviarAviso($this, avisoStripe('payment_intent.succeeded', 100))->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->order->review_reason)->toContain('no coincide');
});

it('un pago fallido queda anotado y el pedido sigue esperando', function () {
    enviarAviso($this, avisoStripe('payment_intent.payment_failed', 2500, 'evt_2'))->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Failed)
        ->and(Payment::sole()->failure_message)->toBe('Tarjeta rechazada');
});

it('la página de pago con Stripe carga Stripe.js y no ofrece el pago simulado', function () {
    $this->withoutVite();
    // Sin llamar a Stripe en las pruebas: el cobro lo «crea» un doble del driver.
    app()->instance(PaymentGateway::class, new class('sk_test_x', 'pk_test_x', 'whsec_prueba') extends StripeGateway
    {
        public function start(PaymentRequest $request): StartedPayment
        {
            return new StartedPayment('pi_prueba', 'pi_prueba_secret_abc');
        }
    });

    $this->get(route('checkout.pay', $this->order->token))->assertOk()
        ->assertSee('https://js.stripe.com/v3/', false)
        ->assertSee('data-client-secret="pi_prueba_secret_abc"', false)
        ->assertSee('data-stripe-key="pk_test_x"', false)
        ->assertDontSee('Simular pago');
    $this->post(route('checkout.simulate', $this->order->token), ['outcome' => 'ok'])->assertNotFound();
});

it('si Stripe no responde, el pedido se guarda y se puede reintentar', function () {
    $this->withoutVite();
    app()->instance(PaymentGateway::class, new class('sk_test_x', 'pk_test_x', 'whsec_prueba') extends StripeGateway
    {
        public function start(PaymentRequest $request): StartedPayment
        {
            throw new PaymentUnavailable('sin conexión');
        }
    });

    $this->get(route('checkout.pay', $this->order->token))->assertOk()->assertSee('Reintentar');
    expect($this->order->refresh()->status)->toBe(OrderStatus::PendingPayment);
});
