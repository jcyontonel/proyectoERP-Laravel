<?php

namespace Database\Factories;

use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requiere la moneda PEN en `monedas` (MonedaSeeder) por la FK empresas.moneda.
 *
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        return [
            'ruc' => fake()->unique()->numerify('20#########'),
            'razon_social' => fake()->company() . ' S.A.C.',
            'nombre_comercial' => fake()->company(),
            'direccion' => fake()->address(),
            'telefono' => fake()->numerify('9########'),
            'email' => fake()->unique()->companyEmail(),
            'moneda' => 'PEN',
            'igv' => 18,
            'precios_incluyen_igv' => true,
            'controlar_stock' => true,
            'sunat_modo' => 'beta',
            'activo' => true,
        ];
    }
}
