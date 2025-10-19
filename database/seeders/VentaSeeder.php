<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Venta;
use App\Models\Empresa;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class VentaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $empresas = Empresa::all();

        foreach ($empresas as $empresa) {
            $clientes = $empresa->clientes;
            $usuarios = $empresa->users;

            foreach ($clientes as $cliente) {
                Venta::create([
                    'empresa_id' => $empresa->id,
                    'cliente_id' => $cliente->id,
                    'user_id' => $usuarios->isNotEmpty() ? $usuarios->random()->id : null,
                    'serie' => 'F001',
                    'numero' => $faker->unique()->numerify('######'),
                    'fecha_emision' => now(),
                    'tipo' => 'factura',
                    'estado' => 'registrada',
                    'subtotal' => 0,
                    'total_impuestos' => 0,
                    'total' => 0,
                    'observaciones' => $faker->sentence,
                ]);
            }
        }
    }
}
