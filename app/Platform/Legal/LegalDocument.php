<?php

namespace App\Platform\Legal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Versión de un texto legal (de Clave de Alma si store_id es nulo, o de una tienda).
 * Inmutable: un cambio crea una versión nueva; solo se marca su revisión.
 *
 * @property int $id
 * @property LegalPage $page
 * @property ?int $store_id
 * @property int $version
 * @property string $content
 * @property ?list<string> $missing
 * @property Carbon $generated_at
 * @property ?Carbon $approved_at
 * @property ?string $approved_by
 */
class LegalDocument extends Model
{
    private const MUTABLE = ['approved_at', 'approved_by', 'updated_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['page' => LegalPage::class, 'missing' => 'array', 'generated_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (LegalDocument $doc): void {
            if (array_diff(array_keys($doc->getDirty()), self::MUTABLE) !== []) {
                throw new LogicException('Un texto legal no se modifica: se genera una versión nueva.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Los textos legales no se borran: quedan como historial.'));
    }

    public static function current(LegalPage $page, ?int $storeId): ?self
    {
        return self::query()->where('page', $page)->where('store_id', $storeId)->latest('version')->first();
    }

    /** Lo que se guarda en el pedido: qué condiciones aceptó el cliente. */
    public function label(): string
    {
        return $this->page->value.' v'.$this->version;
    }
}
