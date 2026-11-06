<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Minimedia\CloseMinimediaMonth;
use App\Platform\Minimedia\MinimediaCommission;
use App\Platform\Minimedia\MinimediaStatement;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use App\Platform\Settings\Settings;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * «Minimedia» (ADR 0029): la comisión de Minimedia sobre las ventas de todas las tiendas.
 * Lo que va del mes, los cierres mensuales (con PDF, CSV y el email del día 1) y los
 * ajustes (porcentaje y email). Solo superadministradores.
 */
class Minimedia extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.minimedia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Minimedia';

    protected static ?string $navigationLabel = 'Comisión Minimedia';

    protected static ?string $title = 'Minimedia';

    protected static ?string $slug = 'minimedia';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $now = CarbonImmutable::now(MinimediaCommission::TZ);
        $current = MinimediaCommission::compute(MinimediaCommission::monthKey($now));
        $m = fn (int $cents): string => Money::fromCents($cents)->format();

        return [
            'current' => $current,
            'money' => $m,
            'rate' => CloseMinimediaMonth::rate(MinimediaCommission::rateBps()),
            'email' => MinimediaCommission::email(),
            'nextSend' => $now->startOfMonth()->addMonthNoOverflow()->translatedFormat('j \d\e F \d\e Y'),
            'unsent' => MinimediaStatement::query()->whereNull('sent_at')->whereNotNull('send_error')->count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MinimediaStatement::query())
            ->defaultSort('month', 'desc')
            ->heading('Cierres mensuales')
            ->description('Se cierran solos el día 1 y se envían a Minimedia. Las cifras de un mes cerrado no cambian.')
            ->emptyStateHeading('Todavía no hay meses cerrados')
            ->emptyStateDescription('El primero se cerrará el día 1 del mes que viene.')
            ->columns([
                TextColumn::make('month')->label('Mes')->formatStateUsing(fn (MinimediaStatement $record): string => $record->label())->sortable(),
                TextColumn::make('orders')->label('Pedidos')->alignEnd(),
                TextColumn::make('net_base_cents')->label('Neto sin IVA')->formatStateUsing(fn (int $state): string => Money::fromCents($state)->format())->alignEnd(),
                TextColumn::make('rate_bps')->label('%')->formatStateUsing(fn (int $state): string => CloseMinimediaMonth::rate($state))->alignEnd(),
                TextColumn::make('commission_cents')->label('Comisión')->formatStateUsing(fn (int $state): string => Money::fromCents($state)->format())->weight('bold')->alignEnd(),
                TextColumn::make('sent_at')->label('Email')
                    ->state(fn (MinimediaStatement $record): string => $record->sent_at !== null
                        ? 'Enviado el '.$record->sent_at->timezone(MinimediaCommission::TZ)->format('d/m/Y H:i').' a '.$record->sent_to
                        : ($record->send_error !== null ? 'No se ha podido enviar' : 'Pendiente'))
                    ->color(fn (MinimediaStatement $record): string => $record->sent_at !== null ? 'success' : 'danger')
                    ->tooltip(fn (MinimediaStatement $record): ?string => $record->send_error)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                    ->action(fn (MinimediaStatement $record, CloseMinimediaMonth $close): StreamedResponse => response()->streamDownload(
                        function () use ($close, $record): void {
                            echo $close->pdf($record);
                        }, 'minimedia-'.$record->month.'.pdf', ['Content-Type' => 'application/pdf'])),
                Action::make('csv')->label('CSV')->icon(Heroicon::OutlinedTableCells)->color('gray')
                    ->action(fn (MinimediaStatement $record): StreamedResponse => response()->streamDownload(
                        function () use ($record): void {
                            echo CloseMinimediaMonth::csv($record);
                        }, 'minimedia-'.$record->month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
                Action::make('resend')->label('Enviar')->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->modalDescription(fn (MinimediaStatement $record): string => 'Envía el resumen de '.mb_strtolower($record->label()).' a '.MinimediaCommission::email().', con el PDF y el CSV.')
                    ->action(function (MinimediaStatement $record, CloseMinimediaMonth $close): void {
                        $ok = $close->send($record);
                        SecurityLog::record(SecurityEventType::MinimediaChanged, 'Resumen de Minimedia de '.$record->label().($ok ? ' enviado a '.MinimediaCommission::email() : ': falló el envío').'.');
                        $ok
                            ? Notification::make()->title('Resumen enviado a '.MinimediaCommission::email())->success()->send()
                            : Notification::make()->title('No se ha podido enviar')->body((string) $record->fresh()?->send_error)->danger()->persistent()->send();
                    }),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $previous = CloseMinimediaMonth::previousMonth();

        return [
            Action::make('closePrevious')
                ->label('Cerrar '.mb_strtolower(MinimediaCommission::label($previous)))
                ->icon(Heroicon::OutlinedLockClosed)
                ->visible(fn (): bool => ! MinimediaStatement::query()->where('month', $previous)->exists())
                ->requiresConfirmation()
                ->modalDescription('Guarda las cifras de '.mb_strtolower(MinimediaCommission::label($previous)).' (ya no cambiarán) y envía el resumen a Minimedia. Se hace solo el día 1; úsalo si no se hizo.')
                ->action(function (CloseMinimediaMonth $close) use ($previous): void {
                    $statement = $close->close($previous);
                    $ok = $close->send($statement);
                    SecurityLog::record(SecurityEventType::MinimediaChanged, 'Cierre de Minimedia de '.$statement->label().' a mano'.($ok ? ' y enviado.' : '; falló el envío.'));
                    $ok
                        ? Notification::make()->title($statement->label().' cerrado y enviado')->success()->send()
                        : Notification::make()->title($statement->label().' cerrado')->body('El email no ha salido: revisa Ajustes → Correo y pulsa «Enviar».')->warning()->send();
                }),

            Action::make('settings')
                ->label('Ajustes')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->modalHeading('Ajustes de Minimedia')
                ->modalSubmitActionLabel('Guardar')
                ->fillForm(fn (): array => ['rate' => str_replace('.', ',', (string) (MinimediaCommission::rateBps() / 100)), 'email' => MinimediaCommission::email()])
                ->schema([
                    TextInput::make('rate')->label('Porcentaje de la comisión')->suffix('%')->required()
                        ->regex('/^\d{1,2}([.,]\d{1,2})?$/')->validationMessages(['regex' => 'Escribe un porcentaje, por ejemplo 6 o 6,5.'])
                        ->helperText('Se aplica a los meses que se cierren a partir de ahora. Los ya cerrados no cambian.'),
                    TextInput::make('email')->label('Enviar el resumen mensual a')->email()->required(),
                    TextInput::make('current_password')->label('Tu contraseña')->password()->required()
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $user = auth()->user();
                            if (! $user instanceof User || ! Hash::check((string) $value, (string) $user->getAuthPassword())) {
                                $fail('La contraseña no es correcta.');
                            }
                        }),
                ])
                ->action(function (array $data): void {
                    $bps = (int) round((float) str_replace(',', '.', (string) $data['rate']) * 100);
                    Settings::put(MinimediaCommission::KEY, ['rate_bps' => min($bps, 10000), 'email' => trim((string) $data['email'])]);
                    SecurityLog::record(SecurityEventType::MinimediaChanged, 'Ajustes de Minimedia: '.CloseMinimediaMonth::rate($bps).' · '.trim((string) $data['email']).'.');
                    Notification::make()->title('Ajustes de Minimedia guardados')->success()->send();
                }),
        ];
    }
}
