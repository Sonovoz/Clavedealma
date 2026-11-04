<?php

namespace App\Modules\Shipping\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property ?string $tracking_url
 * @property bool $active
 */
class Carrier extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function trackingUrl(string $code): ?string
    {
        return $this->tracking_url === null ? null : str_replace('{codigo}', rawurlencode($code), $this->tracking_url);
    }
}
