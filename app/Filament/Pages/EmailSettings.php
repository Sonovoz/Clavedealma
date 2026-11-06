<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Mail\SmtpSettings;
use App\Platform\Mail\SmtpTestMail;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * «Ajustes → Correo» (ADR 0029): el servidor SMTP por el que salen todos los emails de la
 * web. La contraseña se guarda cifrada y no se vuelve a mostrar; cada cambio pide la
 * contraseña del superadministrador y queda en el registro de seguridad.
 */
class EmailSettings extends Page
{
    protected string $view = 'filament.pages.email-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Ajustes';

    protected static ?string $navigationLabel = 'Correo';

    protected static ?string $title = 'Correo · SMTP';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'correo';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Editar el correo')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalHeading('Servidor de correo (SMTP)')
                ->modalDescription('Los da tu proveedor de correo. Con el de OVH: ssl0.ovh.net, puerto 465, SSL/TLS, y como usuario la dirección completa.')
                ->modalSubmitActionLabel('Guardar')
                ->fillForm(fn (): array => [
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => (string) config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
                    'username' => config('mail.mailers.smtp.username'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                ])
                ->schema([
                    TextInput::make('host')->label('Servidor SMTP')->required()->maxLength(190)->placeholder('ssl0.ovh.net'),
                    TextInput::make('port')->label('Puerto')->required()->integer()->minValue(1)->maxValue(65535),
                    Select::make('encryption')->label('Cifrado')->required()
                        ->options(['ssl' => 'SSL/TLS (normalmente puerto 465)', 'tls' => 'STARTTLS (normalmente puerto 587)']),
                    TextInput::make('username')->label('Usuario')->required()->maxLength(190)->helperText('Normalmente, la dirección de correo completa.'),
                    TextInput::make('password')->label('Contraseña del correo')->password()
                        ->placeholder(SmtpSettings::password() !== null ? '•••••••• guardada: déjala vacía para no cambiarla' : '')
                        ->required(fn (): bool => SmtpSettings::password() === null && blank(config('mail.mailers.smtp.password'))),
                    TextInput::make('from_address')->label('Remitente (email)')->email()->required()->helperText('Desde qué dirección llegan los emails. Mejor la misma que el usuario.'),
                    TextInput::make('from_name')->label('Nombre del remitente')->required()->maxLength(80),
                    $this->passwordField(),
                ])
                ->action(function (array $data): void {
                    $changed = SmtpSettings::save($data);
                    if ($changed !== []) {
                        SecurityLog::record(SecurityEventType::MailSettingsChanged, 'Correo SMTP cambiado: '.implode(', ', $changed).'.');
                    }
                    Notification::make()->title('Correo guardado')->body('Pulsa «Enviar email de prueba» para comprobarlo.')->success()->send();
                }),

            Action::make('test')
                ->label('Enviar email de prueba')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalSubmitActionLabel('Enviar')
                ->fillForm(fn (): array => ['to' => auth()->user()?->getAttribute('email')])
                ->schema([TextInput::make('to')->label('Enviar a')->email()->required()])
                ->action(function (array $data): void {
                    try {
                        Mail::to((string) $data['to'])->send(new SmtpTestMail);
                        Notification::make()->title('Email de prueba enviado a '.$data['to'])->body('Mira la bandeja de entrada y la de spam.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('No se ha podido enviar')->body(Str::limit($e->getMessage(), 300))->danger()->persistent()->send();
                    }
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'smtp' => SmtpSettings::usesSmtp(),
            'mailer' => (string) config('mail.default'),
            'source' => SmtpSettings::source(),
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (string) config('mail.mailers.smtp.port'),
            'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'SSL/TLS' : 'STARTTLS (o sin cifrar si el servidor no lo ofrece)',
            'username' => (string) config('mail.mailers.smtp.username'),
            'hasPassword' => SmtpSettings::password() !== null || filled(config('mail.mailers.smtp.password')),
            'from' => config('mail.from.name').' <'.config('mail.from.address').'>',
        ];
    }

    private function passwordField(): TextInput
    {
        return TextInput::make('current_password')->label('Tu contraseña (la del panel)')->password()->required()
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $user = auth()->user();
                if (! $user instanceof User || ! Hash::check((string) $value, (string) $user->getAuthPassword())) {
                    $fail('La contraseña no es correcta.');
                }
            });
    }
}
