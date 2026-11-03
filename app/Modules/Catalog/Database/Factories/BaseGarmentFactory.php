<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseGarment>
 */
class BaseGarmentFactory extends Factory
{
    protected $model = BaseGarment::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PB-###'),
            'name' => fake()->randomElement(['Camiseta unisex', 'Sudadera con capucha', 'Gorra']).' '.fake()->numberBetween(150, 400).' g',
            'material' => 'Algodón orgánico',
            'active' => true,
        ];
    }
}
