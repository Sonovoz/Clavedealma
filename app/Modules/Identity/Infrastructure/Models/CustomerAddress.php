<?php

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dirección guardada de un cliente («Mi cuenta → Direcciones», como en WooCommerce).
 * Rellena «Finalizar compra»; el pedido guarda su propia copia, así que cambiarla
 * nunca toca pedidos ya hechos.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property ?string $phone
 * @property string $address_line1
 * @property ?string $address_line2
 * @property string $postal_code
 * @property string $city
 * @property ?string $province
 * @property string $country
 */
class CustomerAddress extends Model
{
    public const FIELDS = ['name', 'phone', 'address_line1', 'address_line2', 'postal_code', 'city', 'province', 'country'];

    protected $fillable = ['user_id', ...self::FIELDS];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
