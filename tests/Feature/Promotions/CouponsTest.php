<?php

use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Modules\Cart\Application\PriceCart;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceIssuer;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceLine;
use App\Modules\Orders\Application\CancelPaidOrder;
use App\Modules\Orders\Application\PlaceOrder;
use App\Modules\Orders\Contracts\CheckoutDetails;
use App\Modules\Orders\Contracts\OrderDiscount;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\HandleGatewayEvent;
use App\Modules\Payments\Application\StartPayment;
use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Promotions\Contracts\CouponType;
use App\Modules\Promotions\Domain\DiscountAllocator;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Modules\Promotions\Infrastructure\Models\CouponRedemption;
use App\Modules\Shipping\Contracts\ShippingQuote;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    $this->withoutVite();
    config()->set('payments.driver', 'fake');

    $this->store = Store::factory()->create(['subdomain' => 'luna', 'name' => 'Luna Bruma', 'status' => StoreStatus::Published, 'commission_bps' => 2500]);
    $this->other = Store::factory()->create(['subdomain' => 'otra', 'name' => 'Otra tienda', 'status' => StoreStatus::Published]);
    $garment = BaseGarment::factory()->create(['store_id' => $this->store->id, 'weight_grams' => 250]);
    $tee = Product::factory()->create(['store_id' => $this->store->id, 'base_garment_id' => $garment->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3000, 'vat_rate_bps' => 2100]);
    $cap = Product::factory()->create(['store_id' => $this->other->id, 'name' => 'Gorra', 'status' => ProductStatus::Published, 'price_cents' => 2000, 'vat_rate_bps' => 2100]);
    $this->tee = $tee->variants()->create(['sku' => 'LB-TEE-M', 'size' => 'M']);
    $this->cap = $cap->variants()->create(['sku' => 'OT-CAP']);

    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::where('name', 'NACEX')->value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);
});

function cupon(array $attrs = []): Coupon
{
    return Coupon::query()->create(array_merge(['code' => 'verano', 'type' => CouponType::Percent, 'amount' => 1000], $attrs));
}

function datosCupon(array $over = []): array
{
    return array_merge([
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1', 'simulate' => 'ok',
    ], $over);
}

it('reparte el descuento al céntimo, en proporción y sin pasarse de ninguna línea', function () {
    expect(DiscountAllocator::allocate(1000, [1 => 3000, 2 => 2000, 3 => 1000]))->toBe([1 => 500, 2 => 333, 3 => 167])
        ->and(array_sum(DiscountAllocator::allocate(1001, [1 => 333, 2 => 333, 3 => 335])))->toBe(1001)
        ->and(DiscountAllocator::allocate(9999, [1 => 500]))->toBe([1 => 500])
        ->and(DiscountAllocator::percent(3333, 1500))->toBe(500);
});

it('aplica el cupón en el carrito como WooCommerce y lo quita', function () {
    cupon();
    $this->postJson(route('cart.add'), ['variant_id' => $this->tee->id, 'quantity' => 2]);

    $this->post(route('cart.coupon'), ['coupon_code' => 'Verano'])->assertSessionHas('cart.updated', 'El código de cupón se ha aplicado correctamente.');
    $this->get(route('cart.show'))->assertOk()
        ->assertSee('Código de cupón')->assertSee('Aplicar cupón')
        ->assertSee('Cupón: VERANO')->assertSee('10 %')->assertSee('−6,00 €')->assertSee('54,00 €');

    $this->post(route('cart.coupon'), ['coupon_code' => 'NOEXISTE'])->assertSessionHas('cart.error', 'El cupón «NOEXISTE» no existe.');
    $this->post(route('cart.coupon.remove'));
    $this->get(route('cart.show'))->assertDontSee('Cupón: VERANO');
});

it('el pedido guarda el descuento en cada línea: IVA, comisión y factura salen de lo cobrado', function () {
    cupon(['type' => CouponType::FixedCart, 'amount' => 1000]); // 10 € entre 60 € de camisetas y 20 € de gorra
    InvoiceIssuer::current()->update(['legal_name' => 'Ejemplo S.L.', 'nif' => 'B12345678', 'address' => 'C/ A 1', 'postal_code' => '28001', 'city' => 'Madrid', 'email' => 'a@b.es']);
    $this->postJson(route('cart.add'), ['variant_id' => $this->tee->id, 'quantity' => 2]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->cap->id, 'quantity' => 1]);
    $this->post(route('cart.coupon'), ['coupon_code' => 'VERANO']);

    $this->get(route('checkout.show'))->assertOk()->assertSee('Cupón: VERANO')->assertDontSee('¿Tienes un cupón?');
    $this->post(route('checkout.place'), datosCupon())->assertRedirect();

    $order = Order::query()->with('lines')->sole();
    $tee = $order->lines->firstWhere('sku', 'LB-TEE-M');
    $cap = $order->lines->firstWhere('sku', 'OT-CAP');
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->coupon_code)->toBe('VERANO')
        ->and($order->discount_cents)->toBe(1000)
        ->and($tee->discount_cents)->toBe(750)->and($tee->total_cents)->toBe(5250)
        ->and($cap->discount_cents)->toBe(250)->and($cap->total_cents)->toBe(1750)
        ->and($order->total_cents)->toBe(7000 + 500)
        ->and(CouponRedemption::query()->sole()->discount_cents)->toBe(1000);

    $this->get(route('checkout.thanks', $order->token))->assertSee('Cupón: VERANO')->assertSee('−10,00 €');
    // La factura de un pedido real lleva el descuento en la línea (con el pago simulado no se factura).
    expect(InvoiceLine::query()->count())->toBe(0);
});

