<?php

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\CustomerAddress;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    Notification::fake();
    $this->withoutVite();
    config()->set('payments.driver', 'fake');

    $store = Store::factory()->create(['subdomain' => 'luna', 'name' => 'Luna Bruma', 'status' => StoreStatus::Published]);
    $garment = BaseGarment::factory()->create(['store_id' => $store->id, 'weight_grams' => 250]);
    $product = Product::factory()->create(['store_id' => $store->id, 'base_garment_id' => $garment->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3500, 'vat_rate_bps' => 2100]);
    $this->variant = $product->variants()->create(['sku' => 'LB-01-M-NEGRO', 'size' => 'M', 'color' => 'Negro']);

    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::where('name', 'NACEX')->value('id'), 'active' => true]);
    $rate->bands()->create(['max_weight_grams' => 1000, 'price_cents' => 495]);
});

function datosCuenta(array $over = []): array
{
    return array_merge([
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456',
        'address_line1' => 'Calle Mayor 1', 'postal_code' => '28013', 'city' => 'Madrid',
        'country' => 'ES', 'terms' => '1', 'simulate' => 'ok',
    ], $over);
}

function comprarCuenta($test, array $over = []): Order
{
    $test->postJson(route('cart.add'), ['variant_id' => $test->variant->id, 'quantity' => 1])->assertOk();
    $test->post(route('checkout.place'), datosCuenta($over))->assertRedirect();

    return Order::query()->latest('id')->firstOrFail();
}

function cliente(array $attrs = []): User
{
    return User::factory()->withRole(Role::Customer)->create(array_merge(['email' => 'ana@example.com', 'name' => 'Ana Pérez'], $attrs));
}

it('sin sesión, «Mi cuenta» enseña «Acceder» y «Registrarse» como WooCommerce', function () {
    $this->get('/mi-cuenta')->assertOk()
        ->assertSee('Mi cuenta')->assertSee('Acceder')->assertSee('Registrarse')
        ->assertSee('Recuérdame')->assertSee('¿Has olvidado tu contraseña?')->assertSee('noindex', false);
    $this->get('/mi-cuenta/pedidos')->assertRedirect(route('account.show'));
});

it('registrarse crea un cliente, inicia la sesión y pide confirmar el email', function () {
    $this->post(route('account.register'), ['reg_email' => 'Nuevo@Example.com', 'reg_password' => 'secreta-123'])
        ->assertRedirect(route('account.show'));

    $user = User::query()->where('email', 'nuevo@example.com')->sole();
    expect($user->isCustomer())->toBeTrue()->and($user->name)->toBe('nuevo')->and($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get('/mi-cuenta')->assertOk()
        ->assertSee('Hola')->assertSee('Cerrar sesión')->assertSee('Escritorio')->assertSee('Pedidos')
        ->assertSee('Direcciones')->assertSee('Detalles de la cuenta')->assertSee('Salir')->assertSee('Confirma tu email');

    $this->post(route('account.register'), ['reg_email' => 'nuevo@example.com', 'reg_password' => 'otra-123456']);
    expect(User::query()->where('email', 'nuevo@example.com')->count())->toBe(1);
});

it('acceder con email y contraseña, y salir', function () {
    $user = cliente(['password' => 'secreta-123']);

    $this->post(route('account.login'), ['login_email' => 'ana@example.com', 'login_password' => 'mala'])
        ->assertSessionHasErrors('login_email');
    $this->assertGuest();

    $this->post(route('account.login'), ['login_email' => 'ANA@example.com', 'login_password' => 'secreta-123'])->assertRedirect(route('account.show'));
    $this->assertAuthenticatedAs($user);

    $this->post(route('account.logout'))->assertRedirect(route('account.show'));
    $this->assertGuest();
});

it('«Pedidos» enseña los suyos y los de invitado solo con el email confirmado', function () {
    $guest = comprarCuenta($this);           // como invitado, con ana@example.com
    session()->flush();
    $user = cliente(['email_verified_at' => null]);
    $other = Order::query()->sole();

    $this->actingAs($user)->get('/mi-cuenta/pedidos')->assertOk()
        ->assertDontSee($guest->reference)->assertSee('1 pedido hecho sin cuenta');
    $this->get(route('account.order', $other->reference))->assertNotFound();

    $user->forceFill(['email_verified_at' => now()])->save();
    $this->get('/mi-cuenta/pedidos')->assertOk()
        ->assertSee($guest->reference)->assertSee('Procesando')->assertSee('para 1 artículo')->assertSee('Ver');
    $this->get(route('account.order', $guest->reference))->assertOk()
        ->assertSee('está actualmente')->assertSee('Procesando')->assertSee('Detalles del pedido')->assertSee('La esencia');
});

it('nadie ve pedidos de otro cliente', function () {
    $order = comprarCuenta($this, ['email' => 'otra@example.com']);
    session()->flush();

    $this->actingAs(cliente(['email_verified_at' => now()]))
        ->get(route('account.order', $order->reference))->assertNotFound();
});

it('comprar con sesión rellena los datos, guarda la dirección y enlaza el pedido', function () {
    $user = cliente();
    CustomerAddress::query()->create(['user_id' => $user->id, 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Vieja 3', 'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES']);
    $this->actingAs($user);
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 1]);

    $this->get(route('checkout.show'))->assertOk()
        ->assertSee('value="Calle Vieja 3"', false)->assertSee('value="ana@example.com"', false)
        ->assertDontSee('¿Ya eres cliente?')->assertDontSee('¿Crear una cuenta?');

    $this->post(route('checkout.place'), datosCuenta(['address_line1' => 'Calle Nueva 9']))->assertRedirect();

    $order = Order::query()->sole();
    expect($order->user_id)->toBe($user->id)
        ->and($user->address()->sole()->address_line1)->toBe('Calle Nueva 9');
    $this->get('/mi-cuenta/pedidos')->assertSee($order->reference);
});

it('«¿Crear una cuenta?» al finalizar la compra', function () {
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 1]);
    $this->get(route('checkout.show'))->assertSee('¿Ya eres cliente?')->assertSee('¿Crear una cuenta?');

    $this->post(route('checkout.place'), datosCuenta(['create_account' => '1', 'account_password' => 'secreta-123']))->assertRedirect();

    $user = User::query()->where('email', 'ana@example.com')->sole();
    expect($user->isCustomer())->toBeTrue()->and($user->name)->toBe('Ana Pérez')
        ->and(Order::query()->sole()->user_id)->toBe($user->id)
        ->and($user->address()->exists())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('«¿Crear una cuenta?» con un email ya registrado pide acceder', function () {
    cliente();
    $this->postJson(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 1]);

    $this->post(route('checkout.place'), datosCuenta(['create_account' => '1', 'account_password' => 'secreta-123']))
        ->assertSessionHasErrors(['email' => 'Ya hay una cuenta registrada con tu dirección de correo electrónico. Accede para continuar.']);
    expect(Order::query()->count())->toBe(0);
});

