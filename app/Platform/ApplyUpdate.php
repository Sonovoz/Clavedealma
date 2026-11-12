<?php

namespace App\Platform;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aplica una actualización recién subida (ZIP en Hostinger) sin necesidad de SSH:
 * migraciones pendientes, cachés y enlace de storage.
 */
class ApplyUpdate
{
    /**
     * @return list<array{step: string, ok: bool, output: string}>
     */
    public function __invoke(?int $userId = null): array
    {
        $steps = [
            'Migraciones pendientes' => ['migrate', ['--force' => true]],
            'Limpiar cachés' => ['optimize:clear', []],
            'Enlace de storage' => ['storage:link', []],
            // Estilos y JavaScript del panel (no van en GitHub): sin ellos no se puede entrar.
            'Archivos del panel' => ['filament:assets', []],
        ];

        // Cachear configuración y rutas solo fuera de las pruebas automáticas.
        if (! app()->runningUnitTests()) {
            $steps['Regenerar cachés'] = ['optimize', []];
        }

        $results = [];

        foreach ($steps as $label => [$command, $arguments]) {
            try {
                $exitCode = Artisan::call($command, $arguments);
                $results[] = ['step' => $label, 'ok' => $exitCode === 0, 'output' => $this->clean(Artisan::output())];
            } catch (Throwable $e) {
                $results[] = ['step' => $label, 'ok' => false, 'output' => $e->getMessage()];
            }
        }

        Log::info('Actualización aplicada desde el panel', ['user_id' => $userId, 'results' => $results]);

        return $results;
    }

    /**
     * Quita los puntos de relleno y espacios de la salida de Artisan.
     */
    private function clean(string $output): string
    {
        $output = (string) preg_replace('/\.{3,}|\s+/', ' ', $output);

        return trim((string) preg_replace('/ {2,}/', ' ', $output));
    }
}
