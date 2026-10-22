<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            // RUC (catálogo SUNAT 06 = '6')
            'tipo_documento_id' => fn () => TipoDocumento::where('codigo', '6')->value('id')
                ?? TipoDocumento::factory()->ruc(),
            'numero_documento' => fake()->unique()->numerify('20#########'),
            'razon_social' => strtoupper(fake()->unique()->company()) . ' S.A.C.',
            'nombre_comercial' => fake()->company(),
            'contacto_nombre' => fake()->name(),
            'contacto_cargo' => fake()->randomElement(['Gerente', 'Administrador', 'Vendedor']),
            'telefono' => fake()->numerify('01#######'),
            'celular' => fake()->numerify('9########'),
            'email' => fake()->unique()->safeEmail(),
            'pais' => 'Perú',
            'departamento' => fake()->randomElement(['Lima', 'La Libertad', 'Arequipa', 'Piura', 'Cusco']),
            'provincia' => fake()->city(),
            'distrito' => fake()->city(),
            'direccion' => fake()->streetAddress(),
            'observacion' => fake()->optional()->sentence(8),
            'activo' => true,
        ];
    }
}
