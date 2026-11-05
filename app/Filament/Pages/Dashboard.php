<?php

namespace App\Filament\Pages;

use App\Modules\Identity\Infrastructure\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Escritorio del panel. Los artistas no tienen nada que hacer aquí: van directos a
 * «Mis ventas», su propio escritorio (ADR 0023).
 */
class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return ! ($user instanceof User && $user->isArtist() && ! $user->isSuperadmin());
    }

    public function mount(): void
    {
        $user = auth()->user();
        if ($user instanceof User && $user->isArtist() && ! $user->isSuperadmin()) {
            $this->redirect(ArtistSalesDashboard::getUrl());
        }
    }
}
