<?php

use App\Filament\Pages\PaymentSettings;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Platform\Payments\RedsysSettings;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use App\Platform\Settings\Settings;
use Filament\Facades\Filament;
use Livewire\Livewire;

// Una clave con el formato de Redsys (Base64 de 24 bytes), inventada para la prueba.
const PANEL_KEY = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0';

beforeEach(function () {
    config()->set('payments.driver', 'redsys');
    config()->set('payments.redsys', ['merchant_code' => '', 'terminal' => '1', 'secret_key' => '', 'environment' => 'test', 'card' => true, 'bizum' => false, 'merchant_name' => 'Clave de Alma']);
    app()->forgetInstance(PaymentGateway::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
});

it('«Ajustes → Pagos» enseña el estado, los datos para el banco y los pasos; solo al superadmin', function () {
    $this->get(PaymentSettings::getUrl())->assertOk()
        ->assertSee('Estado del TPV')->assertSee('Pruebas (sis-t.redsys.es)')
        ->assertSee(route('payments.webhook.redsys'))->assertSee('Parámetros en las URLs')
        ->assertSee('Contrata el TPV virtual con tu banco')->assertSee('Pasa a cobros reales')
        ->assertSee('Todavía ninguno');

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get(PaymentSettings::getUrl())->assertForbidden();
});

it('guarda los datos del TPV con la clave cifrada, pide la contraseña y lo anota sin la clave', function () {
    Livewire::test(PaymentSettings::class)
        ->callAction('edit', ['merchant_code' => '123456789', 'terminal' => '1', 'secret' => PANEL_KEY, 'merchant_name' => 'Clave de Alma', 'card' => true, 'bizum' => true, 'current_password' => 'mal'])
        ->assertHasActionErrors(['current_password']);
    Livewire::test(PaymentSettings::class)
        ->callAction('edit', ['merchant_code' => '12345', 'terminal' => '1', 'secret' => 'corta', 'merchant_name' => 'Clave de Alma', 'card' => false, 'bizum' => false, 'current_password' => 'password'])
        ->assertHasActionErrors(['merchant_code', 'secret', 'card']);
    Livewire::test(PaymentSettings::class)
        ->callAction('edit', ['merchant_code' => '123456789', 'terminal' => '1', 'secret' => PANEL_KEY, 'merchant_name' => 'Clave de Alma', 'card' => true, 'bizum' => true, 'current_password' => 'password'])
        ->assertHasNoActionErrors();

    $stored = Settings::get(RedsysSettings::KEY);
    expect($stored['secret'])->not->toBe(PANEL_KEY)->not->toContain(PANEL_KEY)
        ->and(RedsysSettings::secret())->toBe(PANEL_KEY)
        ->and(RedsysSettings::configured())->toBeTrue()
        ->and(RedsysSettings::sources())->toBe(['merchant_code' => 'panel', 'terminal' => 'panel', 'secret' => 'panel'])
        ->and(app(PaymentGateway::class)->methods())->toBe(['card' => 'Tarjeta de crédito o débito', 'bizum' => 'Bizum']);

    $log = SecurityEvent::query()->where('type', SecurityEventType::PaymentSettingsChanged)->sole();
    expect($log->description)->toContain('clave secreta')->toContain('Bizum')->not->toContain(PANEL_KEY);

    // Sin escribir clave nueva, se queda la guardada.
    Livewire::test(PaymentSettings::class)
        ->callAction('edit', ['merchant_code' => '123456789', 'terminal' => '2', 'secret' => '', 'merchant_name' => 'Clave de Alma', 'card' => true, 'bizum' => false, 'current_password' => 'password'])
        ->assertHasNoActionErrors();
    expect(RedsysSettings::secret())->toBe(PANEL_KEY)->and(config('payments.redsys.terminal'))->toBe('2');
});

it('lo del panel se aplica al crear la pasarela y manda sobre el .env', function () {
    config()->set('payments.redsys.merchant_code', '999999999');
    Settings::put(RedsysSettings::KEY, ['merchant_code' => '123456789', 'terminal' => '1', 'secret' => encrypt(PANEL_KEY, false), 'bizum' => true]);

    expect(app(PaymentGateway::class)->methods())->toHaveKey('bizum')
        ->and(config('payments.redsys.merchant_code'))->toBe('123456789')
        ->and(config('payments.redsys.secret_key'))->toBe(PANEL_KEY);
});

it('el comercio de pruebas de Redsys se pone con un botón, y sin datos reales no se pasa a cobros reales', function () {
    Livewire::test(PaymentSettings::class)
        ->callAction('testMerchant', ['current_password' => 'password'])->assertHasNoActionErrors();
    expect(config('payments.redsys.merchant_code'))->toBe('999008881')
        ->and(RedsysSettings::configured())->toBeTrue()
        ->and(app(PaymentGateway::class)->testMode())->toBeTrue();

    Livewire::test(PaymentSettings::class)
        ->callAction('environment', ['current_password' => 'password'])
        ->assertNotified('Faltan los datos reales del TPV');
    expect(config('payments.redsys.environment'))->toBe('test');

    Livewire::test(PaymentSettings::class)
        ->callAction('edit', ['merchant_code' => '123456789', 'terminal' => '1', 'secret' => PANEL_KEY, 'merchant_name' => 'Clave de Alma', 'card' => true, 'bizum' => false, 'current_password' => 'password']);
    Livewire::test(PaymentSettings::class)
        ->callAction('environment', ['current_password' => 'password'])->assertNotified('Cobros reales activados');

    expect(config('payments.redsys.environment'))->toBe('live')
        ->and(app(PaymentGateway::class)->testMode())->toBeFalse();
    $this->get(PaymentSettings::getUrl())->assertSee('Cobros reales.')->assertSee('Volver a modo de pruebas');
});

it('el aviso del banco queda como «Último aviso del banco»', function () {
    RedsysSettings::save(RedsysSettings::TEST_MERCHANT);
    app()->forgetInstance(PaymentGateway::class);

    $this->post(route('payments.webhook.redsys'), ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => 'x', 'Ds_Signature' => 'x'])->assertStatus(400);
    expect(Settings::get(RedsysSettings::LAST_NOTICE))->toBeNull();

    $encoded = base64_encode((string) json_encode(['Ds_Order' => '000199999999', 'Ds_Response' => '0000']));
    $length = 16;
    $key = substr((string) openssl_encrypt(str_pad('000199999999', $length, "\0"), 'des-ede3-cbc', (string) base64_decode(RedsysSettings::TEST_MERCHANT['secret']), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8)), 0, $length);
    $this->post(route('payments.webhook.redsys'), ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $encoded, 'Ds_Signature' => base64_encode(hash_hmac('sha256', $encoded, $key, true))])->assertOk();

    expect(Settings::get(RedsysSettings::LAST_NOTICE))->not->toBeNull();
    $this->get(PaymentSettings::getUrl())->assertDontSee('Todavía ninguno');
});
