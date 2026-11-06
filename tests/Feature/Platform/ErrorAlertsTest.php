<?php

use App\Filament\Pages\System;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Monitoring\ErrorAlertNotification;
use App\Platform\Monitoring\ErrorAlerts;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    File::deleteDirectory(storage_path('framework/error-alerts'));
    config()->set('clavedealma.errors', ['alerts' => true, 'email' => 'tecnico@clavedealma.test']);
});

afterEach(fn () => File::deleteDirectory(storage_path('framework/error-alerts')));

it('avisa por email de un error, una sola vez por hora, y sin datos de clientes', function () {
    $error = new RuntimeException('No se pudo enviar el pedido de ana@example.com con el token '.str_repeat('a', 48));

    report($error);
    report($error);

    Notification::assertSentOnDemandTimes(ErrorAlertNotification::class, 1);
    Notification::assertSentOnDemand(ErrorAlertNotification::class, function (ErrorAlertNotification $n, array $channels, AnonymousNotifiable $to): bool {
        $mail = $n->toMail($to);
        $text = implode(' ', array_merge([$mail->subject], $mail->introLines));

        return $to->routes['mail'] === 'tecnico@clavedealma.test'
            && str_contains($text, 'RuntimeException') && str_contains($text, 'ErrorAlertsTest.php')
            && ! str_contains($text, 'ana@example.com') && ! str_contains($text, str_repeat('a', 48));
    });
});

it('no manda nunca el SQL (puede llevar datos de clientes)', function () {
    $sql = new QueryException('mariadb', 'insert into orders (email) values (?)', ['ana@example.com'], new PDOException('SQLSTATE[23000]: duplicado'));

    expect(ErrorAlerts::summary($sql)['message'])->not->toContain('insert into')->not->toContain('ana@example.com')->toContain('consulta SQL omitida');
});

it('nunca más de 10 avisos a la hora, aunque sean errores distintos', function () {
    foreach (range(1, 15) as $i) {
        // Quince errores distintos (cada uno «en otra línea»).
        $error = new RuntimeException("Error {$i}");
        (new ReflectionProperty(Exception::class, 'line'))->setValue($error, 1000 + $i);
        report($error);
    }

    Notification::assertSentOnDemandTimes(ErrorAlertNotification::class, ErrorAlerts::MAX_PER_HOUR);
});

it('los errores normales (404, validación) no avisan, y sin ERROR_ALERTS tampoco', function () {
    $this->get('/no-existe-esta-pagina')->assertNotFound();
    Notification::assertNothingSent();

    config()->set('clavedealma.errors.alerts', false);
    report(new RuntimeException('Algo'));
    Notification::assertNothingSent();
});

it('«Estado del sistema» dice si están activados y envía un aviso de prueba', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    $this->get(System::getUrl())->assertSee('Avisos de errores por email')->assertSee('Activados, a tecnico@clavedealma.test');
    Livewire::test(System::class)->callAction('testErrorAlert')->assertNotified('Aviso de prueba enviado');

    Notification::assertSentOnDemand(ErrorAlertNotification::class, fn (ErrorAlertNotification $n): bool => $n->summary['title'] === 'Aviso de prueba');
});
