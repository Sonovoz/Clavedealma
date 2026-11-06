<?php

use App\Filament\Pages\Backups;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Backup\Backup;
use App\Platform\Backup\BackupAlert;
use App\Platform\Backup\CreateBackup;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('backups');
    Storage::fake('backups_offsite');
    Notification::fake();
    config()->set('clavedealma.superadmin.email', 'admin@clavedealma.test');
    config()->set('clavedealma.backups.offsite', true);
});

it('cada copia queda en el servidor y, además, fuera (Object Storage)', function () {
    $backup = app(CreateBackup::class)();

    expect($backup->status)->toBe(Backup::OK)->and($backup->isOffsite())->toBeTrue();
    Storage::disk('backups')->assertExists($backup->path);
    Storage::disk('backups_offsite')->assertExists($backup->path);
    expect(Storage::disk('backups_offsite')->size($backup->path))->toBe(Storage::disk('backups')->size($backup->path));

    // Al borrar sobrantes, se borran en los dos sitios.
    app(CreateBackup::class)->prune(0);
    Storage::disk('backups')->assertMissing($backup->path);
    Storage::disk('backups_offsite')->assertMissing($backup->path);
});

it('si Object Storage falla, la copia del servidor vale, se avisa y se puede subir después', function () {
    config()->set('filesystems.disks.broken', ['driver' => 's3', 'key' => 'x', 'secret' => 'y', 'region' => 'gra', 'bucket' => 'copias',
        'endpoint' => 'http://127.0.0.1:1', 'use_path_style_endpoint' => true, 'throw' => true, 'http' => ['connect_timeout' => 1]]);
    config()->set('clavedealma.backups.offsite_disk', 'broken');

    $backup = app(CreateBackup::class)();

    expect($backup->status)->toBe(Backup::OK)->and($backup->isOffsite())->toBeFalse()->and($backup->offsite_error)->not->toBeNull();
    Storage::disk('backups')->assertExists($backup->path);
    Notification::assertSentOnDemand(BackupAlert::class);
    expect(Artisan::call('copias:revisar'))->toBe(1);

    // Arreglado Object Storage, «Subir fuera» desde el panel.
    config()->set('clavedealma.backups.offsite_disk', 'backups_offsite');
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    $this->get(Backups::getUrl())->assertSee('La última copia solo está en el servidor')->assertSee('Fuera del servidor');

    Livewire::test(Backups::class)->callTableAction('uploadOffsite', $backup)->assertNotified('Copia subida fuera del servidor');
    expect($backup->fresh()->isOffsite())->toBeTrue();
    Storage::disk('backups_offsite')->assertExists($backup->path);
    expect(Artisan::call('copias:revisar'))->toBe(0);
});

it('«Probar Object Storage» escribe, lee y borra un archivo de prueba', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    Livewire::test(Backups::class)->callAction('testOffsite')->assertNotified('Object Storage funciona');
    expect(Storage::disk('backups_offsite')->allFiles())->toBe([]);
});

it('sin BACKUP_OFFSITE todo sigue como antes: solo en el servidor', function () {
    config()->set('clavedealma.backups.offsite', false);

    $backup = app(CreateBackup::class)();

    expect($backup->offsite_disk)->toBeNull()->and(Storage::disk('backups_offsite')->allFiles())->toBe([]);
    expect(Artisan::call('copias:revisar'))->toBe(0);
});
