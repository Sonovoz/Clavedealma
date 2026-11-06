<?php

namespace App\Platform\Backup;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Registro de una copia de seguridad.
 *
 * @property int $id
 * @property string $disk
 * @property ?string $path
 * @property ?string $offsite_disk
 * @property ?string $offsite_path
 * @property ?Carbon $offsite_at
 * @property ?string $offsite_error
 * @property ?int $size_bytes
 * @property string $status
 * @property string $trigger
 * @property ?int $user_id
 * @property ?string $message
 * @property Carbon $started_at
 * @property ?Carbon $finished_at
 */
class Backup extends Model
{
    public const RUNNING = 'running';

    public const OK = 'ok';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'offsite_at' => 'datetime'];
    }

    /** Las copias se suben también fuera del servidor (BACKUP_OFFSITE). */
    public static function offsiteEnabled(): bool
    {
        return (bool) config('clavedealma.backups.offsite');
    }

    public function isOffsite(): bool
    {
        return $this->offsite_at !== null && $this->offsite_path !== null;
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size_bytes;

        return match (true) {
            $bytes >= 1_073_741_824 => number_format($bytes / 1_073_741_824, 1, ',', '.').' GB',
            $bytes >= 1_048_576 => number_format($bytes / 1_048_576, 1, ',', '.').' MB',
            default => number_format($bytes / 1024, 0, ',', '.').' KB',
        };
    }
}
