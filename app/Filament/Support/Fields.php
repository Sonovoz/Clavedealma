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

    /** Peso en kilos en el formulario («0,5»), guardado en gramos enteros (500). */
    public static function kilograms(string $name): TextInput
    {
        return TextInput::make($name)
            ->suffix('kg')
            ->rule('regex:/^\d{1,3}([.,]\d{1,3})?$/')
            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : self::kilogramsLabel($state, false))
            ->dehydrateStateUsing(function (?string $state): ?int {
                if (blank($state)) {
                    return null;
                }
                [$kilos, $decimals] = array_pad(explode(',', str_replace('.', ',', $state)), 2, '0');

                return (int) $kilos * 1000 + (int) str_pad(substr($decimals, 0, 3), 3, '0');
            });
    }

    public static function kilogramsLabel(int $grams, bool $unit = true): string
    {
        $kilos = intdiv($grams, 1000);
        $rest = rtrim(str_pad((string) ($grams % 1000), 3, '0', STR_PAD_LEFT), '0');

        return $kilos.($rest === '' ? '' : ','.$rest).($unit ? ' kg' : '');
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
