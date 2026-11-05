<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Infrastructure\Console\CreateSuperadminCommand;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // Emails de «Mi cuenta» en español y con los enlaces de la tienda (ADR 0021).
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Confirma tu email · Clave de Alma')
            ->greeting('Hola')
            ->line('Gracias por crear tu cuenta en Clave de Alma. Confirma que este email es tuyo para ver en «Mi cuenta» también los pedidos que hiciste sin cuenta.')
            ->action('Confirmar mi email', $url)
            ->line('Si no has creado ninguna cuenta, ignora este mensaje.')
            ->salutation('Clave de Alma'));
        ResetPassword::createUrlUsing(fn (object $user, string $token): string => route('account.password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]));
        ResetPassword::toMailUsing(fn (object $notifiable, string $token): MailMessage => (new MailMessage)
            ->subject('Restablecer la contraseña · Clave de Alma')
            ->greeting('Hola')
            ->line('Alguien ha pedido una contraseña nueva para tu cuenta de Clave de Alma.')
            ->action('Elegir una contraseña nueva', route('account.password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('El enlace caduca en '.config('auth.passwords.users.expire', 60).' minutos. Si no lo has pedido tú, ignora este mensaje: tu contraseña no cambia.')
            ->salutation('Clave de Alma'));

        if ($this->app->runningInConsole()) {
            $this->commands([CreateSuperadminCommand::class]);
        }

        // El superadministrador puede hacer cualquier acción autorizada por políticas.
        Gate::before(fn (User $user): ?bool => $user->isSuperadmin() ? true : null);
    }
}
