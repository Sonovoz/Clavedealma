<?php

namespace App\Filament\Resources\Returns\Pages;

use App\Filament\Resources\Returns\ReturnResource;
use App\Modules\Returns\Application\ProcessReturn;
use App\Modules\Returns\Contracts\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Platform\Security\SecurityEventType;
use App\Platform\Security\SecurityLog;
use App\Support\Money;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

class ViewReturn extends ViewRecord
{
    protected static string $resource = ReturnResource::class;

    protected function getHeaderActions(): array
    {
        $is = fn (ReturnStatus $status): Closure => fn (ReturnRequest $record): bool => $record->status === $status;

        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible($is(ReturnStatus::Requested))
                ->modalDescription('El cliente recibirá estas instrucciones por email.')
                ->schema([
                    Textarea::make('instructions')->label('Cómo enviarnos las piezas')->required()->rows(5)->maxLength(2000)
                        ->default(fn (ReturnRequest $record): string => self::defaultInstructions($record)),
                ])
                ->action(fn (ReturnRequest $record, array $data, ProcessReturn $process) => $this->run(
                    fn () => $process->approve($record, (string) $data['instructions'], $this->who()), 'Devolución aprobada: se ha avisado al cliente')),

            Action::make('reject')
                ->label('Rechazar')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible($is(ReturnStatus::Requested))
                ->schema([Textarea::make('reason')->label('Motivo (lo leerá el cliente)')->required()->rows(3)->maxLength(500)])
                ->action(fn (ReturnRequest $record, array $data, ProcessReturn $process) => $this->run(
                    fn () => $process->reject($record, (string) $data['reason'], $this->who()), 'Devolución rechazada')),

            Action::make('receive')
                ->label(fn (ReturnRequest $record): string => 'Recibida: devolver '.Money::fromCents($record->refund_cents)->format())
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('danger')
                ->visible($is(ReturnStatus::Approved))
                ->requiresConfirmation()
                ->modalDescription('Confirma que has recibido y revisado las piezas. Se devolverá el dinero al medio de pago del cliente y no se puede deshacer.')
                ->schema([Toggle::make('restock')->label('Las piezas están bien: reponerlas en el stock')->default(true)])
                ->action(fn (ReturnRequest $record, array $data, ProcessReturn $process) => $this->run(
                    fn () => $this->refunded($process->receiveAndRefund($record, (bool) $data['restock'], $this->who())), 'Dinero devuelto al cliente')),

            Action::make('retryRefund')
                ->label('Reintentar la devolución del dinero')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('danger')
                ->visible($is(ReturnStatus::Received))
                ->requiresConfirmation()
                ->action(fn (ReturnRequest $record, ProcessReturn $process) => $this->run(fn () => $this->refunded($process->refund($record)), 'Dinero devuelto al cliente')),
        ];
    }

    public static function defaultInstructions(ReturnRequest $return): string
    {
        $address = config('clavedealma.returns.address');

        return "Mete las piezas, sin usar y con sus etiquetas, en un paquete y escribe por fuera la referencia {$return->reference}.\n"
            .($address ? "Envíalo a: {$address}." : 'Envíalo a: [ESCRIBE AQUÍ LA DIRECCIÓN DE DEVOLUCIONES].')
            .($return->reason->ourFault() ? "\nComo el error es nuestro, el envío corre de nuestra cuenta: [indica cómo]." : "\nEl envío de vuelta corre de tu cuenta.");
    }

    private function run(Closure $do, string $done): void
    {
        try {
            $do();
            Notification::make()->title($done)->success()->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
        $this->record->refresh();
    }

    private function refunded(ReturnRequest $return): void
    {
        SecurityLog::record(SecurityEventType::MoneyRefunded, 'Devueltos '.Money::fromCents($return->refund_cents)->format().' por la devolución '.$return->reference.' (pedido '.$return->order_reference.').', subject: (string) $return->reference);
    }

    private function who(): ?string
    {
        $name = auth()->user()?->getAttribute('name');

        return is_string($name) ? $name : null;
    }
}
