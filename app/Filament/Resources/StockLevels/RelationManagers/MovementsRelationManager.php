<?php

namespace App\Filament\Resources\StockLevels\RelationManagers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Contracts\StockMovementReason;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Historial de movimientos (solo lectura).
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Historial de movimientos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->addSelect(['user_email' => User::query()->select('email')->whereColumn('users.id', 'stock_movements.user_id')]))
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                TextColumn::make('delta')->label('Unidades')->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('quantity_after')->label('Quedan'),
                TextColumn::make('reason')->label('Motivo')->formatStateUsing(fn (StockMovementReason $state): string => $state->label()),
                TextColumn::make('note')->label('Nota')->wrap(),
                TextColumn::make('user_email')->label('Usuario'),
            ]);
    }
}
