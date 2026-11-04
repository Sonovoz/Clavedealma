<?php

use App\Filament\Pages\Backups;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Backup\Backup;
use App\Platform\Backup\BackupAlert;
use App\Platform\Backup\BackupSchedule;
use App\Platform\Backup\CreateBackup;
use App\Platform\Backup\DatabaseDumper;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('backups');
    config()->set('clavedealma.superadmin.email', 'admin@clavedealma.test');
});

it('hace una copia con la base de datos y los archivos, y se puede restaurar', function () {
    Store::factory()->create(['name' => 'Tienda copiada']);
    $foto = storage_path('app/public/pruebas-copia/foto.txt');
    @mkdir(dirname($foto), 0777, true);
    file_put_contents($foto, 'foto');

    $backup = app(CreateBackup::class)();
    unlink($foto);
    rmdir(dirname($foto));

    expect($backup->status)->toBe(Backup::OK)->and($backup->size_bytes)->toBeGreaterThan(0);
    Storage::disk('backups')->assertExists($backup->path);

    $zipPath = tempnam(sys_get_temp_dir(), 'copia');
    file_put_contents($zipPath, Storage::disk('backups')->get($backup->path));
    $zip = new ZipArchive;
    $zip->open($zipPath);
    expect($zip->locateName('base-de-datos.sql'))->not->toBeFalse()
        ->and($zip->locateName('archivos/public/pruebas-copia/foto.txt'))->not->toBeFalse()
        ->and($zip->locateName('LEEME.txt'))->not->toBeFalse();

    $sql = (string) $zip->getFromName('base-de-datos.sql');
    expect($sql)->toContain('Tienda copiada');

    // Restaurar el volcado en una base vacía devuelve los datos (con SQLite se comprueba aquí;
    // con MariaDB, la CI comprueba que el volcado contiene las tablas y los datos).
    if (DB::connection()->getDriverName() === 'sqlite') {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec($sql);
        expect($pdo->query("select name from stores where name = 'Tienda copiada'")->fetchColumn())->toBe('Tienda copiada');
    } else {
        expect($sql)->toContain('CREATE TABLE `stores`')->toContain('SET FOREIGN_KEY_CHECKS = 0');
    }
    $zip->close();
    unlink($zipPath);
});

it('conserva solo las copias indicadas en la programación', function () {
    BackupSchedule::save(['keep' => 2]);

    foreach (range(1, 3) as $i) {
        $this->travel(1)->days();
        app(CreateBackup::class)();
    }

    expect(Backup::where('status', Backup::OK)->count())->toBe(2)
        ->and(count(Storage::disk('backups')->allFiles()))->toBe(2);
});

it('si la copia falla, lo registra y avisa por email', function () {
    Notification::fake();
    $this->mock(DatabaseDumper::class)->shouldReceive('dump')->andThrow(new RuntimeException('disco lleno'));

    $backup = app(CreateBackup::class)('scheduled');

    expect($backup->status)->toBe(Backup::FAILED)->and($backup->message)->toBe('disco lleno');
    Notification::assertSentOnDemand(BackupAlert::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'admin@clavedealma.test');
});

it('programa la copia según el panel y avisa si se queda antigua', function () {
    expect(BackupSchedule::cron())->toBe('15 3 * * *');

    BackupSchedule::save(['frequency' => 'weekly', 'weekday' => 0, 'time' => '04:30']);
    expect(BackupSchedule::cron())->toBe('30 4 * * 0')
        ->and(BackupSchedule::describe())->toContain('Cada domingo a las 04:30');

    Notification::fake();
    $this->artisan('copias:revisar')->assertFailed(); // no hay ninguna copia
    Notification::assertSentOnDemand(BackupAlert::class);

    BackupSchedule::save(['enabled' => false]);
    $this->artisan('copias:crear --programada')->expectsOutputToContain('desactivadas')->assertSuccessful();
    expect(Backup::count())->toBe(0);
});

it('la página de copias es solo del superadministrador y hace copias al momento', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())->get('/admin/copias-de-seguridad')->assertForbidden();

    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    $this->get('/admin/copias-de-seguridad')->assertOk()->assertSee('Todavía no hay ninguna copia buena');

    Livewire::test(Backups::class)->callAction('backupNow')->assertNotified('Copia hecha');
    expect(Backup::where('status', Backup::OK)->count())->toBe(1);

    Livewire::test(Backups::class)
        ->callAction('schedule', ['enabled' => true, 'frequency' => 'daily', 'time' => '02:45', 'keep' => 7, 'email' => ''])
        ->assertNotified('Programación guardada');
    expect(BackupSchedule::get())->toMatchArray(['time' => '02:45', 'keep' => 7]);
});
