<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\Refund;
use App\Platform\Legal\LegalDocument;
use App\Platform\Legal\LegalPage;
use App\Platform\Payments\RedsysSettings;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use App\Platform\Settings\Settings;
use App\Support\Money;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/**
 * «Ajustes → Pagos», como en WooCommerce (ADR 0027): el TPV de Redsys con sus datos, sus
 * métodos (tarjeta y Bizum), el entorno (pruebas o real), los pasos para ponerlo en marcha y
 * los últimos cobros. Solo superadministradores; cada cambio pide la contraseña y queda en el
 * registro de seguridad.
 */
class PaymentSettings extends Page
{
    protected string $view = 'filament.pages.payment-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Ajustes';

    protected static ?string $navigationLabel = 'Pagos';

    protected static ?string $title = 'Pagos · Redsys';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'pagos';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    protected function getHeaderActions(): array
    {
        $config = (array) config('payments.redsys');
        $live = ($config['environment'] ?? 'test') === 'live';

        return [
            Action::make('edit')
                ->label('Editar datos del TPV')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalHeading('Datos del TPV de Redsys')
                ->modalDescription('Los da el banco al contratar el TPV virtual. La clave secreta se guarda cifrada y no se vuelve a mostrar.')
                ->modalSubmitActionLabel('Guardar')
                ->fillForm(fn (): array => [
                    'merchant_code' => $config['merchant_code'] ?? '',
                    'terminal' => $config['terminal'] ?? '1',
                    'merchant_name' => $config['merchant_name'] ?? 'Clave de Alma',
                    'card' => (bool) ($config['card'] ?? true),
                    'bizum' => (bool) ($config['bizum'] ?? false),
                ])
                ->schema([
                    TextInput::make('merchant_code')->label('Código de comercio (FUC)')->required()->regex('/^\d{9}$/')
                        ->validationMessages(['regex' => 'El código de comercio son 9 cifras.'])->helperText('9 cifras. Viene en la carta o el email del banco.'),
                    TextInput::make('terminal')->label('Terminal')->required()->regex('/^\d{1,3}$/')
                        ->validationMessages(['regex' => 'El terminal es un número (normalmente 1).'])->helperText('Normalmente 1.'),
                    TextInput::make('secret')->label('Clave secreta SHA-256')->password()
                        ->placeholder(RedsysSettings::sources()['secret'] !== null ? '•••••••• guardada: déjala vacía para no cambiarla' : 'La da el banco')
                        ->required(fn (): bool => RedsysSettings::sources()['secret'] === null)
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled($value) && ! RedsysSettings::validSecret((string) $value)) {
                                $fail('La clave SHA-256 del TPV tiene 32 caracteres (letras, números, «+» o «/»). Cópiala tal cual la da el banco.');
                            }
                        }),
                    TextInput::make('merchant_name')->label('Nombre del comercio')->required()->maxLength(25)
                        ->helperText('Lo que ve el cliente en la página de pago de Redsys.'),
                    Toggle::make('card')->label('Tarjeta de crédito o débito')->helperText('Método de pago «Tarjeta» en Finalizar compra.')
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (! $value && ! $get('bizum')) {
                                $fail('Deja activo al menos un método de pago.');
                            }
                        }),
                    Toggle::make('bizum')->label('Bizum')->helperText('Actívalo solo cuando el banco te confirme que Bizum está dado de alta en el TPV.'),
                    $this->passwordField(),
                ])
                ->action(function (array $data): void {
                    $changed = RedsysSettings::save($data);
                    $this->refreshGateway();
                    if ($changed !== []) {
                        SecurityLog::record(SecurityEventType::PaymentSettingsChanged, 'Datos del TPV de Redsys cambiados: '.implode(', ', $changed).'.');
                    }
                    Notification::make()->title('Datos del TPV guardados')->success()->send();
                }),

            Action::make('testMerchant')
                ->label('Usar el comercio de pruebas de Redsys')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->visible(! $live)
                ->modalDescription('Rellena los datos públicos de pruebas de Redsys (código 999008881, terminal 1) para probar la compra sin el TPV del banco. No se cobra nada. Cuando el banco te dé los datos, cámbialos con «Editar datos del TPV».')
                ->schema([$this->passwordField()])
                ->modalSubmitActionLabel('Usar el comercio de pruebas')
                ->action(function (): void {
                    RedsysSettings::save(RedsysSettings::TEST_MERCHANT + ['environment' => 'test']);
                    $this->refreshGateway();
                    SecurityLog::record(SecurityEventType::PaymentSettingsChanged, 'TPV de Redsys: comercio público de pruebas.');
                    Notification::make()->title('Comercio de pruebas de Redsys puesto')->body('Prueba una compra con la tarjeta 4548 8100 0000 0003, caducidad 12/49, CVV 123.')->success()->send();
                }),

            Action::make('environment')
                ->label($live ? 'Volver a modo de pruebas' : 'Pasar a cobros reales')
                ->icon($live ? Heroicon::OutlinedBeaker : Heroicon::OutlinedBolt)
                ->color($live ? 'gray' : 'danger')
                ->modalHeading($live ? 'Volver a modo de pruebas' : 'Pasar a cobros reales')
                ->modalDescription($live
                    ? 'Los pagos dejan de cobrarse de verdad y los pedidos nuevos se marcan como compras de prueba.'
                    : 'Desde ahora los pagos se cobran de verdad en sis.redsys.es. Hazlo solo con los datos reales del banco, después de probar una compra y una devolución en modo de pruebas.')
                ->schema([$this->passwordField()])
                ->modalSubmitActionLabel($live ? 'Volver a pruebas' : 'Pasar a cobros reales')
                ->action(function () use ($live): void {
                    if (! $live && (! RedsysSettings::configured() || RedsysSettings::usingTestMerchant())) {
                        Notification::make()->title('Faltan los datos reales del TPV')->body('Escribe el código de comercio, el terminal y la clave que te ha dado el banco («Editar datos del TPV»).')->danger()->send();

                        return;
                    }
                    RedsysSettings::save(['environment' => $live ? 'test' : 'live']);
                    $this->refreshGateway();
                    SecurityLog::record(SecurityEventType::PaymentSettingsChanged, $live ? 'TPV de Redsys en modo de pruebas.' : 'TPV de Redsys en cobros reales.');
                    Notification::make()->title($live ? 'Modo de pruebas' : 'Cobros reales activados')->success()->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $config = (array) config('payments.redsys');
        $sources = RedsysSettings::sources();
        $live = ($config['environment'] ?? 'test') === 'live';
        $configured = RedsysSettings::configured();
        $lastNotice = Settings::get(RedsysSettings::LAST_NOTICE);
        $paidTest = Payment::query()->where('provider', 'redsys')->where('status', PaymentStatus::Succeeded)->exists();
        $refunded = Refund::query()->where('status', Refund::SUCCEEDED)->whereIn('payment_id', Payment::query()->select('id')->where('provider', 'redsys'))->exists();
        $legalFresh = str_contains((string) LegalDocument::current(LegalPage::Privacy, null)?->content, 'Redsys');

        $payments = Payment::query()->where('provider', 'redsys')->latest('id')->limit(10)->get();
        $references = Order::query()->whereIn('id', $payments->pluck('order_id'))->pluck('reference', 'id');

        return [
            'active' => app(PaymentGateway::class)->name() === 'redsys',
            'driver' => (string) config('payments.driver'),
            'config' => $config,
            'sources' => $sources,
            'live' => $live,
            'configured' => $configured,
            'testMerchant' => RedsysSettings::usingTestMerchant(),
            'notifyUrl' => route('payments.webhook.redsys'),
            'lastNotice' => is_string($lastNotice) ? Carbon::parse($lastNotice)->timezone('Europe/Madrid')->format('d/m/Y H:i') : null,
            'steps' => [
                ['done' => $configured && ! RedsysSettings::usingTestMerchant(), 'title' => 'Contrata el TPV virtual con tu banco', 'text' => 'Pide «TPV virtual Redsys» para la tienda online. Pide también: Bizum, «Parámetros en las URLs: Sí» y, si los tienen, Apple Pay y Google Pay.'],
                ['done' => $lastNotice !== null, 'title' => 'Da al banco la URL de notificación', 'text' => 'Es la dirección a la que Redsys avisa de cada cobro. Está más abajo, lista para copiar. Que la configuren como notificación «HTTP» (en línea).'],
                ['done' => $configured, 'title' => 'Escribe aquí los datos del TPV', 'text' => 'Código de comercio, terminal y clave secreta («Editar datos del TPV»). Mientras no los tengas, puedes probar con «Usar el comercio de pruebas de Redsys».'],
                ['done' => $paidTest, 'title' => 'Haz una compra de prueba', 'text' => 'Con el entorno en pruebas, compra con la tarjeta 4548 8100 0000 0003 (caducidad 12/49, CVV 123). El pedido debe quedar pagado y aquí debe aparecer «Último aviso del banco».'],
                ['done' => $refunded, 'title' => 'Prueba una devolución', 'text' => 'Cancela ese pedido desde Comercio → Pedidos («Cancelar y devolver el importe»): el dinero se devuelve por Redsys.'],
                ['done' => (bool) ($config['bizum'] ?? false), 'title' => 'Activa Bizum', 'text' => 'Cuando el banco confirme que Bizum está dado de alta, actívalo en «Editar datos del TPV».'],
                ['done' => $live, 'title' => 'Pasa a cobros reales', 'text' => 'Con los datos reales del banco, pulsa «Pasar a cobros reales». Haz una compra pequeña de verdad y devuélvela.'],
                ['done' => $legalFresh, 'title' => 'Revisa los textos legales', 'text' => 'En Ajustes → Textos legales, pulsa «Regenerar textos» para que digan Redsys y Bizum, y envíalos a la asesoría.'],
            ],
            'payments' => $payments->map(fn (Payment $p): array => [
                'date' => $p->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i'),
                'order' => $references[$p->order_id] ?? '#'.$p->order_id,
                'number' => (string) $p->provider_reference,
                'method' => $p->method === 'bizum' ? 'Bizum' : 'Tarjeta',
                'amount' => Money::fromCents($p->amount_cents)->format(),
                'status' => $p->status->label(),
                'ok' => $p->status === PaymentStatus::Succeeded,
                'reason' => $p->failure_message,
            ])->all(),
        ];
    }

    /** Contraseña del superadministrador para cambiar algo del cobro. */
    private function passwordField(): TextInput
    {
        return TextInput::make('current_password')->label('Tu contraseña')->password()->required()
            ->helperText('Por seguridad, cualquier cambio en los cobros pide tu contraseña y queda en el registro de seguridad.')
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $user = auth()->user();
                if (! $user instanceof User || ! Hash::check((string) $value, (string) $user->getAuthPassword())) {
                    $fail('La contraseña no es correcta.');
                }
            });
    }

    /** La pasarela se vuelve a crear con los datos nuevos. */
    private function refreshGateway(): void
    {
        app()->forgetInstance(PaymentGateway::class);
    }
}
