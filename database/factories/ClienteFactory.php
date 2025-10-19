<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\TipoDocumento;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cliente>
 */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        // Asegura que exista al menos un tipo de documento
        $tipoDoc = TipoDocumento::inRandomOrder()->first();

        return [
            'tipo_documento_id' => $tipoDoc ? $tipoDoc->id : 1, // fallback por si no hay datos
            'numero_documento' => $this->faker->numerify('########'),
            'nombre' => $this->faker->name(),
            'correo' => $this->faker->unique()->safeEmail(),
            'telefono' => $this->faker->phoneNumber(),
            'direccion' => $this->faker->address(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
