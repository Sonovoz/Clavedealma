<?php

namespace App\Http\Middleware;

use App\Platform\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras la web no esté instalada, todo lleva al instalador (/instalar), y el instalador usa
 * sesiones y caché en archivos (aún no hay tablas). Una vez instalada, el instalador no existe.
 */
class RedirectToInstaller
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        $installer = $request->is('instalar', 'instalar/*');

        if ($this->state->installed()) {
            // Solo la página final, justo después de instalar.
            abort_if($installer && ! $request->is('instalar/listo'), 404);

            return $next($request);
        }

        if ($request->is('up')) {
            return $next($request);
        }
        config(['session.driver' => 'file', 'cache.default' => 'file']);

        return $installer ? $next($request) : redirect('/instalar');
    }
}
