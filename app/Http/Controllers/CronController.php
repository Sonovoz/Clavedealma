<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * Tareas programadas lanzadas por una dirección secreta (ADR 0030), para servidores en los que
 * no se puede poner un cron: un servicio externo la llama cada minuto. La clave es CRON_TOKEN.
 */
class CronController
{
    public function __invoke(string $token): Response
    {
        $expected = (string) config('clavedealma.cron_token');
        abort_if($expected === '' || ! hash_equals($expected, $token), 404);

        @set_time_limit(280);
        Artisan::call('schedule:run');

        return response('ok '.now()->toIso8601String(), 200, ['Content-Type' => 'text/plain', 'X-Robots-Tag' => 'noindex']);
    }
}
