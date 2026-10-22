<?php

namespace Database\Factories;

use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    protected $model = TipoDocumento::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('??'),
            'abreviatura' => fake()->unique()->lexify('DOC???'),
            'descripcion' => fake()->words(2, true),
        ];
    }

    public function dni(): static
    {
        return $this->state(['codigo' => '1', 'abreviatura' => 'DNI', 'descripcion' => 'Documento Nacional de Identidad']);
    }

    public function ruc(): static
    {
        return $this->state(['codigo' => '6', 'abreviatura' => 'RUC', 'descripcion' => 'Registro Único de Contribuyentes']);
    }
}
