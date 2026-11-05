<?php

namespace App\Platform\CatalogImport;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Domain\LeadTime;
use App\Modules\Catalog\Domain\SizeScale;
use App\Support\Money;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Convierte las filas de la hoja en artículos (un artículo = tienda + modelo) con sus variantes,
 * validando cada valor. No toca la base de datos.
 *
 * @phpstan-type VariantDraft array{fila: int, talla: ?string, color: ?string, color_hex: ?string, precio: int, sku: ?string, venta: string, unidades: ?int, aviso: ?int}
 * @phpstan-type ProductDraft array{fila: int, tienda: string, modelo: string, nombre: string, colecciones: ?list<string>, prenda: ?string, iva: int, comision: ?int, descripcion: ?string, info_modelo: ?string, plazo: array{0: int, 1: int}, personalizado: bool, estado: ?ProductStatus, variantes: list<VariantDraft>}
 */
class RowParser
{
    private const MAX_ROWS = 5000;

    /**
     * @param  list<array{fila: int, datos: array<string, string>}>  $rows
     * @return array<string, ProductDraft> clave "tienda|MODELO"
     */
    public function parse(array $rows, ImportReport $report): array
    {
        if ($rows === []) {
            $report->error(null, 'hoja', 'La hoja no tiene filas de artículos.');

            return [];
        }
        if (count($rows) > self::MAX_ROWS) {
            $report->error(null, 'hoja', 'La hoja tiene más de '.self::MAX_ROWS.' filas: divídela en varias.');

            return [];
        }

        $headers = array_keys($rows[0]['datos']);
        foreach (Columns::required() as $column) {
            if (! in_array($column, $headers, true)) {
                $report->error(1, $column, "Falta la columna obligatoria \"{$column}\".");
            }
        }
        foreach (array_diff($headers, Columns::names()) as $unknown) {
            $report->warning(1, $unknown, "Columna desconocida \"{$unknown}\": se ignora.");
        }
        if ($report->hasErrors()) {
            return [];
        }

        /** @var array<string, array{fila: int, raw: array<string, array{0: string, 1: int}>, variantes: list<VariantDraft>}> $groups */
        $groups = [];
        $skus = [];

        foreach ($rows as ['fila' => $fila, 'datos' => $datos]) {
            $tienda = Str::lower($datos['tienda'] ?? '');
            $modelo = Str::upper($datos['modelo'] ?? '');

            if ($tienda === '') {
                $report->error($fila, 'tienda', 'Falta la tienda.');
            }
            if ($modelo === '') {
                $report->error($fila, 'modelo', 'Falta el modelo.');
            } elseif (! preg_match('/^[A-Z0-9][A-Z0-9-]{0,39}$/', $modelo)) {
                $report->error($fila, 'modelo', "El modelo \"{$modelo}\" solo puede tener letras, números y guiones (máx. 40), sin espacios ni \"_\".");
            }
            if ($tienda === '' || $modelo === '') {
                continue;
            }

            $key = $tienda.'|'.$modelo;
            $groups[$key] ??= ['fila' => $fila, 'raw' => [], 'variantes' => []];

            foreach (Columns::productLevel() as $campo) {
                $valor = $datos[$campo] ?? '';
                if (in_array($campo, ['tienda', 'modelo'], true) || $valor === '') {
                    continue;
                }
                $previo = $groups[$key]['raw'][$campo] ?? null;
                if ($previo === null) {
                    $groups[$key]['raw'][$campo] = [$valor, $fila];
                } elseif ($previo[0] !== $valor) {
                    $report->error($fila, $campo, "No coincide con la fila {$previo[1]} del mismo modelo {$modelo} (\"{$previo[0]}\"). Los datos de producto deben ser iguales en todas sus filas o quedar vacíos.");
                }
            }

            $variante = $this->variant($fila, $datos, $report);
            if ($variante === null) {
                continue;
            }
            foreach ($groups[$key]['variantes'] as $otra) {
                if (Str::lower((string) $otra['talla']) === Str::lower((string) $variante['talla'])
                    && Str::lower((string) $otra['color']) === Str::lower((string) $variante['color'])) {
                    $report->error($fila, 'talla', "Talla y color repetidos en el modelo {$modelo} (ya están en la fila {$otra['fila']}).");

                    continue 2;
                }
            }
            if ($variante['sku'] !== null) {
                if (isset($skus[$variante['sku']])) {
                    $report->error($fila, 'sku', "SKU {$variante['sku']} repetido (fila {$skus[$variante['sku']]}).");

                    continue;
                }
                $skus[$variante['sku']] = $fila;
            }
            $groups[$key]['variantes'][] = $variante;
        }

        $products = [];
        foreach ($groups as $key => $group) {
            [$tienda, $modelo] = explode('|', $key, 2);
            $product = $this->product($tienda, $modelo, $group['fila'], $group['raw'], $report);
            if ($product !== null && $group['variantes'] !== []) {
                $product['variantes'] = $group['variantes'];
                $products[$key] = $product;
            }
        }

        return $products;
    }

