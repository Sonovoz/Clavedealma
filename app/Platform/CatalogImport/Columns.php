<?php

namespace App\Platform\CatalogImport;

/**
 * Columnas de la hoja de artículos (protocolo de alta, docs/protocolo/README.md).
 * Una fila por variante (talla × color). Los datos de producto se repiten
 * en cada fila del mismo modelo o se dejan vacíos a partir de la segunda.
 */
final class Columns
{
    public const PRODUCT = 'producto';

    public const VARIANT = 'variante';

    /**
     * nombre => [obligatoria, nivel, explicación, ejemplo]
     *
     * @var array<string, array{0: bool, 1: string, 2: string, 3: string}>
     */
    public const ALL = [
        'tienda' => [true, self::PRODUCT, 'Subdominio de la tienda (debe existir en el panel).', 'farruquito'],
        'coleccion' => [false, self::PRODUCT, 'Colección. Si no existe, se crea. Varias: separadas por "|". Vacía: no se cambia.', 'On Stage'],
        'modelo' => [true, self::PRODUCT, 'Referencia única del artículo en la tienda: letras, números y guiones. Da nombre a las fotos.', 'FQ-OS-01'],
        'nombre' => [true, self::PRODUCT, 'Nombre comercial.', 'La esencia'],
        'prenda' => [false, self::PRODUCT, 'Prenda base (código o nombre). Si no existe, se crea.', 'FQ-CAM-OS'],
        'color' => [false, self::VARIANT, 'Color de la variante. Vacío si el artículo no tiene colores.', 'Negro'],
        'color_hex' => [false, self::VARIANT, 'Muestra del color en la tienda, formato #RRGGBB.', '#111111'],
        'talla' => [false, self::VARIANT, 'Talla. Vacía = talla única.', 'M'],
        'precio' => [true, self::VARIANT, 'Precio de venta con IVA, en euros.', '39,90'],
        'iva' => [false, self::PRODUCT, 'Tipo de IVA en %. Por defecto 21.', '21'],
        'comision' => [false, self::PRODUCT, 'Comisión del artista en % solo para este artículo. Vacía = la de la tienda.', ''],
        'descripcion' => [false, self::PRODUCT, 'Texto de la ficha. Un párrafo por línea en blanco.', 'Una imagen que trasciende lo visible.'],
        'plazo' => [false, self::PRODUCT, 'Días de fabricación, "mín-máx". Por defecto 5-7.', '5-7'],
        'venta' => [false, self::VARIANT, '"bajo pedido" (por defecto), "limitada" (se agota) o "stock y bajo pedido".', 'bajo pedido'],
        'unidades' => [false, self::VARIANT, 'Unidades disponibles. Obligatoria si la venta es limitada o con stock.', ''],
        'aviso_stock' => [false, self::VARIANT, 'Avisar al fabricante cuando queden estas unidades.', ''],
        'personalizado' => [false, self::PRODUCT, '"si" si se hace con datos del cliente (sin derecho de desistimiento).', 'no'],
        'estado' => [false, self::PRODUCT, '"borrador", "publicado" o "archivado". Vacío: nuevo = borrador; existente = no se cambia.', 'borrador'],
        'sku' => [false, self::VARIANT, 'Código de la variante. Vacío = TIENDA-MODELO-TALLA-COLOR.', ''],
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * @return list<string>
     */
    public static function required(): array
    {
        return array_keys(array_filter(self::ALL, fn (array $c): bool => $c[0]));
    }

    /**
     * @return list<string>
     */
    public static function productLevel(): array
    {
        return array_keys(array_filter(self::ALL, fn (array $c): bool => $c[1] === self::PRODUCT));
    }
}
