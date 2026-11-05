<?php

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Orders\Application\CancelPaidOrder;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\InvalidWebhook;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\Refunds;
use App\Modules\Payments\Infrastructure\Drivers\Redsys\RedsysSignature;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\Refund;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

// Datos públicos del entorno de pruebas de Redsys (no son de Clave de Alma).
const REDSYS_KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

/**
 * Respuesta firmada como la manda Redsys (Base64 «URL-safe»), calculada aquí con la
 * librería de cifrado directamente, sin pasar por RedsysSignature.
 *
 * @param  array<string, string>  $params
 * @return array<string, string>
 */
function redsysReply(array $params, string $key = REDSYS_KEY): array
{
    $encoded = strtr(base64_encode((string) json_encode($params)), '+/', '-_');

    return [
        'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
        'Ds_MerchantParameters' => $encoded,
        'Ds_Signature' => strtr(base64_encode(redsysMac($encoded, $params['Ds_Order'], $key)), '+/', '-_'),
    ];
}

/** HMAC-SHA256 con la clave diversificada por pedido (3DES), como la librería de Redsys. */
function redsysMac(string $encoded, string $order, string $key = REDSYS_KEY): string
{
    $length = (int) ceil(strlen($order) / 8) * 8;
    $diversified = substr((string) openssl_encrypt(str_pad($order, $length, "\0"), 'des-ede3-cbc', (string) base64_decode($key), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8)), 0, $length);

    return hash_hmac('sha256', $encoded, $diversified, true);
}

/** @return array<string, string> */
function sentParams(string $html): array
{
    preg_match('/name="Ds_MerchantParameters" value="([^"]+)"/', $html, $m);

    return json_decode((string) base64_decode($m[1]), true);
}

beforeEach(function () {
    Mail::fake();
    config()->set('payments.driver', 'redsys');
    config()->set('payments.redsys', ['merchant_code' => '999008881', 'terminal' => '1', 'secret_key' => REDSYS_KEY, 'environment' => 'test', 'bizum' => true, 'merchant_name' => 'Clave de Alma']);
    app()->forgetInstance(PaymentGateway::class);

    $store = Store::factory()->create(['status' => StoreStatus::Published]);
    $product = Product::factory()->create(['store_id' => $store->id, 'status' => ProductStatus::Published, 'price_cents' => 3000]);
    $this->variant = $product->variants()->create(['sku' => 'RS-M', 'size' => 'M']);
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);

    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id]);
    $this->checkout = fn (array $extra = []) => $this->post(route('checkout.place'), $extra + [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1', 'payment_method' => 'card',
    ]);
});

it('firma como la librería oficial de Redsys y rechaza lo manipulado', function () {
    $signer = new RedsysSignature(REDSYS_KEY);
    $reply = redsysReply(['Ds_Order' => '000112345678', 'Ds_Response' => '0000', 'Ds_Amount' => '3500']);

    expect($signer->verify(...array_values($reply)))->toMatchArray(['Ds_Order' => '000112345678', 'Ds_Amount' => '3500']);

    $sent = $signer->sign(['DS_MERCHANT_ORDER' => '000112345678', 'DS_MERCHANT_AMOUNT' => '3500']);
    expect($sent['Ds_Signature'])->toBe(base64_encode(redsysMac($sent['Ds_MerchantParameters'], '000112345678')));

    $tampered = redsysReply(['Ds_Order' => '000112345678', 'Ds_Response' => '0000', 'Ds_Amount' => '1']);
    expect(fn () => $signer->verify('HMAC_SHA256_V1', $tampered['Ds_MerchantParameters'], $reply['Ds_Signature']))->toThrow(InvalidWebhook::class);
    expect(fn () => (new RedsysSignature(base64_encode(str_repeat('x', 24))))->verify(...array_values($reply)))->toThrow(InvalidWebhook::class);
});

it('«Finalizar compra» enseña los métodos de pago de WooCommerce y lleva a Redsys con el elegido', function () {
    $this->get(route('checkout.show'))->assertOk()
        ->assertSee('Tarjeta de crédito o débito')->assertSee('Bizum')->assertSee('Modo de pruebas de Redsys');

    $response = ($this->checkout)(['payment_method' => 'bizum'])->assertOk()
        ->assertSee('https://sis-t.redsys.es:25443/sis/realizarPago', false)->assertSee('Ir a pagar 35,00 €');

    $order = Order::query()->sole();
    $sent = sentParams($response->getContent());
    expect($sent)->toMatchArray([
        'DS_MERCHANT_AMOUNT' => '3500', 'DS_MERCHANT_CURRENCY' => '978', 'DS_MERCHANT_MERCHANTCODE' => '999008881',
        'DS_MERCHANT_TRANSACTIONTYPE' => '0', 'DS_MERCHANT_PAYMETHODS' => 'z',
        'DS_MERCHANT_URLOK' => route('checkout.thanks', $order->token), 'DS_MERCHANT_URLKO' => route('checkout.pay', $order->token),
        'DS_MERCHANT_MERCHANTURL' => route('payments.webhook.redsys'),
    ])->and($sent['DS_MERCHANT_ORDER'])->toMatch('/^\d{12}$/')
        ->and($order->is_test)->toBeTrue()
        ->and(Payment::query()->sole())->method->toBe('bizum')->provider->toBe('redsys');

    ($this->checkout)(['payment_method' => 'paypal'])->assertSessionHasErrors(['payment_method' => 'Elige un método de pago.']);
});

