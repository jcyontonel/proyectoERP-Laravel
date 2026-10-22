<?php

namespace Database\Factories;

use App\Models\Impuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Impuesto>
 */
class ImpuestoFactory extends Factory
{
    protected $model = Impuesto::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('IMP???'),
            'nombre' => 'IGV',
            'tipo_afectacion' => '10',
            'codigo_tributo' => '1000',
            'porcentaje' => 18,
            'activo' => true,
        ];
    }
}
