<?php

namespace App\Filament\Resources\ArtistNotices\Pages;

use App\Filament\Resources\ArtistNotices\ArtistNoticeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArtistNotices extends ListRecords
{
    protected static string $resource = ArtistNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo aviso')->visible(fn (): bool => ArtistNoticeResource::canCreate())];
    }
}
