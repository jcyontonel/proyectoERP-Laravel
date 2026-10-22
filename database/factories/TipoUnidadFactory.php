<?php

namespace Database\Factories;

use App\Models\TipoUnidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoUnidad>
 */
class TipoUnidadFactory extends Factory
{
    protected $model = TipoUnidad::class;

    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->lexify('???')),
            'codigo_sunat' => 'NIU',
            'descripcion' => ucfirst(fake()->word()),
        ];
    }
}
