<?php

namespace Database\Factories;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Models\MotivoAjuste;
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
