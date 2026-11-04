<?php

namespace App\Platform\CatalogImport;

use App\Modules\Catalog\Domain\SizeScale;
use Illuminate\Support\Str;

/**
 * Valida las pestañas opcionales "Prendas" (ficha textil) y "Tallas" (guía de tallas).
 * No toca la base de datos.
 *
 * @phpstan-type GarmentDraft array{fila: int, codigo: string, nombre: ?string, tejido: ?string, composicion: ?string, corte: ?string, cuidados: ?string, pais: ?string, peso: ?int, nota_tallas: ?string}
 * @phpstan-type SizeDraft array{fila: int, talla: string, pecho: ?string, largo: ?string, manga: ?string}
 */
class GarmentParser
{
    /**
     * @param  list<array{fila: int, datos: array<string, string>}>  $rows
     * @return array<string, GarmentDraft> por código
     */
    public function garments(array $rows, ImportReport $report): array
    {
        $this->checkHeaders($rows, Columns::GARMENTS, 'Prendas', $report);
        $out = [];

        foreach ($rows as ['fila' => $fila, 'datos' => $d]) {
            $codigo = Str::upper($d['codigo'] ?? '');
            if ($codigo === '' || ! preg_match('/^[A-Z0-9][A-Z0-9-]{0,29}$/', $codigo)) {
                $report->error($fila, 'Prendas · codigo', "Código \"{$codigo}\" no válido: letras, números y guiones (máx. 30).");

                continue;
            }
            if (isset($out[$codigo])) {
                $report->error($fila, 'Prendas · codigo', "La prenda {$codigo} está repetida (fila {$out[$codigo]['fila']}).");

                continue;
            }

            $peso = null;
            if (($d['peso'] ?? '') !== '') {
                if (! preg_match('/^\d{1,5}$/', $d['peso'])) {
                    $report->error($fila, 'Prendas · peso', 'El peso va en gramos, sin decimales.');

                    continue;
                }
                $peso = (int) $d['peso'];
            }

            $value = fn (string $key, int $max): ?string => ($d[$key] ?? '') === '' ? null : Str::limit($d[$key], $max, '');
            $out[$codigo] = [
                'fila' => $fila,
                'codigo' => $codigo,
                'nombre' => $value('nombre', 255),
                'tejido' => $value('tejido', 255),
                'composicion' => $value('composicion', 255),
                'corte' => $value('corte', 40),
                'cuidados' => $value('cuidados', 2000),
                'pais' => $value('pais', 60),
                'peso' => $peso,
                'nota_tallas' => $value('nota_tallas', 255),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{fila: int, datos: array<string, string>}>  $rows
     * @return array<string, list<SizeDraft>> por código de prenda, en el orden de la escala
     */
    public function sizes(array $rows, ImportReport $report): array
    {
        $this->checkHeaders($rows, Columns::SIZES, 'Tallas', $report);
        $out = [];

        foreach ($rows as ['fila' => $fila, 'datos' => $d]) {
            $prenda = Str::upper($d['prenda'] ?? '');
            $talla = SizeScale::normalize($d['talla'] ?? '') ?? 'Única';
            if ($prenda === '') {
                $report->error($fila, 'Tallas · prenda', 'Falta el código de la prenda.');

                continue;
            }
            foreach ($out[$prenda] ?? [] as $otra) {
                if ($otra['talla'] === $talla) {
                    $report->error($fila, 'Tallas · talla', "La talla {$talla} de {$prenda} está repetida (fila {$otra['fila']}).");

                    continue 2;
                }
            }

            $medidas = [];
            foreach (['pecho', 'largo', 'manga'] as $medida) {
                $valor = str_replace(',', '.', $d[$medida] ?? '');
                if ($valor !== '' && ! preg_match('/^\d{1,3}(\.\d)?$/', $valor)) {
                    $report->error($fila, "Tallas · {$medida}", "\"{$d[$medida]}\" no es una medida en cm (p. ej. 56 o 56,5).");

                    continue 2;
                }
                $medidas[$medida] = $valor === '' ? null : $valor;
            }

            $out[$prenda][] = ['fila' => $fila, 'talla' => $talla] + $medidas;
        }

        foreach ($out as &$list) {
            usort($list, fn (array $a, array $b): int => [SizeScale::rank($a['talla']), $a['talla']] <=> [SizeScale::rank($b['talla']), $b['talla']]);
        }

        return $out;
    }

    /**
     * @param  list<array{fila: int, datos: array<string, string>}>  $rows
     * @param  array<string, array{0: bool, 1: string, 2: string}>  $columns
     */
    private function checkHeaders(array $rows, array $columns, string $sheet, ImportReport $report): void
    {
        if ($rows === []) {
            return;
        }
        $headers = array_keys($rows[0]['datos']);
        foreach (array_keys(array_filter($columns, fn (array $c): bool => $c[0])) as $required) {
            if (! in_array($required, $headers, true)) {
                $report->error(1, "{$sheet} · {$required}", "Falta la columna obligatoria \"{$required}\" en la pestaña {$sheet}.");
            }
        }
        foreach (array_diff($headers, array_keys($columns)) as $unknown) {
            $report->warning(1, "{$sheet} · {$unknown}", "Columna desconocida \"{$unknown}\" en la pestaña {$sheet}: se ignora.");
        }
    }
}