it('cupón de una tienda solo descuenta sus artículos; con envío gratis, el envío sale a 0', function () {
    cupon(['code' => 'LUNA', 'amount' => 2000, 'store_id' => $this->store->id, 'free_shipping' => true]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->tee->id, 'quantity' => 1]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->cap->id, 'quantity' => 1]);
    $this->post(route('cart.coupon'), ['coupon_code' => 'luna']);

    $this->getJson(route('checkout.quote', ['country' => 'ES', 'postal_code' => '28013']))->assertJson(['ok' => true, 'shipping' => 'Gratis', 'total_cents' => 4400]);
    $this->post(route('checkout.place'), datosCupon());

    $order = Order::query()->with('lines')->sole();
    expect($order->shipping_cents)->toBe(0)->and($order->shipping_free)->toBeTrue()
        ->and($order->lines->firstWhere('sku', 'OT-CAP')->discount_cents)->toBe(0)
        ->and($order->total_cents)->toBe(4400);
});

it('respeta caducidad, gasto mínimo y límites de uso', function () {
    $this->postJson(route('cart.add'), ['variant_id' => $this->tee->id, 'quantity' => 1]);

    cupon(['code' => 'VIEJO', 'expires_on' => now()->subDay()->toDateString()]);
    $this->post(route('cart.coupon'), ['coupon_code' => 'viejo'])->assertSessionHas('cart.error', 'Este cupón ha caducado.');

    cupon(['code' => 'MIN', 'minimum_cents' => 5000]);
    $this->post(route('cart.coupon'), ['coupon_code' => 'min'])->assertSessionHas('cart.error', 'El gasto mínimo para usar este cupón es de 50,00 €.');

    cupon(['code' => 'UNO', 'usage_limit_per_customer' => 1]);
    $this->post(route('cart.coupon'), ['coupon_code' => 'uno']);
    $this->post(route('checkout.place'), datosCupon())->assertRedirect();
    expect(Order::query()->sole()->coupon_code)->toBe('UNO');

    // Segunda compra del mismo email con el mismo cupón: al finalizar se rechaza.
    $this->postJson(route('cart.add'), ['variant_id' => $this->tee->id, 'quantity' => 1]);
    $this->post(route('cart.coupon'), ['coupon_code' => 'uno']);
    $this->post(route('checkout.place'), datosCupon())->assertRedirect(route('checkout.show'))
        ->assertSessionHas('payment.error', 'Ya has usado este cupón el máximo de veces permitido.');
    expect(Order::query()->count())->toBe(1);
});

it('si el pedido pagado se cancela, el uso del cupón se libera', function () {
    cupon(['usage_limit' => 1]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->tee->id, 'quantity' => 1]);
    $this->post(route('cart.coupon'), ['coupon_code' => 'verano']);
    $this->post(route('checkout.place'), datosCupon());
    $order = Order::query()->sole();

    expect(Coupon::query()->sole()->uses())->toBe(1);
    app(CancelPaidOrder::class)($order->refresh(), 'Prueba', 'Admin');
    expect(Coupon::query()->sole()->uses())->toBe(0);
});

it('Marketing → Cupones: el superadmin los crea con los campos de WooCommerce', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    Livewire::test(CreateCoupon::class)
        ->fillForm(['code' => 'navidad', 'type' => CouponType::Percent->value, 'amount_input' => '15,5', 'free_shipping' => true, 'minimum_input' => '40', 'usage_limit' => 100])
        ->call('create')->assertHasNoFormErrors();

    $coupon = Coupon::query()->sole();
    expect($coupon->code)->toBe('NAVIDAD')->and($coupon->amount)->toBe(1550)->and($coupon->minimum_cents)->toBe(4000)
        ->and($coupon->label())->toBe('15,5 % + envío gratis');
    Livewire::test(ListCoupons::class)->assertSee('NAVIDAD')->assertSee('0 / 100');
    $this->get('/admin')->assertSee('Marketing')->assertSee('Cupones');

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())->get('/admin/cupones')->assertForbidden();
});

it('la factura de un pedido real con cupón cobra lo descontado y lo dice en la línea', function () {
    InvoiceIssuer::current()->update(['legal_name' => 'Ejemplo Tienda S.L.', 'nif' => 'B00000000', 'address' => 'Calle Prueba 1', 'postal_code' => '28001', 'city' => 'Madrid']);
    $cart = app(PriceCart::class)([$this->tee->id => 2]);
    $order = app(PlaceOrder::class)(
        $cart,
        new CheckoutDetails('ana@example.com', 'Ana Pérez', '600123456', 'Calle Mayor 1', null, '28013', 'Madrid', null, 'ES', null, 'v1'),
        new ShippingQuote(ShippingZone::Peninsula, 500, false, 1, 2, 'NACEX'),
        false,
        new OrderDiscount('VERANO', [$this->tee->id => 600]),
    );
    $payment = app(StartPayment::class)($order->id, (string) $order->reference, $order->total_cents, 'EUR', $order->email);
    app(HandleGatewayEvent::class)('fake', new GatewayEvent('evt-'.$order->id, GatewayEvent::SUCCEEDED, $payment->providerReference, $order->total_cents, 'EUR'));

    $line = InvoiceLine::query()->where('description', 'like', 'La esencia%')->sole();
    expect($order->refresh()->total_cents)->toBe(5400 + 500)
        ->and($line->total_cents)->toBe(5400)
        ->and($line->description)->toContain('descuento cupón VERANO −6,00 €')
        ->and(Invoice::query()->sole()->total_cents)->toBe(5900);
});
