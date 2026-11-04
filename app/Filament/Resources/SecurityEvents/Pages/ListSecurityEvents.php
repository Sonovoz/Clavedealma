<?php

namespace App\Filament\Resources\SecurityEvents\Pages;

use App\Filament\Resources\SecurityEvents\SecurityEventResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListSecurityEvents extends ListRecords
{
    protected static string $resource = SecurityEventResource::class;

    protected static ?string $title = 'Registro de seguridad';

    protected static ?string $breadcrumb = 'Listado';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Accesos al panel, intentos fallidos, cambios de usuarios y roles, dinero devuelto, datos fiscales, copias y actualizaciones. No se puede modificar y se conserva '.config('clavedealma.security.retention_days', 365).' días.';
    }
}
