<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceIssuer;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $issuer = InvoiceIssuer::current();

        return $issuer->complete() ? null : 'No se emiten facturas: faltan los datos fiscales ('.implode(', ', $issuer->missing()).'). Rellénalos en Ajustes → Datos de facturación.';
    }
}
