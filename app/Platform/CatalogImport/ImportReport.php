<?php

namespace App\Platform\CatalogImport;

/**
 * Resultado de una importación (o de su comprobación): errores que la impiden,
 * avisos que no la impiden y el resumen de lo que se ha hecho o se haría.
 */
class ImportReport
{
    /** @var list<array{fila: ?int, campo: string, mensaje: string}> */
    public array $errors = [];

    /** @var list<array{fila: ?int, campo: string, mensaje: string}> */
    public array $warnings = [];

    /** @var array<string, int> */
    public array $summary = [
        'filas' => 0,
        'productos_nuevos' => 0,
        'productos_actualizados' => 0,
        'productos_sin_cambios' => 0,
        'variantes_nuevas' => 0,
        'variantes_actualizadas' => 0,
        'variantes_desactivadas' => 0,
        'fotos' => 0,
        'colecciones_nuevas' => 0,
        'prendas_nuevas' => 0,
    ];

    public bool $applied = false;

    /** Etiquetas del resumen para la interfaz. */
    public const LABELS = [
        'filas' => 'Filas leídas',
        'productos_nuevos' => 'Productos nuevos',
        'productos_actualizados' => 'Productos actualizados',
        'productos_sin_cambios' => 'Productos sin cambios',
        'variantes_nuevas' => 'Variantes nuevas',
        'variantes_actualizadas' => 'Variantes actualizadas',
        'variantes_desactivadas' => 'Variantes desactivadas (no están en la hoja)',
        'fotos' => 'Fotos asignadas',
        'colecciones_nuevas' => 'Colecciones nuevas',
        'prendas_nuevas' => 'Prendas base nuevas',
    ];

    public function error(?int $fila, string $campo, string $mensaje): void
    {
        $this->errors[] = ['fila' => $fila, 'campo' => $campo, 'mensaje' => $mensaje];
    }

    public function warning(?int $fila, string $campo, string $mensaje): void
    {
        $this->warnings[] = ['fila' => $fila, 'campo' => $campo, 'mensaje' => $mensaje];
    }

    public function count(string $key, int $by = 1): void
    {
        $this->summary[$key] += $by;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, int>
     */
    public function labelledSummary(): array
    {
        $out = [];
        foreach ($this->summary as $key => $value) {
            $out[self::LABELS[$key]] = $value;
        }

        return $out;
    }
}
