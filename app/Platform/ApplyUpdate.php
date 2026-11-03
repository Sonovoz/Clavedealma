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
        ];

        // Cachear configuración y rutas solo fuera de las pruebas automáticas.
        if (! app()->runningUnitTests()) {
            $steps['Regenerar cachés'] = ['optimize', []];
        }

        $results = [];

        foreach ($steps as $label => [$command, $arguments]) {
            try {
                $exitCode = Artisan::call($command, $arguments);
                $results[] = ['step' => $label, 'ok' => $exitCode === 0, 'output' => trim(Artisan::output())];
            } catch (Throwable $e) {
                $results[] = ['step' => $label, 'ok' => false, 'output' => $e->getMessage()];
            }
        }

        Log::info('Actualización aplicada desde el panel', ['user_id' => $userId, 'results' => $results]);

        return $results;
    }
}
