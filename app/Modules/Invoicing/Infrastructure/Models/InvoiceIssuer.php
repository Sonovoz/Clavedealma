<?php

namespace App\Modules\Invoicing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datos fiscales del emisor (Clave de Alma). Se copian en cada factura al emitirla.
 *
 * @property int $id
 * @property ?string $legal_name
 * @property ?string $nif
 * @property ?string $address
 * @property ?string $postal_code
 * @property ?string $city
 * @property ?string $province
 * @property string $country
 * @property ?string $email
 * @property ?string $phone
 * @property ?string $registry_details
 * @property string $simplified_series
 * @property string $rectifying_series
 */
class InvoiceIssuer extends Model
{
    protected $guarded = ['id'];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['country' => 'ES', 'simplified_series' => 'T', 'rectifying_series' => 'R']);
    }

    /**
     * Lo que falta para poder facturar.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $labels = ['legal_name' => 'razón social', 'nif' => 'NIF', 'address' => 'domicilio', 'postal_code' => 'código postal', 'city' => 'población'];

        return array_values(array_map(fn (string $f): string => $labels[$f], array_filter(array_keys($labels), fn (string $f): bool => trim((string) $this->getAttribute($f)) === '')));
    }

    public function complete(): bool
    {
        return $this->missing() === [];
    }

    /**
     * @return array<string, ?string>
     */
    public function snapshot(): array
    {
        return [
            'legal_name' => $this->legal_name, 'nif' => strtoupper(trim((string) $this->nif)), 'address' => $this->address,
            'postal_code' => $this->postal_code, 'city' => $this->city, 'province' => $this->province, 'country' => $this->country, 'email' => $this->email,
        ];
    }
}