    /**
     * @param  array<string, string>  $d
     * @return VariantDraft|null
     */
    private function variant(int $fila, array $d, ImportReport $report): ?array
    {
        $errores = count($report->errors);

        $talla = SizeScale::normalize($d['talla'] ?? null);
        if ($talla !== null && mb_strlen($talla) > 20) {
            $report->error($fila, 'talla', 'La talla no puede pasar de 20 caracteres.');
        }

        $color = ($d['color'] ?? '') === '' ? null : Str::ucfirst($d['color']);
        if ($color !== null && (mb_strlen($color) > 40 || Str::slug($color) === '')) {
            $report->error($fila, 'color', "Color \"{$color}\" no válido (máx. 40 caracteres, con alguna letra o número).");
        }

        $hex = ($d['color_hex'] ?? '') === '' ? null : Str::lower($d['color_hex']);
        if ($hex !== null && ! preg_match('/^#[0-9a-f]{6}$/', $hex)) {
            $report->error($fila, 'color_hex', "\"{$hex}\" no es un color #RRGGBB.");
        }

        $precio = 0;
        try {
            $precio = Money::parse($d['precio'] ?? '')->cents;
            if ($precio <= 0) {
                $report->error($fila, 'precio', 'El precio debe ser mayor que cero.');
            }
        } catch (InvalidArgumentException) {
            $report->error($fila, 'precio', 'Precio no válido: "'.($d['precio'] ?? '').'". Usa euros con IVA, por ejemplo 39,90.');
        }

        $sku = ($d['sku'] ?? '') === '' ? null : Str::upper($d['sku']);
        if ($sku !== null && ! preg_match('/^[A-Z0-9][A-Z0-9-]{0,63}$/', $sku)) {
            $report->error($fila, 'sku', "SKU \"{$sku}\" no válido: letras, números y guiones (máx. 64).");
        }

        $venta = $this->mode($d['venta'] ?? '');
        if ($venta === null) {
            $report->error($fila, 'venta', 'Venta no válida: "'.($d['venta'] ?? '').'". Usa "bajo pedido", "agotado" o "limitada".');
        }
        if (in_array(Str::slug($d['venta'] ?? ''), ['stock-y-bajo-pedido', 'stock-bajo-pedido', 'limited-on-demand'], true)) {
            $report->warning($fila, 'venta', '«stock y bajo pedido» ya no existe: se fabrica todo bajo pedido (ADR 0022).');
        }

        $unidades = $this->integer($fila, 'unidades', $d['unidades'] ?? '', $report);
        if ($venta === 'limitada' && $unidades === null) {
            $report->error($fila, 'unidades', 'Indica las unidades del producto (entre todas las tallas) para la venta "limitada".');
        }
        if ($venta !== 'limitada' && $unidades !== null) {
            $report->warning($fila, 'unidades', 'Se ignoran las unidades: solo cuentan con la venta "limitada".');
            $unidades = null;
        }
        $aviso = $this->integer($fila, 'aviso_stock', $d['aviso_stock'] ?? '', $report);

        if (count($report->errors) > $errores || $venta === null) {
            return null;
        }

        return [
            'fila' => $fila, 'talla' => $talla, 'color' => $color, 'color_hex' => $hex, 'precio' => $precio,
            'sku' => $sku, 'venta' => $venta, 'unidades' => $unidades, 'aviso' => $aviso,
        ];
    }

