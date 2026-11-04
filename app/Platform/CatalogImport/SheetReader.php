<?php

namespace App\Platform\CatalogImport;

use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lee la hoja de artículos (.xlsx o .csv) y devuelve una fila por variante,
 * con las cabeceras normalizadas (minúsculas, sin tildes, espacios como "_").
 */
class SheetReader
{
    /**
     * @return list<array{fila: int, datos: array<string, string>}>
     */
    public function read(string $path, ?string $extension = null): array
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader($this->csvOptions($path)),
            default => throw new InvalidArgumentException('La hoja debe ser .xlsx o .csv.'),
        };

        $reader->open($path);
        $filas = [];
        $cabeceras = null;

        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $numero => $row) {
                $valores = array_map(fn ($v): string => $this->texto($v), $row->toArray());

                if ($cabeceras === null) {
                    $cabeceras = array_map(fn (string $c): string => $this->cabecera($c), $valores);

                    continue;
                }
                if (implode('', $valores) === '') {
                    continue; // fila vacía
                }

                $datos = [];
                foreach ($cabeceras as $k => $cabecera) {
                    if ($cabecera !== '') {
                        $datos[$cabecera] = trim($valores[$k] ?? '');
                    }
                }
                $filas[] = ['fila' => (int) $numero, 'datos' => $datos];
            }
            break; // solo la primera hoja: la segunda es la de instrucciones
        }

        $reader->close();

        return $filas;
    }

    private function csvOptions(string $path): CsvOptions
    {
        $options = new CsvOptions;
        $handle = fopen($path, 'r') ?: throw new InvalidArgumentException('No se puede abrir la hoja.');
        $primera = (string) fgets($handle);
        fclose($handle);
        $options->FIELD_DELIMITER = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

        return $options;
    }

    private function cabecera(string $valor): string
    {
        return Str::of($valor)->ascii()->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function texto(mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            is_float($valor) => rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.'),
            $valor instanceof \DateTimeInterface => $valor->format('Y-m-d'),
            is_scalar($valor) => (string) $valor,
            default => '',
        };
    }
}
