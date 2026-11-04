<?php

namespace App\Filament\Resources\Returns\Pages;

use App\Filament\Resources\Returns\ReturnResource;
use App\Modules\Returns\Contracts\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReturns extends ListRecords
{
    protected static string $resource = ReturnResource::class;

    public function getTabs(): array
    {
        $tab = fn (string $label, ReturnStatus $status): Tab => Tab::make($label)
            ->badge(fn (): ?int => ($n = ReturnRequest::query()->where('status', $status)->count()) > 0 ? $n : null)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));

        return [
            'por_revisar' => $tab('Por revisar', ReturnStatus::Requested),
            'esperando' => $tab('Esperando el paquete', ReturnStatus::Approved),
            'dinero_pendiente' => $tab('Dinero pendiente', ReturnStatus::Received),
            'todas' => Tab::make('Todas'),
        ];
    }
}
