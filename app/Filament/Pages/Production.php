<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderActions;
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

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $navigationLabel = 'Fabricación';

    protected static ?string $title = 'Fabricación';

    protected static ?int $navigationSort = 5;

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
                    ->description(fn (Order $record): ?string => $record->is_test ? 'Prueba' : null),
                TextColumn::make('pieces')->label('Piezas')->html()->state(fn (Order $record): HtmlString => new HtmlString(
                    $record->lines->map(fn (OrderLine $l): string => '<strong>'.$l->quantity.' ×</strong> '.e($l->sku).' · '.e($l->product_name)
                        .($l->label() !== '' ? ' ('.e($l->label()).')' : '')
                        .($l->personalized ? ' <em>· personalizada</em>' : '')
                        .' <span style="opacity:.6">· '.e($l->store_name).'</span>')->implode('<br>'),
                )),
                TextColumn::make('paid_at')->label('Pagado')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('deadline')->label('Debe estar listo')
                    ->state(fn (Order $record): ?string => $record->paid_at?->copy()->addWeekdays($record->lead_time_max_days)->format('d/m/Y'))
                    ->color(fn (Order $record): ?string => $record->paid_at !== null && $record->paid_at->copy()->addWeekdays($record->lead_time_max_days)->isPast() ? 'danger' : null),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    OrderStatus::Paid->value => OrderStatus::Paid->label(),
                    OrderStatus::InProduction->value => OrderStatus::InProduction->label(),
                ]),
            ])
            ->recordActions([OrderActions::startProduction(), OrderActions::markReady()])
            ->defaultSort('paid_at')
            ->emptyStateHeading('No hay nada que fabricar')
            ->emptyStateDescription('Los pedidos pagados aparecen aquí solos.');
    }
}
