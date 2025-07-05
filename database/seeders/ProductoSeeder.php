<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Producto;
use App\Models\Empresa;
use App\Models\Categoria;
use App\Models\TipoUnidad;
use App\Models\Impuesto;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $empresas = Empresa::all();
        $unidad = TipoUnidad::inRandomOrder()->first();
        $impuesto = Impuesto::where('nombre', 'IGV')->first();

        foreach ($empresas as $empresa) {
            $categoria = Categoria::where('empresa_id', $empresa->id)->first();

            for ($i = 0; $i < 5; $i++) {
                Producto::create([
                    'empresa_id' => $empresa->id,
                    'categoria_id' => $categoria->id,
                    'tipo_unidad_id' => $unidad->id,
                    'impuesto_id' => $impuesto->id,
                    'codigo' => strtoupper($faker->bothify('PRD-####')),
                    'nombre' => $faker->word,
                    'descripcion' => $faker->sentence,
                    'precio_unitario' => $faker->randomFloat(2, 10, 200),
                    'stock' => $faker->numberBetween(5, 100),
                    'es_servicio' => false,
                    'activo' => true,
                ]);
            }
        }
    }
}
