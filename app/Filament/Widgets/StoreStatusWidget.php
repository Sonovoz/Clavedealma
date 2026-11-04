<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Reports;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Returns\ReturnResource;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Returns\Contracts\ReturnStatus;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Platform\Documents\SalesReport;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * «Estado de la tienda» en el Escritorio, como el recuadro «Estado de WooCommerce»:
 * ventas del mes y lo que espera a alguien. Solo superadministradores (hay importes).
 */
class StoreStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Estado de la tienda';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSuperadmin();
    }

    protected function getDescription(): ?string
    {
        return 'Pedidos reales de todas las tiendas (sin pruebas).';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $now = CarbonImmutable::now('Europe/Madrid');
        $month = SalesReport::data($now->startOfMonth(), $now);
        $real = fn () => Order::query()->where('is_test', false);

        $processing = $real()->whereIn('status', OrderStatus::processing())->count();
        $toMake = $real()->where('status', OrderStatus::Paid)->count();
        $pending = $real()->where('status', OrderStatus::PendingPayment)->count();
        $returns = ReturnRequest::query()->whereIn('status', [ReturnStatus::Requested, ReturnStatus::Received])->count();

        return [
            Stat::make('Ventas netas este mes', $month['net'])
                ->description("{$month['orders']} pedidos · ".$now->translatedFormat('F'))
                ->url(Reports::getUrl()),
            Stat::make('Procesando', (string) $processing)
                ->description($toMake === 1 ? '1 por empezar a fabricar' : "{$toMake} por empezar a fabricar")
                ->color($processing > 0 ? 'success' : 'gray')
                ->url(OrderResource::getUrl('index', ['tab' => 'procesando'])),
            Stat::make('Pendientes de pago', (string) $pending)
                ->description('Pedidos sin pago confirmado')
                ->url(OrderResource::getUrl('index', ['tab' => 'pendiente'])),
            Stat::make('Devoluciones por revisar', (string) $returns)
                ->description('Solicitadas o recibidas sin reembolsar')
                ->color($returns > 0 ? 'warning' : 'gray')
                ->url(ReturnResource::getUrl('index')),
        ];
    }
}
