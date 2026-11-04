<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Development\ProjectTree;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel('admin'));

it('la página Desarrollo enseña la guía, el árbol generado y las decisiones', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    $this->get('/admin/desarrollo')->assertOk()
        ->assertSee('Cómo está hecho Clave de Alma')
        ->assertSee('Hola, Diego')
        ->assertSee('Árbol del sistema')
        ->assertSee('Flujo de una venta')
        ->assertSee('ADR 0001')
        ->assertSee('Invoicing/');

    expect(ProjectTree::render())->toContain('Modules/')->toContain('Contracts/  — lo que pueden usar los demás')->toContain('PlaceOrder');
});

it('Desarrollo y el registro de seguridad son solo del superadministrador', function () {
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get('/admin/desarrollo')->assertForbidden();
    $this->get('/admin/registro-de-seguridad')->assertForbidden();

    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create());
    $this->get('/admin/desarrollo')->assertForbidden();
});

it('anota accesos correctos y fallidos sin guardar la contraseña', function () {
    $user = User::factory()->withRole(Role::Superadmin)->create(['email' => 'admin@example.com', 'password' => bcrypt('clave-correcta-1')]);

    auth()->attempt(['email' => 'admin@example.com', 'password' => 'mala']);
    auth()->attempt(['email' => 'nadie@example.com', 'password' => 'mala']);
    auth()->attempt(['email' => 'admin@example.com', 'password' => 'clave-correcta-1']);

    $failed = SecurityEvent::where('type', SecurityEventType::LoginFailed)->get();
    expect($failed)->toHaveCount(2)
        ->and($failed->pluck('user_email')->all())->toContain('admin@example.com', 'nadie@example.com')
        ->and(SecurityEvent::where('type', SecurityEventType::Login)->sole()->user_id)->toBe($user->id)
        ->and(json_encode(SecurityEvent::all()->toArray()))->not->toContain('clave-correcta-1')->not->toContain('mala');
});

it('anota los cambios de rol y de contraseña', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    $user = User::factory()->withRole(Role::Manufacturer)->create(['email' => 'taller@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['role' => Role::CatalogManager->value])->call('save')->assertHasNoFormErrors();
    $user->update(['password' => bcrypt('otra-clave-larga')]);

    expect(SecurityEvent::where('type', SecurityEventType::RoleChanged)->sole()->description)->toContain('fabricante → gestor_catalogo')
        ->and(SecurityEvent::where('type', SecurityEventType::PasswordChanged)->exists())->toBeTrue();
});

it('el registro no se modifica, se ve en el panel y se purga pasado el plazo', function () {
    SecurityLog::record(SecurityEventType::BackupDownloaded, 'Copia de seguridad descargada.', 'Copia #1');
    $event = SecurityEvent::sole();
    expect(fn () => $event->update(['description' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);

    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    $this->get('/admin/registro-de-seguridad')->assertOk()->assertSee('Copia de seguridad descargada');

    $this->travel(366)->days();
    $this->artisan('seguridad:purgar')->assertSuccessful();
    expect(SecurityEvent::where('type', SecurityEventType::BackupDownloaded)->exists())->toBeFalse();
});
