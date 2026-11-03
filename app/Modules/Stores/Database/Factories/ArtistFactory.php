<?php

namespace App\Modules\Stores\Database\Factories;

use App\Modules\Stores\Contracts\TaxRegime;
use App\Modules\Stores\Infrastructure\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    public function definition(): array
    {
        return [
            'display_name' => fake()->unique()->name(),
            'legal_name' => fake()->name(),
            'tax_id' => fake()->bothify('########?'),
            'tax_regime' => TaxRegime::SelfEmployed,
            'email' => fake()->unique()->safeEmail(),
            'city' => fake()->city(),
            'country' => 'ES',
        ];
    }
}
