<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Support\Documents;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderLine;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * «Fabricación»: lo que hay que fabricar, para el fabricante y el superadministrador.
 * Solo piezas, plazos y referencias: ningún dato del cliente (nombre, email ni dirección).
 */
class Production extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.production';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Comercio';

    protected static ?string $navigationLabel = 'Fabricación';

    protected static ?string $title = 'Fabricación';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'fabricacion';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->isSuperadmin() || $user->hasRole(Role::Manufacturer->value));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->where('status', OrderStatus::Paid)->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->with('lines')->whereIn('status', [OrderStatus::Paid, OrderStatus::InProduction]))
            ->columns([
                TextColumn::make('reference')->label('Pedido')->weight('bold')
                    ->description(function (Order $record): HtmlString {
                        $deadline = $record->paid_at?->copy()->addWeekdays($record->lead_time_max_days);
                        $late = $deadline !== null && $deadline->isPast();

                        return new HtmlString('Pagado '.e((string) $record->paid_at?->timezone('Europe/Madrid')->format('d/m H:i')).($record->is_test ? ' · Prueba' : '')
                            .'<br><span style="'.($late ? 'color:rgb(220 38 38);font-weight:600' : '').'">Listo antes del '.e((string) $deadline?->format('d/m/Y')).'</span>');
                    }),
                // Cada pieza en dos líneas cortas (qué es / referencia): la tabla cabe sin barra lateral.
                TextColumn::make('pieces')->label('Piezas')->html()->wrap()->state(fn (Order $record): HtmlString => new HtmlString(
                    $record->lines->map(fn (OrderLine $l): string => '<div style="padding:.15rem 0;min-width:14rem">'
                        .'<strong>'.$l->quantity.' ×</strong> '.e($l->product_name)
                        .($l->label() !== '' ? ' · '.e($l->label()) : '')
                        .($l->personalized ? ' <em style="color:rgb(217 119 6)">· personalizada</em>' : '')
                        .'<br><span style="font-size:.75rem;opacity:.6;overflow-wrap:anywhere">'.e($l->sku).' · '.e($l->store_name).'</span></div>')->implode(''),
                )),
                TextColumn::make('status')->label('Estado')->badge()->visibleFrom('xl')
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    OrderStatus::Paid->value => OrderStatus::Paid->label(),
                    OrderStatus::InProduction->value => OrderStatus::InProduction->label(),
                ]),
            ])
            ->headerActions([Documents::productionSheet()])
            ->recordActions([
                OrderActions::startProduction()->label('Empezar')->button()->size('sm'),
                OrderActions::markReady()->label('Listo')->button()->size('sm'),
                Documents::productionOrder()->iconButton()->tooltip('Orden de fabricación (PDF)'),
            ])
            ->defaultSort('paid_at')
            ->emptyStateHeading('No hay nada que fabricar')
            ->emptyStateDescription('Los pedidos pagados aparecen aquí solos.');
    }
}