    /**
     * @param  array<string, array{0: string, 1: int}>  $raw
     * @return ProductDraft|null
     */
    private function product(string $tienda, string $modelo, int $fila, array $raw, ImportReport $report): ?array
    {
        $errores = count($report->errors);
        $valor = fn (string $campo): string => $raw[$campo][0] ?? '';
        $origen = fn (string $campo): int => $raw[$campo][1] ?? $fila;

        $nombre = $valor('nombre');
        if ($nombre === '') {
            $report->error($fila, 'nombre', "Falta el nombre del modelo {$modelo}.");
        } elseif (mb_strlen($nombre) > 200) {
            $report->error($origen('nombre'), 'nombre', 'El nombre no puede pasar de 200 caracteres.');
        }

        $colecciones = null;
        if ($valor('coleccion') !== '') {
            $colecciones = array_values(array_unique(array_filter(array_map('trim', explode('|', $valor('coleccion'))))));
        }

        $iva = $valor('iva') === '' ? 2100 : $this->percent($origen('iva'), 'iva', $valor('iva'), $report);
        $comision = $valor('comision') === '' ? null : $this->percent($origen('comision'), 'comision', $valor('comision'), $report);

        $plazo = [5, 7];
        if ($valor('plazo') !== '') {
            if (preg_match('/^(\d{1,2})\s*(?:[-–a]\s*(\d{1,2}))?$/u', $valor('plazo'), $m)) {
                $plazo = [(int) $m[1], (int) ($m[2] ?? $m[1])];
                try {
                    new LeadTime($plazo[0], $plazo[1]);
                } catch (InvalidArgumentException $e) {
                    $report->error($origen('plazo'), 'plazo', $e->getMessage());
                }
            } else {
                $report->error($origen('plazo'), 'plazo', "Plazo no válido: \"{$valor('plazo')}\". Usa días, por ejemplo 5-7.");
            }
        }

        $personalizado = $this->boolean($valor('personalizado'));
        if ($personalizado === null) {
            $report->error($origen('personalizado'), 'personalizado', 'Usa "si" o "no".');
        }

        $estado = match (Str::slug($valor('estado'))) {
            '' => null,
            'borrador', 'draft' => ProductStatus::Draft,
            'publicado', 'published' => ProductStatus::Published,
            'archivado', 'archived' => ProductStatus::Archived,
            default => false,
        };
        if ($estado === false) {
            $report->error($origen('estado'), 'estado', 'Estado no válido. Usa "borrador", "publicado" o "archivado".');
        }

        if (count($report->errors) > $errores || $estado === false || $personalizado === null) {
            return null;
        }

        return [
            'fila' => $fila,
            'tienda' => $tienda,
            'modelo' => $modelo,
            'nombre' => $nombre,
            'colecciones' => $colecciones,
            'prenda' => $valor('prenda') === '' ? null : $valor('prenda'),
            'iva' => (int) $iva,
            'comision' => $comision,
            'descripcion' => $valor('descripcion') === '' ? null : $this->html($valor('descripcion')),
            'info_modelo' => $valor('info_modelo') === '' ? null : Str::limit($valor('info_modelo'), 255, ''),
            'plazo' => $plazo,
            'personalizado' => $personalizado,
            'estado' => $estado,
            'variantes' => [],
        ];
    }

    /** Venta del producto (ADR 0022): «bajo pedido», «agotado» o «limitada». */
    private function mode(string $valor): ?string
    {
        return match (Str::slug($valor)) {
            '', 'bajo-pedido', 'on-demand', 'stock-y-bajo-pedido', 'stock-bajo-pedido', 'limited-on-demand' => 'bajo-pedido',
            'agotado', 'agotada', 'no-se-vende', 'out-of-stock' => 'agotado',
            'limitada', 'limitado', 'edicion-limitada', 'limited-sold-out' => 'limitada',
            default => null,
        };
    }

    private function integer(int $fila, string $campo, string $valor, ImportReport $report): ?int
    {
        if ($valor === '') {
            return null;
        }
        if (! preg_match('/^\d{1,6}$/', $valor)) {
            $report->error($fila, $campo, "\"{$valor}\" no es un número entero de unidades.");

            return null;
        }

        return (int) $valor;
    }

    /** "21", "21 %", "10,5" => puntos básicos (2100, 2100, 1050). */
    private function percent(int $fila, string $campo, string $valor, ImportReport $report): ?int
    {
        $limpio = str_replace([' ', '%'], '', $valor);
        if (! preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', $limpio, $m) || (int) $m[1] > 100) {
            $report->error($fila, $campo, "\"{$valor}\" no es un porcentaje entre 0 y 100.");

            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    private function boolean(string $valor): ?bool
    {
        return match (Str::slug($valor)) {
            '', 'no', 'n', '0', 'false' => false,
            'si', 's', 'yes', '1', 'x', 'true' => true,
            default => null,
        };
    }

    /** Texto plano a párrafos HTML (siempre escapado). */
    private function html(string $texto): string
    {
        $parrafos = preg_split('/\R\s*\R/u', trim($texto)) ?: [];

        return implode('', array_map(
            fn (string $p): string => '<p>'.nl2br(e(trim($p)), false).'</p>',
            $parrafos,
        ));
    }
}
