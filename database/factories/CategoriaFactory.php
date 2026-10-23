<?php

namespace Database\Factories;

use App\Modulos\Catalogo\Models\Categoria;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nombre' => ucfirst(fake()->unique()->words(2, true)),
            'descripcion' => fake()->sentence(),
        ];
    }
}
