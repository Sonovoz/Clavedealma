<?php

namespace App\Modules\Stores\Infrastructure\Models;

use App\Modules\Stores\Contracts\TaxRegime;
use App\Modules\Stores\Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ?int $user_id
 * @property string $display_name
 * @property ?TaxRegime $tax_regime
 * @property ?int $vat_rate_bps
 * @property ?int $irpf_rate_bps
 * @property ?string $iban
 * @property ?Carbon $self_billing_agreement_signed_at
 */
#[UseFactory(ArtistFactory::class)]
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tax_regime' => TaxRegime::class,
            'vat_rate_bps' => 'integer',
            'irpf_rate_bps' => 'integer',
            'iban' => 'encrypted',
            'self_billing_agreement_signed_at' => 'date',
        ];
    }

    /**
     * @return HasMany<Store, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function hasSelfBillingAgreement(): bool
    {
        return $this->self_billing_agreement_signed_at !== null;
    }
}
