<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $empresas = Empresa::all();
        $tipoDoc = TipoDocumento::inRandomOrder()->first();

        foreach ($empresas as $empresa) {
            for ($i = 0; $i < 5; $i++) {
                Cliente::create([
                    'empresa_id' => $empresa->id,
                    'tipo_documento_id' => $tipoDoc->id,
                    'numero_documento' => $faker->numerify('########'),
                    'nombre' => $faker->name,
                    'correo' => $faker->safeEmail,
                    'telefono' => $faker->phoneNumber,
                    'direccion' => $faker->address,
                ]);
            }
        }
    }
}
