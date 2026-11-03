<?php

namespace App\Filament\Support;

use App\Modules\Stores\Domain\Commission;
use App\Support\Contracts\HasLabel;
use App\Support\Money;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

/**
 * Campos reutilizables del panel: importes en euros guardados en céntimos
 * y porcentajes guardados en puntos básicos.
 */
final class Fields
{
    public static function money(string $name): TextInput
    {
        return TextInput::make($name)
            ->suffix('€')
            ->rule('regex:/^\d{1,6}([.,]\d{1,2})?$/')
            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::fromCents($state)->decimal())
            ->dehydrateStateUsing(fn (?string $state): ?int => blank($state) ? null : Money::parse($state)->cents);
    }

    public static function percent(string $name): TextInput
    {
        return TextInput::make($name)
            ->suffix('%')
            ->rule('regex:/^\d{1,3}([.,]\d{1,2})?$/')
            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : str_replace('.', ',', (string) ($state / 100)))
            ->dehydrateStateUsing(fn (?string $state): ?int => blank($state) ? null : Commission::fromPercent($state)->basisPoints);
    }

    public static function moneyColumn(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::fromCents($state)->format())
            ->alignEnd()
            ->sortable();
    }

    public static function percentColumn(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Commission::fromBasisPoints($state)->label())
            ->alignEnd()
            ->sortable();
    }

    /**
     * Opciones de un enum con método label().
     *
     * @param  class-string<BackedEnum&HasLabel>  $enum
     * @return array<string, string>
     */
    public static function options(string $enum): array
    {
        $options = [];
        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }
}
