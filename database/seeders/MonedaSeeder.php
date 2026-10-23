<?php

namespace Database\Seeders;

use App\Modulos\Nucleo\Models\Moneda;
use Illuminate\Database\Seeder;

class MonedaSeeder extends Seeder
{
    public function run(): void
    {
        $monedas = [
            ['codigo' => 'PEN', 'nombre' => 'Soles', 'simbolo' => 'S/'],
            ['codigo' => 'USD', 'nombre' => 'Dólares americanos', 'simbolo' => 'US$'],
        ];

        foreach ($monedas as $moneda) {
            Moneda::updateOrCreate(
                ['codigo' => $moneda['codigo']],
                ['nombre' => $moneda['nombre'], 'simbolo' => $moneda['simbolo'], 'activo' => true]
            );
        }
    }
}
