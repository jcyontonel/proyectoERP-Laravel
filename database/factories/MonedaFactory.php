<?php

namespace Database\Factories;

use App\Models\Moneda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Moneda>
 */
class MonedaFactory extends Factory
{
    protected $model = Moneda::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->currencyCode(),
            'nombre' => ucfirst(fake()->word()),
            'simbolo' => '$',
            'activo' => true,
        ];
    }

    public function soles(): static
    {
        return $this->state(['codigo' => 'PEN', 'nombre' => 'Soles', 'simbolo' => 'S/']);
    }
}