it('«¿Ya eres cliente?» vuelve a «Finalizar compra» después de acceder', function () {
    cliente(['password' => 'secreta-123']);

    $this->get(route('account.show', ['volver' => 'finalizar-compra']))->assertOk();
    $this->post(route('account.login'), ['login_email' => 'ana@example.com', 'login_password' => 'secreta-123'])
        ->assertRedirect(route('checkout.show'));
});

it('«Direcciones» y «Detalles de la cuenta»', function () {
    $user = cliente(['password' => 'secreta-123', 'email_verified_at' => now()]);
    $this->actingAs($user);

    $this->get('/mi-cuenta/direcciones')->assertOk()->assertSee('Dirección de facturación y envío')->assertSee('Añadir');
    $this->post(route('account.address.save'), ['name' => 'Ana Pérez', 'country' => 'ES', 'address_line1' => 'Calle Sol 5', 'postal_code' => '41001', 'city' => 'Sevilla', 'phone' => '600 111 222'])
        ->assertRedirect(route('account.addresses'));
    $this->get('/mi-cuenta/direcciones')->assertSee('Calle Sol 5')->assertSee('Editar');
    $this->post(route('account.address.save'), ['name' => 'Ana', 'country' => 'ES', 'address_line1' => 'X', 'postal_code' => '1', 'city' => 'Sevilla', 'phone' => '600111222'])
        ->assertSessionHasErrors('postal_code');

    // Cambiar el email pide la contraseña actual y obliga a confirmarlo otra vez.
    $this->post(route('account.details.save'), ['name' => 'Ana P.', 'email' => 'ana.p@example.com'])->assertSessionHasErrors('current_password');
    $this->post(route('account.details.save'), ['name' => 'Ana P.', 'email' => 'ana.p@example.com', 'current_password' => 'secreta-123', 'password' => 'nueva-123456', 'password_confirmation' => 'nueva-123456'])
        ->assertRedirect(route('account.details'));

    $user->refresh();
    expect($user->email)->toBe('ana.p@example.com')->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('nueva-123456', $user->password))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('confirmar el email con el enlace firmado', function () {
    $user = cliente(['email_verified_at' => null]);
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($url)->assertRedirect(route('account.show'));
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('«¿Has olvidado tu contraseña?» envía el enlace y deja elegir otra', function () {
    $user = cliente(['password' => 'secreta-123']);

    $this->get('/mi-cuenta/recuperar-contrasena')->assertOk()->assertSee('Restablecer contraseña');
    $this->post(route('account.password.email'), ['email' => 'nadie@example.com'])->assertSessionHas('account.status');
    $this->post(route('account.password.email'), ['email' => 'ana@example.com'])->assertSessionHas('account.status');

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token): bool {
        $token = $n->token;

        return true;
    });

    $this->get(route('account.password.reset', ['token' => $token, 'email' => 'ana@example.com']))->assertOk();
    $this->post(route('account.password.update'), ['token' => $token, 'email' => 'ana@example.com', 'password' => 'otra-123456', 'password_confirmation' => 'otra-123456'])
        ->assertRedirect(route('account.show'));
    expect(Hash::check('otra-123456', $user->refresh()->password))->toBeTrue();
});

it('el panel tiene «Clientes» y «Usuarios» es solo el equipo', function () {
    $customer = cliente(['email_verified_at' => now()]);
    $order = comprarCuenta($this);
    session()->flush();
    $admin = User::factory()->withRole(Role::Superadmin)->create(['name' => 'Equipo Uno']);

    $this->actingAs($admin);
    Livewire::test(ListCustomers::class)->assertOk()->assertSee('Ana Pérez')->assertSee('39,95 €')->assertDontSee('Equipo Uno');
    $this->get('/admin/clientes/'.$customer->id)->assertOk()->assertSee($order->reference);
    $this->get('/admin/usuarios')->assertOk()->assertSee('Equipo Uno')->assertDontSee('ana@example.com');

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get('/admin/clientes')->assertForbidden();
});
