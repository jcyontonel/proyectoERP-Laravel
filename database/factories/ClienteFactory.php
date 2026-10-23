<?php

namespace Database\Factories;

use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            // DNI (catálogo SUNAT 06 = '1'); el seeder puede cambiarlo con sequence()
            'tipo_documento_id' => fn () => TipoDocumento::where('codigo', '1')->value('id')
                ?? TipoDocumento::factory()->dni(),
            'numero_documento' => fake()->unique()->numerify('########'),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'correo' => fake()->unique()->safeEmail(),
            'telefono' => fake()->numerify('9########'),
            'direccion' => fake()->streetAddress(),
            'activo' => true,
        ];
    }
}
