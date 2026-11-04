<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Documents;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [Documents::shippingReport()];
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, array $statuses): Tab => Tab::make($label)
            ->badge(fn (): ?int => ($n = Order::query()->whereIn('status', $statuses)->count()) > 0 ? $n : null)
            ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', $statuses));

        return [
            'todos' => Tab::make('Todos'),
            'por_fabricar' => $tab('Por fabricar', [OrderStatus::Paid]),
            'en_fabricacion' => $tab('En fabricación', [OrderStatus::InProduction]),
            'para_enviar' => $tab('Para enviar', [OrderStatus::ReadyToShip]),
            'enviados' => $tab('Enviados', [OrderStatus::Shipped]),
            'a_revisar' => Tab::make('A revisar')
                ->badge(fn (): ?int => ($n = Order::query()->whereNotNull('review_reason')->whereIn('status', OrderStatus::paid())->count()) > 0 ? $n : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereNotNull('review_reason')),
        ];
    }
}
