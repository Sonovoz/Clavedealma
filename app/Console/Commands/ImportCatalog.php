<?php

namespace App\Console\Commands;

use App\Platform\CatalogImport\CatalogImporter;
use App\Platform\CatalogImport\ImportReport;
use App\Platform\CatalogImport\PhotoSet;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('catalogo:importar {hoja : Hoja .xlsx o .csv del protocolo} {--fotos= : Carpeta o ZIP con las fotos} {--aplicar : Guarda los cambios (sin esta opción solo comprueba)}')]
#[Description('Importa artículos desde la hoja del protocolo (docs/protocolo)')]
class ImportCatalog extends Command
{
    public function handle(CatalogImporter $importer): int
    {
        $hoja = (string) $this->argument('hoja');
        if (! is_file($hoja)) {
            $this->error("No existe la hoja [{$hoja}].");

            return self::FAILURE;
        }

        $fotos = $this->option('fotos');
        $photos = null;
        if (is_string($fotos) && $fotos !== '') {
            $photos = is_dir($fotos)
                ? PhotoSet::fromDirectory($fotos)
                : PhotoSet::fromZip($fotos, storage_path('app/private/importaciones/'.Str::random(12)));
        }

        try {
            $report = $importer->import($hoja, $photos, (bool) $this->option('aplicar'), null);
        } finally {
            $photos?->cleanup();
        }

        $this->show($report);

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    private function show(ImportReport $report): void
    {
        if ($report->errors !== []) {
            $this->error(count($report->errors).' errores: no se ha cambiado nada.');
            $this->table(['Fila', 'Campo', 'Error'], array_map(fn (array $e): array => [$e['fila'] ?? '—', $e['campo'], $e['mensaje']], $report->errors));
        }
        if ($report->warnings !== []) {
            $this->warn(count($report->warnings).' avisos:');
            $this->table(['Fila', 'Campo', 'Aviso'], array_map(fn (array $w): array => [$w['fila'] ?? '—', $w['campo'], $w['mensaje']], $report->warnings));
        }

        $this->table(['Resumen', ''], collect($report->labelledSummary())->map(fn (int $v, string $k): array => [$k, $v])->values()->all());

        if ($report->applied) {
            $this->info('Importación aplicada.');
        } elseif (! $report->hasErrors()) {
            $this->info('Comprobación correcta. Repite con --aplicar para guardar los cambios.');
        }
    }
}
