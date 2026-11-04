<?php

namespace App\Filament\Livewire;

use App\Filament\Support\ActiveStore;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Selector «Tienda: X» de la cabecera del panel (equipo de catálogo).
 */
class StoreSwitcher extends Component
{
    public ?int $storeId = null;

    public function mount(): void
    {
        $this->storeId = ActiveStore::id();
    }

    public function updatedStoreId(?int $value): void
    {
        if ($value === null) {
            return;
        }
        ActiveStore::set($value);

        // Si estábamos dentro de un registro de la otra tienda, volver a su listado.
        $back = (string) preg_replace('#/\d+/edit.*$#', '', (string) url()->previous());
        $this->redirect($back !== '' ? $back : url('/admin'));
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('filament.livewire.store-switcher', [
            'visible' => $user instanceof User && $user->managesCatalog(),
            'stores' => Store::query()->orderBy('name')->pluck('name', 'id')->all(),
            'storeUrl' => ($store = ActiveStore::get()) === null ? null : Urls::home($store),
        ]);
    }
}
