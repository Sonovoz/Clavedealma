<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Infrastructure\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Verificación en dos pasos obligatoria solo para quien la debe usar (ADR 0028): el equipo
 * del panel. A los artistas no se les obliga, aunque pueden activarla en «Mi perfil».
 */
class EnsureTwoFactorForStaff
{
    public function __construct(private readonly EnsureMultiFactorAuthenticationIsEnabled $ensure) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! $user->mustUseTwoFactor()) {
            return $next($request);
        }

        return $this->ensure->handle($request, $next);
    }
}
