<?php

use App\Platform\ApplyUpdate;
use App\Platform\SystemStatus;

it('informa del estado de la instalación', function () {
    $status = app(SystemStatus::class);

    expect($status->databaseIsReachable())->toBeTrue()
        ->and($status->pendingMigrations())->toBe(0)
        ->and($status->drivers())->toHaveKeys(['Base de datos', 'Caché', 'Colas']);
});

it('aplica la actualización y registra cada paso', function () {
    $results = app(ApplyUpdate::class)(userId: 1);

    expect($results)->not->toBeEmpty()
        ->and(collect($results)->firstWhere('step', 'Migraciones pendientes')['ok'])->toBeTrue();
});

it('mantiene el panel accesible durante el mantenimiento', function () {
    $this->artisan('down')->assertSuccessful();

    try {
        $this->get('/')->assertStatus(503);
        $this->get('/admin/login')->assertOk();
    } finally {
        $this->artisan('up');
    }
});
