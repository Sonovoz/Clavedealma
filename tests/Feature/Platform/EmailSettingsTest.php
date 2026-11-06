<?php

use App\Filament\Pages\EmailSettings;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Mail\SmtpSettings;
use App\Platform\Mail\SmtpTestMail;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use App\Platform\Settings\Settings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create(['email' => 'admin@clavedealma.test']));
});

$form = ['host' => 'ssl0.ovh.net', 'port' => '465', 'encryption' => 'ssl', 'username' => 'pedidos@clavedealma.es',
    'password' => 'clave-del-correo', 'from_address' => 'pedidos@clavedealma.es', 'from_name' => 'Clave de Alma'];

it('«Ajustes → Correo» guarda el SMTP con la contraseña cifrada y la aplica a todo el correo', function () use ($form) {
    $this->get(EmailSettings::getUrl())->assertOk()->assertSee('Correo de salida')->assertSee('Qué sale por aquí');

    Livewire::test(EmailSettings::class)->callAction('edit', $form + ['current_password' => 'mal'])->assertHasActionErrors(['current_password']);
    Livewire::test(EmailSettings::class)->callAction('edit', $form + ['current_password' => 'password'])->assertHasNoActionErrors();

    expect(Settings::get(SmtpSettings::KEY)['password'])->not->toBe('clave-del-correo')->not->toContain('clave-del-correo')
        ->and(config('mail.mailers.smtp'))->toMatchArray(['host' => 'ssl0.ovh.net', 'port' => 465, 'scheme' => 'smtps', 'username' => 'pedidos@clavedealma.es', 'password' => 'clave-del-correo'])
        ->and(config('mail.from'))->toBe(['address' => 'pedidos@clavedealma.es', 'name' => 'Clave de Alma'])
        ->and(SmtpSettings::source())->toBe('panel');

    $log = SecurityEvent::query()->where('type', SecurityEventType::MailSettingsChanged)->sole();
    expect($log->description)->toContain('contraseña')->not->toContain('clave-del-correo');

    // Sin escribir contraseña nueva, se queda la guardada; STARTTLS → smtp.
    Livewire::test(EmailSettings::class)->callAction('edit', ['password' => '', 'port' => '587', 'encryption' => 'tls'] + $form + ['current_password' => 'password']);
    expect(SmtpSettings::password())->toBe('clave-del-correo')->and(config('mail.mailers.smtp.scheme'))->toBe('smtp')->and(config('mail.mailers.smtp.port'))->toBe(587);
});

it('lo guardado en el panel se aplica al arrancar el correo en cada petición', function () {
    config()->set('mail.mailers.smtp.host', 'del-env.example.com');
    Settings::put(SmtpSettings::KEY, ['host' => 'ssl0.ovh.net', 'port' => '465', 'encryption' => 'ssl', 'password' => encrypt('x', false)]);
    app()->forgetInstance('mail.manager');

    SmtpSettings::apply();

    expect(config('mail.mailers.smtp.host'))->toBe('ssl0.ovh.net')->and(config('mail.mailers.smtp.scheme'))->toBe('smtps');
});

it('«Enviar email de prueba» manda un email para comprobar el correo', function () {
    Livewire::test(EmailSettings::class)->callAction('test', ['to' => 'yo@clavedealma.test'])->assertNotified('Email de prueba enviado a yo@clavedealma.test');

    Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $m): bool => $m->hasTo('yo@clavedealma.test'));
});

it('avisa si el servidor no envía por SMTP, y solo lo ve el superadmin', function () {
    config()->set('mail.default', 'log');
    $this->get(EmailSettings::getUrl())->assertSee('no salen por SMTP');

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get(EmailSettings::getUrl())->assertForbidden();
});
