<?php

namespace App\Platform\CatalogImport;

use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lee la hoja de artículos (.xlsx o .csv): una fila por variante, con las cabeceras
 * normalizadas (minúsculas, sin tildes, espacios como "_"). En .xlsx lee también
 * las pestañas opcionales "Prendas" y "Tallas".
 */
class SheetReader
{
    /**
     * Solo la primera hoja (artículos).
     *
     * @return list<array{fila: int, datos: array<string, string>}>
     */
    public function read(string $path, ?string $extension = null): array
    {
        return $this->readAll($path, $extension)['articulos'];
    }

    /**
     * Pestañas del libro: la primera son los artículos; "Prendas" y "Tallas" se buscan
     * por nombre (sin distinguir mayúsculas ni tildes). Un .csv solo tiene artículos.
     *
     * @return array{articulos: list<array{fila: int, datos: array<string, string>}>, prendas: list<array{fila: int, datos: array<string, string>}>, tallas: list<array{fila: int, datos: array<string, string>}>}
     */
    public function readAll(string $path, ?string $extension = null): array
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader($this->csvOptions($path)),
            default => throw new InvalidArgumentException('La hoja debe ser .xlsx o .csv.'),
        };

        $reader->open($path);
        $out = ['articulos' => [], 'prendas' => [], 'tallas' => []];
        $first = true;

        foreach ($reader->getSheetIterator() as $hoja) {
            $name = $first ? 'articulos' : $this->cabecera($hoja->getName());
            $first = false;
            if (! array_key_exists($name, $out)) {
                continue; // p. ej. la pestaña de instrucciones
            }
            $out[$name] = $this->rows($hoja->getRowIterator());
        }

        $reader->close();

        return $out;
    }

    /**
     * @param  iterable<int, Row>  $rowIterator
     * @return list<array{fila: int, datos: array<string, string>}>
     */
    private function rows(iterable $rowIterator): array
    {
        $filas = [];
        $cabeceras = null;

        foreach ($rowIterator as $numero => $row) {
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
