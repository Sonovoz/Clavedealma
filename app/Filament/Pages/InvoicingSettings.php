<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Contracts\VerifactuSender;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceIssuer;
use App\Platform\Legal\LegalGenerator;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * «Datos de facturación»: datos fiscales de Clave de Alma, series y estado de Veri*Factu.
 * Solo superadministradores.
 */
class InvoicingSettings extends Page
{
    protected string $view = 'filament.pages.invoicing-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Ajustes';

    protected static ?string $navigationLabel = 'Datos de facturación';

    protected static ?string $title = 'Datos de facturación';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'datos-de-facturacion';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    protected function getHeaderActions(): array
    {
        $issued = Invoice::query()->exists();

        return [
            Action::make('edit')
                ->label('Editar datos fiscales')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->modalDescription('Se copian en cada factura al emitirla (un cambio no altera las ya emitidas) y con ellos se generan los textos legales de Clave de Alma y de cada tienda.')
                ->fillForm(fn (): array => InvoiceIssuer::current()->only(['legal_name', 'nif', 'address', 'postal_code', 'city', 'province', 'email', 'phone', 'registry_details', 'simplified_series', 'rectifying_series']))
                ->schema([
                    TextInput::make('legal_name')->label('Razón social')->required()->maxLength(255),
                    TextInput::make('nif')->label('NIF')->required()->maxLength(20)->regex('/^[A-Za-z0-9]{8,12}$/'),
                    TextInput::make('address')->label('Domicilio fiscal')->required()->maxLength(255),
                    TextInput::make('postal_code')->label('Código postal')->required()->maxLength(12),
                    TextInput::make('city')->label('Población')->required()->maxLength(120),
                    TextInput::make('province')->label('Provincia')->maxLength(120),
                    TextInput::make('email')->label('Email de contacto y facturación')->email()->maxLength(255)
                        ->helperText('Sale en las facturas y en los textos legales (para ejercer los derechos de privacidad).'),
                    TextInput::make('phone')->label('Teléfono de contacto')->tel()->maxLength(30),
                    Textarea::make('registry_details')->label('Datos registrales')->rows(2)->maxLength(500)
                        ->helperText('Para el aviso legal: Registro Mercantil de…, tomo, folio, hoja e inscripción. Lo indica la gestoría.'),
                    TextInput::make('simplified_series')->label('Serie de facturas simplificadas')->required()->maxLength(10)->alphaNum()
                        ->disabled($issued)->helperText($issued ? 'Ya hay facturas emitidas: la serie no se cambia.' : 'Por ejemplo, T.'),
                    TextInput::make('rectifying_series')->label('Serie de rectificativas')->required()->maxLength(10)->alphaNum()
                        ->disabled($issued)->different('simplified_series')->helperText($issued ? 'Ya hay facturas emitidas: la serie no se cambia.' : 'Por ejemplo, R.'),
                ])
                ->action(function (array $data): void {
                    $clean = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
                    if (isset($clean['nif'])) {
                        $clean['nif'] = strtoupper((string) $clean['nif']);
                    }
                    $issuer = InvoiceIssuer::current();
                    $issuer->update($clean);
                    $changed = array_values(array_diff(array_keys($issuer->getChanges()), ['updated_at']));
                    if ($changed !== []) {
                        SecurityLog::record(SecurityEventType::FiscalDataChanged, 'Datos de facturación cambiados: '.implode(', ', $changed).'.');
                    }
                    // Con los datos fiscales se generan los textos legales de Clave de Alma y de cada tienda.
                    $created = app(LegalGenerator::class)->generateAll();
                    if ($created > 0) {
                        SecurityLog::record(SecurityEventType::LegalGenerated, "Textos legales generados: {$created} versiones nuevas.");
                    }
                    Notification::make()->title('Datos de facturación guardados')
                        ->body($created > 0 ? "Textos legales actualizados: {$created} versiones nuevas, pendientes de revisión (Ajustes → Textos legales)." : 'Los textos legales ya estaban al día.')
                        ->success()->send();
                }),
            Action::make('issuePending')
                ->label('Emitir facturas pendientes')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Emite las facturas de los pedidos cobrados (y las rectificativas de las devoluciones) que quedaron sin factura.')
                ->visible(fn (): bool => InvoiceIssuer::current()->complete())
                ->action(function (): void {
                    Artisan::call('facturas:emitir-pendientes');
                    SecurityLog::record(SecurityEventType::InvoicesIssued, trim(Artisan::output()));
                    Notification::make()->title(trim(Artisan::output()))->success()->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $issuer = InvoiceIssuer::current();

        return [
            'issuer' => $issuer,
            'missing' => $issuer->missing(),
            'aeatConfigured' => app(VerifactuSender::class)->configured(),
            'production' => (bool) config('invoicing.verifactu.production'),
            'verifactuEnabled' => (bool) config('invoicing.verifactu.enabled'),
            'count' => Invoice::query()->count(),
        ];
    }
}
