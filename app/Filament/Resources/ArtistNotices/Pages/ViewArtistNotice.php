<?php

namespace App\Filament\Resources\ArtistNotices\Pages;

use App\Filament\Resources\ArtistNotices\ArtistNoticeResource;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Application\HandleNotice;
use App\Modules\Stores\Contracts\NoticeStatus;
use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/** Ver un aviso. El superadmin lo pasa a «En curso» o «Hecho», con una respuesta opcional. */
class ViewArtistNotice extends ViewRecord
{
    protected static string $resource = ArtistNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->handle('inProgress', 'Marcar en curso', NoticeStatus::InProgress, Heroicon::OutlinedClock, 'info'),
            $this->handle('done', 'Marcar como hecho', NoticeStatus::Done, Heroicon::OutlinedCheckCircle, 'success'),
        ];
    }

    private function handle(string $name, string $label, NoticeStatus $status, Heroicon $icon, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(function (ArtistNotice $record) use ($status): bool {
                $user = auth()->user();

                return $user instanceof User && $user->isSuperadmin() && $record->status !== $status;
            })
            ->schema([
                Textarea::make('reply')->label('Respuesta para el artista (opcional)')->rows(4)->maxLength(2000)
                    ->default(fn (ArtistNotice $record): ?string => $record->reply)
                    ->helperText('Le llega por email y la ve en el aviso.'),
            ])
            ->action(function (ArtistNotice $record, array $data) use ($status): void {
                app(HandleNotice::class)($record, $status, $data['reply'] ?? null, (int) auth()->id());
                Notification::make()->title('Aviso: '.mb_strtolower($status->label()).'. El artista recibe un email.')->success()->send();
            });
    }
}