it('el aviso firmado de Redsys da el pedido por pagado, una sola vez; uno falso se rechaza', function () {
    $sent = sentParams(($this->checkout)()->getContent());
    $order = Order::query()->sole();
    $reply = redsysReply(['Ds_Order' => $sent['DS_MERCHANT_ORDER'], 'Ds_Response' => '0000', 'Ds_Amount' => '3500', 'Ds_Currency' => '978', 'Ds_AuthorisationCode' => '123456']);

    $this->post(route('payments.webhook.redsys'), ['Ds_Signature' => 'falsa'] + $reply)->assertStatus(400);
    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    $this->post(route('payments.webhook.redsys'), $reply)->assertOk();
    $this->post(route('payments.webhook.redsys'), $reply)->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->events()->where('type', 'paid')->count())->toBe(1);
    $this->get(route('checkout.thanks', $order->token))->assertSee('Pedido recibido')->assertSee('Tarjeta de crédito o débito');
    $this->post(route('payments.webhook.stripe'), [])->assertNotFound();
});

it('sin aviso (como en local), vale el resultado firmado que trae el cliente al volver', function () {
    $sent = sentParams(($this->checkout)()->getContent());
    $order = Order::query()->sole();

    // Vuelve sin pagar: se le dice por qué y puede elegir otro método.
    $ko = redsysReply(['Ds_Order' => $sent['DS_MERCHANT_ORDER'], 'Ds_Response' => '9915', 'Ds_Amount' => '3500']);
    $this->get(route('checkout.pay', $order->token).'?'.http_build_query($ko))->assertOk()
        ->assertSee('No se ha completado el pago: Has cancelado el pago')->assertSee('Métodos de pago');
    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);

    // Nuevo intento desde «Pagar el pedido»: otro número de pedido para Redsys.
    $again = sentParams($this->post(route('checkout.pay.start', $order->token), ['payment_method' => 'card'])->assertOk()->getContent());
    expect($again['DS_MERCHANT_ORDER'])->not->toBe($sent['DS_MERCHANT_ORDER']);

    $ok = redsysReply(['Ds_Order' => $again['DS_MERCHANT_ORDER'], 'Ds_Response' => '0000', 'Ds_Amount' => '3500', 'Ds_Currency' => '978']);
    $this->get(route('checkout.thanks', $order->token).'?'.http_build_query($ok))->assertOk()->assertSee('Pedido recibido');
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    // Un resultado manipulado en la dirección no hace nada.
    $this->get(route('checkout.thanks', $order->token).'?'.http_build_query(['Ds_Signature' => 'x'] + $ok))->assertOk();
});

it('las devoluciones van por la API REST de Redsys sobre el mismo número de pedido', function () {
    $sent = sentParams(($this->checkout)()->getContent());
    $order = Order::query()->sole();
    $this->post(route('payments.webhook.redsys'), redsysReply(['Ds_Order' => $sent['DS_MERCHANT_ORDER'], 'Ds_Response' => '0000', 'Ds_Amount' => '3500', 'Ds_Currency' => '978']));

    Http::fake(['sis-t.redsys.es:25443/sis/rest/*' => Http::response(redsysReply(['Ds_Order' => $sent['DS_MERCHANT_ORDER'], 'Ds_Response' => '0900', 'Ds_Amount' => '3500', 'Ds_AuthorisationCode' => '654321']))]);
    app(CancelPaidOrder::class)($order->fresh(), 'El cliente se ha equivocado de talla.');

    Http::assertSent(function (HttpRequest $request) use ($sent): bool {
        $params = json_decode(base64_decode($request['Ds_MerchantParameters']), true);

        return $params['DS_MERCHANT_TRANSACTIONTYPE'] === '3' && $params['DS_MERCHANT_ORDER'] === $sent['DS_MERCHANT_ORDER'] && $params['DS_MERCHANT_AMOUNT'] === '3500';
    });
    expect(Refund::query()->sole())->status->toBe(Refund::SUCCEEDED)->provider_reference->toBe($sent['DS_MERCHANT_ORDER'].':654321')
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('si Redsys rechaza la devolución, no se da por hecha', function () {
    $sent = sentParams(($this->checkout)()->getContent());
    $order = Order::query()->sole();
    $this->post(route('payments.webhook.redsys'), redsysReply(['Ds_Order' => $sent['DS_MERCHANT_ORDER'], 'Ds_Response' => '0000', 'Ds_Amount' => '3500', 'Ds_Currency' => '978']));

    Http::fake(['*' => Http::response(['errorCode' => 'SIS0057'])]);
    $result = app(Refunds::class)->refund($order->id, 3500, 'prueba-1', 'Prueba');

    expect($result->succeeded)->toBeFalse()->and($result->message)->toContain('SIS0057')
        ->and(Refund::query()->sole()->status)->toBe(Refund::FAILED);
});

it('un segundo cobro del mismo pedido (dos pestañas) deja el pedido «a revisar»', function () {
    $first = sentParams(($this->checkout)()->getContent());
    $order = Order::query()->sole();
    $second = sentParams($this->post(route('checkout.pay.start', $order->token), ['payment_method' => 'card'])->getContent());

    foreach ([$first, $second] as $sent) {
        $this->post(route('payments.webhook.redsys'), redsysReply(['Ds_Order' => $sent['DS_MERCHANT_ORDER'], 'Ds_Response' => '0000', 'Ds_Amount' => '3500', 'Ds_Currency' => '978']))->assertOk();
    }

    expect($order->fresh())->status->toBe(OrderStatus::Paid)
        ->review_reason->toContain('Se ha cobrado dos veces');
});

it('en real («live») cobra en sis.redsys.es y los pedidos no son de prueba', function () {
    config()->set('payments.redsys.environment', 'live');
    app()->forgetInstance(PaymentGateway::class);

    ($this->checkout)()->assertOk()->assertSee('https://sis.redsys.es/sis/realizarPago', false);
    expect(Order::query()->sole()->is_test)->toBeFalse();
});
