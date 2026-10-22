<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\MotivoAjuste;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MotivoAjuste>
 */
class MotivoAjusteFactory extends Factory
{
    protected $model = MotivoAjuste::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'codigo' => fake()->unique()->bothify('MOT-###'),
            'nombre' => ucfirst(fake()->words(3, true)),
            'tipo_movimiento' => fake()->randomElement(['ingreso', 'egreso', 'ambos']),
            'requiere_aprobacion' => false,
            'activo' => true,
        ];
    }
}
