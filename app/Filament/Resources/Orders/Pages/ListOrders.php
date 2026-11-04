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
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $statuses));

        return [
            // Como la lista de pedidos de WooCommerce. El detalle de fabricación está en Comercio → Fabricación.
            'todos' => Tab::make('Todos'),
            'pendiente' => $tab('Pendiente de pago', [OrderStatus::PendingPayment]),
            'procesando' => $tab('Procesando', OrderStatus::processing()),
            'enviados' => $tab('Enviados', [OrderStatus::Shipped]),
            'completados' => $tab('Completados', [OrderStatus::Delivered]),
            'cancelados' => $tab('Cancelados', [OrderStatus::Cancelled]),
            'a_revisar' => Tab::make('A revisar')
                ->badge(fn (): ?int => ($n = Order::query()->whereNotNull('review_reason')->whereIn('status', OrderStatus::paid())->count()) > 0 ? $n : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('review_reason')),
        ];
    }
}
