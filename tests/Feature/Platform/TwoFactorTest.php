<?php

use App\Filament\Pages\ArtistSalesDashboard;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('clavedealma.security.two_factor_required', true);
    Filament::setCurrentPanel('admin');
});

function withTwoFactor(User $user): string
{
    $provider = AppAuthentication::make();
    $secret = $provider->generateSecret();
    $user->saveAppAuthenticationSecret($secret);
    $user->saveAppAuthenticationRecoveryCodes(['codigo-uno', 'codigo-dos']);

    return $secret;
}

it('el equipo tiene que configurarla para entrar al panel; los artistas no', function () {
    $admin = User::factory()->withRole(Role::Superadmin)->create();
    $this->actingAs($admin)->get('/admin')->assertRedirectContains('/admin/multi-factor-authentication/set-up');
    $this->actingAs($admin)->get('/admin/pedidos')->assertRedirectContains('set-up');
    $this->actingAs($admin)->get('/admin/multi-factor-authentication/set-up')->assertOk()->assertSee('Clave de Alma');

    withTwoFactor($admin);
    $this->actingAs($admin->fresh())->get('/admin/pedidos')->assertOk();

    $artist = User::factory()->withRole(Role::Artist)->create();
    $this->actingAs($artist)->get(ArtistSalesDashboard::getUrl())->assertOk();

    // Sin TWO_FACTOR_REQUIRED (p. ej. en local) no se obliga a nadie.
    config()->set('clavedealma.security.two_factor_required', false);
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create())->get('/admin/pedidos')->assertOk();
});

it('la clave se guarda cifrada y activarla o quitarla queda en el registro de seguridad', function () {
    $admin = User::factory()->withRole(Role::Superadmin)->create();
    $secret = withTwoFactor($admin);

    $raw = DB::table('users')->where('id', $admin->id)->value('app_authentication_secret');
    expect($raw)->not->toBe($secret)->not->toContain($secret)
        ->and($admin->fresh()->hasTwoFactor())->toBeTrue()
        ->and(SecurityEvent::query()->where('type', SecurityEventType::TwoFactorChanged)->where('description', 'like', '%activada%')->exists())->toBeTrue();
});

it('al entrar, después de la contraseña pide el código de la app', function () {
    $admin = User::factory()->withRole(Role::Superadmin)->create(['email' => 'admin@clavedealma.test', 'password' => 'una-contraseña-larga']);
    $secret = withTwoFactor($admin);

    $login = Livewire::test(Login::class)
        ->set('data.email', 'admin@clavedealma.test')
        ->set('data.password', 'una-contraseña-larga')
        ->call('authenticate');
    $this->assertGuest();
    $login->assertSee('código');

    $login->set('data.multiFactor.app.code', '000000')->call('authenticate');
    $this->assertGuest();

    $login->set('data.multiFactor.app.code', AppAuthentication::make()->getCurrentCode($admin->fresh(), $secret))->call('authenticate');
    $this->assertAuthenticatedAs($admin);
});

it('el superadmin puede quitársela a quien ha perdido el móvil, y hay un comando de emergencia', function () {
    $this->actingAs($admin = User::factory()->withRole(Role::Superadmin)->create());
    withTwoFactor($admin);
    $maker = User::factory()->withRole(Role::Manufacturer)->create(['email' => 'taller@clavedealma.test']);
    withTwoFactor($maker);

    Livewire::test(ListUsers::class)->callTableAction('resetTwoFactor', $maker)->assertNotified('Verificación en dos pasos quitada');
    expect($maker->fresh()->hasTwoFactor())->toBeFalse()
        ->and(SecurityEvent::query()->where('type', SecurityEventType::TwoFactorChanged)->where('description', 'like', '%quitada a taller@%')->exists())->toBeTrue();

    expect(Artisan::call('usuarios:quitar-dos-pasos', ['email' => $admin->email]))->toBe(0)
        ->and($admin->fresh()->hasTwoFactor())->toBeFalse();
});
