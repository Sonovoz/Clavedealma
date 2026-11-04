<?php

namespace App\Filament\Resources\Orders;

use App\Modules\Orders\Application\AdvanceOrder;
use App\Modules\Orders\Application\CancelPaidOrder;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Domain\OrderFlow;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Shipping\Contracts\CarrierDirectory;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use App\Support\Money;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * Botones de los pasos del pedido. Los mismos para el pedido y para la pantalla de
 * Fabricación; las reglas están en Orders (AdvanceOrder, CancelPaidOrder).
 */
final class OrderActions
{
    public static function startProduction(): Action
    {
        return self::step('startProduction', 'Empezar fabricación', Heroicon::OutlinedWrenchScrewdriver, OrderStatus::Paid,
            fn (Order $order, AdvanceOrder $advance) => $advance->startProduction($order, self::who()), 'Fabricación empezada');
    }

    public static function markReady(): Action
    {
        return self::step('markReady', 'Listo para enviar', Heroicon::OutlinedArchiveBoxArrowDown, OrderStatus::InProduction,
            fn (Order $order, AdvanceOrder $advance) => $advance->markReady($order, self::who()), 'Pedido listo para enviar');
    }

    public static function ship(): Action
    {
        return Action::make('ship')
            ->label('Marcar enviado')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::ReadyToShip)
            ->modalDescription('El cliente recibirá un email con el número de seguimiento.')
            ->schema([
                Select::make('carrier')
                    ->label('Transportista')
                    ->options(fn (): array => collect(app(CarrierDirectory::class)->names())->mapWithKeys(fn (string $n): array => [$n => $n])->all())
                    ->default(fn (Order $record): string => $record->shipping_carrier)
                    ->required(),
                TextInput::make('tracking_number')->label('Número de seguimiento')->required()->maxLength(120),
                TextInput::make('tracking_url')
                    ->label('Enlace de seguimiento (opcional)')
                    ->url()
                    ->maxLength(500)
                    ->helperText('Vacío = se forma solo con el enlace del transportista (Ajustes → Transportistas).'),
            ])
            ->action(function (Order $record, array $data, AdvanceOrder $advance): void {
                self::run(fn () => $advance->ship($record, (string) $data['carrier'], (string) $data['tracking_number'], $data['tracking_url'] ?? null, self::who()), 'Pedido enviado: se ha avisado al cliente');
            });
    }

    public static function markDelivered(): Action
    {
        return self::step('markDelivered', 'Marcar entregado', Heroicon::OutlinedCheckBadge, OrderStatus::Shipped,
            fn (Order $order, AdvanceOrder $advance) => $advance->markDelivered($order, self::who()), 'Pedido entregado');
    }

    public static function cancelAndRefund(): Action
    {
        return Action::make('cancelAndRefund')
            ->label('Cancelar y devolver el importe')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Order $record): bool => OrderFlow::canCancelPaid($record->status))
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => 'Cancelar '.$record->reference)
            ->modalDescription(fn (Order $record): string => 'Se devolverán '.Money::fromCents($record->total_cents)->format().' al medio de pago del cliente, las piezas que salieron del stock volverán a él y el cliente recibirá un email con el motivo. No se puede deshacer.')
            ->schema([
                Textarea::make('reason')->label('Motivo (lo leerá el cliente)')->required()->maxLength(300)->rows(3),
            ])
            ->action(function (Order $record, array $data, CancelPaidOrder $cancel): void {
                self::run(function () use ($cancel, $record, $data): void {
                    $cancel($record, (string) $data['reason'], self::who());
                    SecurityLog::record(SecurityEventType::OrderCancelled, 'Pedido cancelado y devueltos '.Money::fromCents($record->total_cents)->format().'. Motivo: '.$data['reason'], subject: (string) $record->reference);
                }, 'Pedido cancelado y dinero devuelto');
            });
    }

    /**
     * @param  Closure(Order, AdvanceOrder): Order  $do
     */
    private static function step(string $name, string $label, Heroicon $icon, OrderStatus $from, Closure $do, string $done): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (Order $record): bool => $record->status === $from)
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record): string => $label.' · '.$record->reference)
            ->action(function (Order $record, AdvanceOrder $advance) use ($do, $done): void {
                self::run(fn () => $do($record, $advance), $done);
            });
    }

    private static function run(Closure $do, string $done): void
    {
        try {
            $do();
            Notification::make()->title($done)->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    private static function who(): ?string
    {
        $name = auth()->user()?->getAttribute('name');

        return is_string($name) ? $name : null;
    }
}
