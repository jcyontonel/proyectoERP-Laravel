<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Factura;
use App\Models\Empresa;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Moneda;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class FacturaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $empresas = Empresa::all();
        $moneda = Moneda::first();

        foreach ($empresas as $empresa) {
            $clientes = Cliente::where('empresa_id', $empresa->id)->get();
            $usuarios = User::where('empresa_id', $empresa->id)->get();

            foreach ($clientes as $cliente) {
                Factura::create([
                    'empresa_id' => $empresa->id,
                    'cliente_id' => $cliente->id,
                    'user_id' => $usuarios->random()->id,
                    'moneda_id' => $moneda->id,
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
