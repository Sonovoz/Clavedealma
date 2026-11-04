<?php

namespace App\Http\Controllers\Storefront;

use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\StoreAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Portada del market (la matriz): las tiendas publicadas. El equipo ve también
 * las ocultas, marcadas como vista previa.
 */
class MarketController
{
    public function __invoke(Request $request): View
    {
        $staff = StoreAccess::isStaff($request->user());

        $stores = Store::query()
            ->with('artist')
            ->when(! $staff, fn ($q) => $q->where('status', StoreStatus::Published))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StoreStatus::Published->value])
            ->orderBy('name')
            ->get();

        return view('storefront.market', ['stores' => $stores, 'staff' => $staff]);
    }
}
