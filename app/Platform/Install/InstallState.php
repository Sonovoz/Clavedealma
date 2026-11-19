<?php

namespace App\Platform\Install;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ¿Está instalada la web? (ADR 0030). Lo dice el archivo storage/app/instalado, que escribe el
 * instalador. Una instalación anterior al instalador (con las tablas ya creadas) se reconoce
 * sola y se marca. Mientras haya marca, el instalador no se abre nunca: aunque la base de
 * datos falle un rato, nadie puede volver a configurarla desde la web.
 */
class InstallState
{
    private ?bool $installed = null;

    public function __construct(protected readonly string $marker) {}

    public function installed(): bool
    {
        if ($this->installed !== null) {
            return $this->installed;
        }
        if (is_file($this->marker)) {
            return $this->installed = true;
        }

        try {
            $migrated = Schema::hasTable('migrations') && Schema::hasTable('users');
        } catch (Throwable) {
            $migrated = false;
        }
        if ($migrated && ! app()->runningUnitTests()) {
            $this->markInstalled();
        }

        return $this->installed = $migrated;
    }

    public function markInstalled(): void
    {
        @mkdir(dirname($this->marker), 0755, true);
        file_put_contents($this->marker, 'Instalado el '.now()->toIso8601String()."\n");
        $this->installed = true;
    }

    public function forget(): void
    {
        $this->installed = null;
    }
}
