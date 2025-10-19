<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proveedor>
 */
class ProveedorFactory extends Factory
{
    public function definition(): array
    {
        $empresa = Empresa::inRandomOrder()->first() ?? Empresa::factory()->create();
        $tipoDocumento = TipoDocumento::inRandomOrder()->first() ?? TipoDocumento::factory()->create([
            'codigo' => '6',
            'descripcion' => 'RUC',
        ]);

        return [
            'empresa_id' => $empresa->id,
            'tipo_documento_id' => $tipoDocumento->id,
            'numero_documento' => $this->faker->unique()->numerify('10#########'),
            'razon_social' => strtoupper($this->faker->company()),
            'nombre_comercial' => $this->faker->companySuffix(),
            'contacto_nombre' => $this->faker->name(),
            'contacto_cargo' => $this->faker->randomElement(['Gerente', 'Administrador', 'Vendedor']),
            'telefono' => $this->faker->numerify('01########'),
            'celular' => $this->faker->numerify('9########'),
            'email' => $this->faker->unique()->safeEmail(),
            'pais' => 'Perú',
            'departamento' => $this->faker->randomElement(['Lima', 'La Libertad', 'Arequipa', 'Piura', 'Cusco']),
            'provincia' => $this->faker->city(),
            'distrito' => $this->faker->citySuffix(),
            'direccion' => $this->faker->streetAddress(),
            'observacion' => $this->faker->optional()->sentence(8),
            'activo' => $this->faker->boolean(90),
        ];
    }
}
