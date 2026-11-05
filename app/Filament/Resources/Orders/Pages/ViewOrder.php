<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Documents;
use App\Modules\Orders\Application\AddOrderNote;
use App\Modules\Orders\Application\ResendOrderEmails;
use App\Modules\Orders\Infrastructure\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * Ficha del pedido como WooCommerce (ADR 0025): los pasos del pedido, «Añadir nota»
 * (privada o para el cliente) y el menú «Acciones del pedido».
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::startProduction(),
            OrderActions::markReady(),
            OrderActions::ship(),
            OrderActions::markDelivered(),
            OrderActions::cancelAndRefund(),
            $this->addNote(),
            ActionGroup::make([
                Action::make('resendCustomer')
                    ->label('Enviar los detalles del pedido al cliente')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Order $record): string => 'Se envía otra vez la confirmación del pedido a '.$record->email.'.')
                    ->visible(fn (Order $record): bool => $record->status->isPaid())
                    ->action(fn (Order $record) => $this->run(fn () => app(ResendOrderEmails::class)->toCustomer($record, $this->author()), 'Detalles enviados a '.$record->email.'.')),
                Action::make('resendTeam')
                    ->label('Reenviar la notificación de nuevo pedido')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->action(fn (Order $record) => $this->run(fn () => 'Aviso reenviado a '.app(ResendOrderEmails::class)->toTeam($record, $this->author()).'.')),
                Action::make('customerView')
                    ->label('Ver el pedido como el cliente')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Order $record): string => route('checkout.thanks', $record->token), shouldOpenInNewTab: true),
                Documents::productionOrder(),
                Documents::packingSlip(),
            ])->label('Acciones del pedido')->icon(Heroicon::OutlinedChevronDown)->button()->color('gray')->dropdownWidth(Width::Small),
        ];
    }

    private function addNote(): Action
    {
        return Action::make('addNote')
            ->label('Añadir nota')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->color('gray')
            ->modalHeading('Añadir nota')
            ->modalSubmitActionLabel('Añadir')
            ->schema([
                Textarea::make('text')->label('Nota')->required()->maxLength(AddOrderNote::MAX)->rows(4),
                Select::make('kind')->label('Tipo de nota')->selectablePlaceholder(false)->default('private')
                    ->options(['private' => 'Nota privada', 'customer' => 'Nota para el cliente'])
                    ->helperText('La nota para el cliente le llega por email y la ve en la página de su pedido.'),
            ])
            ->action(fn (Order $record, array $data) => $this->run(
                fn () => app(AddOrderNote::class)($record, (string) $data['text'], ($data['kind'] ?? 'private') === 'customer', $this->author()),
                ($data['kind'] ?? 'private') === 'customer' ? 'Nota añadida y enviada al cliente.' : 'Nota privada añadida.',
            ));
    }

    private function run(callable $do, ?string $ok = null): void
    {
        try {
            $result = $do();
            Notification::make()->title(is_string($result) ? $result : (string) $ok)->success()->send();
            // Las notas nuevas se ven al momento, sin recargar la página.
            $this->getRecord()->load(['lines', 'events']);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    private function author(): ?string
    {
        $name = auth()->user()?->getAttribute('name');

        return is_string($name) ? $name : null;
    }
}
