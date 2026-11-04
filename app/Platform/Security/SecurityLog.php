<?php

namespace App\Platform\Security;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anota en el registro de seguridad quién hizo qué, cuándo y desde dónde. Nunca guarda
 * contraseñas, tokens ni datos de tarjetas. Si falla, no interrumpe la acción.
 */
final class SecurityLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(SecurityEventType $type, string $description, ?string $subject = null, array $context = [], ?Authenticatable $user = null, ?string $email = null): void
    {
        try {
            $user ??= auth()->user();
            $request = app()->runningInConsole() ? null : request();

            SecurityEvent::query()->create([
                'type' => $type,
                'level' => $type->level(),
                'user_id' => $user?->getAuthIdentifier(),
                'user_email' => $email ?? ($user instanceof User ? $user->email : null),
                'ip' => $request?->ip(),
                'user_agent' => $request === null ? 'consola' : mb_substr((string) $request->userAgent(), 0, 255),
                'subject' => $subject === null ? null : mb_substr($subject, 0, 120),
                'description' => mb_substr($description, 0, 500),
                'context' => $context === [] ? null : $context,
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo escribir en el registro de seguridad: '.$e->getMessage());
        }
    }
}
