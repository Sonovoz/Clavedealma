<?php

namespace App\Console\Commands;

use App\Platform\CatalogImport\TemplateWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catalogo:plantilla {destino=docs/protocolo/plantilla-articulos.xlsx : Dónde guardar la hoja}')]
#[Description('Genera la plantilla vacía de la hoja de artículos')]
class WriteCatalogTemplate extends Command
{
    public function handle(TemplateWriter $writer): int
    {
        $destino = (string) $this->argument('destino');
        $writer->write(str_starts_with($destino, '/') ? $destino : base_path($destino));
        $this->info("Plantilla guardada en {$destino}.");

        return self::SUCCESS;
    }
}
