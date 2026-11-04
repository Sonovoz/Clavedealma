<?php

namespace App\Platform\Backup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Throwable;
use ZipArchive;

/**
 * Hace una copia de seguridad: base de datos completa + archivos subidos (fotos de
 * productos, diseños para producción) en un ZIP con fecha, en el disco de copias.
 * No incluye el código (está en GitHub) ni el .env (contraseñas). Después borra las
 * copias que sobran según la programación. Si falla, avisa por email.
 */
class CreateBackup
{
    /** Carpetas de storage/app/private que no hace falta copiar (temporales). */
    private const SKIP_PRIVATE = ['livewire-tmp', 'importaciones'];

    public function __construct(private readonly DatabaseDumper $dumper) {}

    public function __invoke(string $trigger = 'manual', ?int $userId = null): Backup
    {
        $disk = (string) config('clavedealma.backups.disk');
        $backup = Backup::query()->create([
            'disk' => $disk, 'status' => Backup::RUNNING, 'trigger' => $trigger, 'user_id' => $userId, 'started_at' => now(),
        ]);
        $tmp = storage_path('app/backups-tmp/'.Str::random(12));
        File::ensureDirectoryExists($tmp);

        try {
            $name = 'copia-'.now((string) config('clavedealma.backups.timezone'))->format('Y-m-d-His').'.zip';
            $stats = $this->dumper->dump($tmp.'/base-de-datos.sql');
            $files = $this->zip($tmp.'/'.$name, $tmp.'/base-de-datos.sql', $stats);

            $stream = fopen($tmp.'/'.$name, 'r') ?: throw new RuntimeException('No se puede leer el ZIP.');
            Storage::disk($disk)->writeStream($name, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $backup->update([
                'status' => Backup::OK,
                'path' => $name,
                'size_bytes' => (int) filesize($tmp.'/'.$name),
                'message' => "{$stats['tables']} tablas, {$stats['rows']} registros y {$files} archivos.",
                'finished_at' => now(),
            ]);
            $this->prune(BackupSchedule::get()['keep']);
        } catch (Throwable $e) {
            report($e);
            $backup->update(['status' => Backup::FAILED, 'message' => Str::limit($e->getMessage(), 1000), 'finished_at' => now()]);
            $this->notify('La copia de seguridad ha fallado', 'La copia del '.now((string) config('clavedealma.backups.timezone'))->format('d/m/Y H:i').' no se ha podido hacer: '.$e->getMessage());
        } finally {
            File::deleteDirectory($tmp);
        }

        return $backup->refresh();
    }

    /**
     * @param  array{tables: int, rows: int}  $stats
     */
    private function zip(string $zipPath, string $sqlPath, array $stats): int
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se puede crear el ZIP de la copia.');
        }

        $zip->addFile($sqlPath, 'base-de-datos.sql');
        $files = $this->addFolder($zip, storage_path('app/public'), 'archivos/public', []);
        $files += $this->addFolder($zip, storage_path('app/private'), 'archivos/private', self::SKIP_PRIVATE);

        $zip->addFromString('LEEME.txt', implode("\n", [
            'Copia de seguridad de Clave de Alma · '.now((string) config('clavedealma.backups.timezone'))->format('d/m/Y H:i'),
            "Base de datos: {$stats['tables']} tablas y {$stats['rows']} registros (base-de-datos.sql).",
            "Archivos: {$files} (carpeta archivos/, va en storage/app/ del servidor).",
            'No incluye el código (está en GitHub) ni el .env (contraseñas).',
            'Cómo restaurar: docs/copias-de-seguridad.md del proyecto.',
        ])."\n");

        if (! $zip->close()) {
            throw new RuntimeException('No se ha podido cerrar el ZIP de la copia.');
        }

        return $files;
    }

    /**
     * @param  list<string>  $skip
     */
    private function addFolder(ZipArchive $zip, string $folder, string $prefix, array $skip): int
    {
        if (! is_dir($folder)) {
            return 0;
        }

        $count = 0;
        $finder = Finder::create()->files()->in($folder)->ignoreDotFiles(true)->exclude($skip);
        foreach ($finder as $file) {
            $zip->addFile($file->getPathname(), $prefix.'/'.str_replace('\\', '/', $file->getRelativePathname()));
            $count++;
        }

        return $count;
    }

    /** Conserva las N copias buenas más recientes y borra el resto (archivo y registro). */
    public function prune(int $keep): int
    {
        $old = Backup::query()->where('status', Backup::OK)->orderByDesc('started_at')->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->get();
        foreach ($old as $backup) {
            if ($backup->path !== null) {
                Storage::disk($backup->disk)->delete($backup->path);
            }
            $backup->delete();
        }
        Backup::query()->where('status', Backup::FAILED)->where('started_at', '<', now()->subDays(30))->delete();

        return $old->count();
    }

    public function notify(string $subject, string $body): void
    {
        $email = BackupSchedule::notifyEmail();
        if ($email === null) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new BackupAlert($subject, $body));
        } catch (Throwable $e) {
            report($e); // sin correo configurado: queda en el registro y en el panel
        }
    }
}
