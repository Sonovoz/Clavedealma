<?php

use App\Platform\Monitoring\ErrorAlerts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de Traefik (Docker/OVH) la web llega por HTTP desde el proxy: se confía en él
        // para saber que es HTTPS y la IP real del cliente. Solo si TRUSTED_PROXIES lo dice
        // (en Hostinger no hay proxy y nadie podría falsear la IP).
        $proxies = env('TRUSTED_PROXIES');
        if (is_string($proxies) && $proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        // Durante el mantenimiento el panel sigue accesible para poder actualizar.
        $middleware->preventRequestsDuringMaintenance(except: [
            'admin',
            'admin/*',
            'livewire*',
        ]);
        // Los avisos de la pasarela llegan firmados por ella, no con el token del formulario.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        // Sin sesión, «Mi cuenta» pide acceder (el panel tiene su propio acceso).
        $middleware->redirectGuestsTo(fn (): string => route('account.show'));
        $middleware->redirectUsersTo(fn (): string => route('account.show'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Aviso por email al equipo de cada error nuevo (ERROR_ALERTS, con límite por hora).
        $exceptions->report(fn (Throwable $e) => ErrorAlerts::report($e));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
