<?php

namespace App\Platform\Security;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;

/**
 * Qué se anota solo: accesos, fallos, bloqueos y cambios en las cuentas de usuario.
 * Las acciones sensibles del panel (devolver dinero, copias…) se anotan en cada acción.
 */
final class SecurityListeners
{
    public static function register(): void
    {
        Event::listen(Login::class, fn (Login $e) => SecurityLog::record(SecurityEventType::Login, 'Ha entrado en el panel.', user: $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user === null ? null : SecurityLog::record(SecurityEventType::Logout, 'Ha salido del panel.', user: $e->user));
        Event::listen(Failed::class, fn (Failed $e) => SecurityLog::record(
            SecurityEventType::LoginFailed,
            $e->user === null ? 'Intento de acceso con un email que no existe.' : 'Contraseña incorrecta.',
            user: $e->user,
            email: is_string($e->credentials['email'] ?? null) ? mb_substr($e->credentials['email'], 0, 190) : null,
        ));
        Event::listen(Lockout::class, fn (Lockout $e) => SecurityLog::record(SecurityEventType::Lockout, 'Demasiados intentos: acceso bloqueado un rato.', email: is_string($e->request->input('email')) ? mb_substr((string) $e->request->input('email'), 0, 190) : null));
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => SecurityLog::record(SecurityEventType::PasswordReset, 'Contraseña restablecida con el enlace de recuperación.', user: $e->user));

        User::created(fn (User $u) => SecurityLog::record(SecurityEventType::UserCreated, 'Usuario creado: '.$u->email.'.', subject: 'Usuario #'.$u->id));
        User::updated(function (User $u): void {
            if ($u->wasChanged('password')) {
                SecurityLog::record(SecurityEventType::PasswordChanged, 'Contraseña cambiada de '.$u->email.'.', subject: 'Usuario #'.$u->id);
            }
            $fields = array_values(array_diff(array_keys($u->getChanges()), ['password', 'remember_token', 'updated_at']));
            if ($fields !== []) {
                SecurityLog::record(SecurityEventType::UserUpdated, 'Usuario '.$u->email.' modificado: '.implode(', ', $fields).'.', subject: 'Usuario #'.$u->id);
            }
        });
        User::deleted(fn (User $u) => SecurityLog::record(SecurityEventType::UserDeleted, 'Usuario borrado: '.$u->email.'.', subject: 'Usuario #'.$u->id));
    }
}
