<?php

namespace App\Platform\CatalogImport;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera la hoja del protocolo (.xlsx): pestañas "Artículos", "Prendas", "Tallas"
 * e "Instrucciones". Todas las celdas van como texto para que la hoja
 * de cálculo no convierta "5-7" en una fecha ni "39,90" en otro número.
 */
class TemplateWriter
{
    /** Notas que acompañan a las columnas en la pestaña de instrucciones. */
    public const NOTES = [
        'Una fila por variante (cada combinación de talla y color). Los datos del producto se repiten en sus filas o se dejan vacíos desde la segunda.',
        'Fotos en un ZIP aparte, con este nombre: MODELO_NN.jpg (fotos generales) o MODELO_COLOR_NN.jpg (fotos de un color). Ej.: FQ-OS-01_01.jpg, FQ-OS-01_NEGRO_02.jpg.',
        'La foto número 00 de un color es su muestra de tejido (el botón para elegir el color): FQ-OS-01_NEGRO_00.jpg.',
        'Pestaña Prendas: la ficha textil (composición, cuidados, corte…) de cada prenda base. Se escribe una vez y la heredan todos sus artículos. Sin composición no se puede publicar.',
        'Pestaña Tallas: la guía de tallas, una fila por prenda y talla, con las medidas en cm tomadas en plano.',
        'Para publicar hacen falta precio, una variante, una foto y una prenda base con composición. Si falta algo en un artículo publicado, la importación da error.',
        'Formatos JPG, PNG o WEBP; máximo 8 MB por foto. Recomendado: 1600 px por el lado largo, fondo limpio y la misma proporción en todas (4:5).',
        'Las fotos que se suben sustituyen a las que tenía el modelo. Si un modelo no tiene fotos en el ZIP, conserva las suyas.',
        'Volver a importar la misma hoja actualiza los artículos: no duplica nada. Las variantes que desaparecen de la hoja se desactivan (no se borran).',
        'Las unidades solo se aplican mientras la variante no haya tenido ventas, fabricación ni ajustes, o si cambia su modo de venta. El stock vivo se ajusta en el panel.',
        'Antes de importar, el panel comprueba la hoja y enseña los errores fila por fila. Con algún error no se cambia nada.',
    ];

    /**
     * @param  list<array<string, string>>  $rows  artículos, con las claves de Columns::ALL
     * @param  list<array<string, string>>  $garments  prendas, con las claves de Columns::GARMENTS
     * @param  list<array<string, string>>  $sizes  guía de tallas, con las claves de Columns::SIZES
     */
    public function write(string $path, array $rows = [], array $garments = [], array $sizes = []): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1F1B16');
        $bold = (new Style)->setFontBold();
        $wrap = (new Style)->setShouldWrapText();

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Artículos');
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));
        foreach (Columns::names() as $i => $name) {
            $sheet->setColumnWidth(in_array($name, ['descripcion', 'nombre', 'coleccion'], true) ? 32 : 14, $i + 1);
        }
        $writer->addRow(Row::fromValues(Columns::names(), $header));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn (string $c): string => $row[$c] ?? '', Columns::names())));
        }

        foreach (['Prendas' => [Columns::GARMENTS, $garments], 'Tallas' => [Columns::SIZES, $sizes]] as $name => [$columns, $data]) {
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $sheet->setSheetView((new SheetView)->setFreezeRow(2));
            foreach (array_keys($columns) as $i => $column) {
                $sheet->setColumnWidth(in_array($column, ['composicion', 'cuidados', 'nombre', 'tejido', 'nota_tallas'], true) ? 30 : 12, $i + 1);
            }
            $writer->addRow(Row::fromValues(array_keys($columns), $header));
            foreach ($data as $row) {
                $writer->addRow(Row::fromValues(array_map(fn (string $c): string => $row[$c] ?? '', array_keys($columns))));
            }
        }

        $help = $writer->addNewSheetAndMakeItCurrent();
        $help->setName('Instrucciones');
        $help->setColumnWidth(16, 1);
        $help->setColumnWidth(12, 2, 3);
        $help->setColumnWidth(70, 4);
        $help->setColumnWidth(18, 5);
        $writer->addRow(Row::fromValues(['Protocolo de alta de artículos · Clave de Alma'], $bold));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Columna', 'Obligatoria', 'Nivel', 'Qué poner', 'Ejemplo'], $header));
        foreach (Columns::ALL as $name => [$required, $level, $text, $example]) {
            $writer->addRow(Row::fromValues([$name, $required ? 'sí' : 'no', $level, $text, $example], $wrap));
        }
        foreach (['Prendas' => Columns::GARMENTS, 'Tallas' => Columns::SIZES] as $tab => $columns) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Pestaña '.$tab], $bold));
            foreach ($columns as $name => [$required, $text, $example]) {
                $writer->addRow(Row::fromValues([$name, $required ? 'sí' : 'no', Str::lower($tab), $text, $example], $wrap));
            }
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Notas'], $bold));
        foreach (self::NOTES as $note) {
            $writer->addRow(Row::fromValues(['', '', '', $note], $wrap));
        }

        $writer->close();
    }
}
