<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Moneda;
use Illuminate\Database\Seeder;

class MonedaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $monedas = [
            ['codigo' => 'PEN', 'descripcion' => 'Sol Peruano', 'simbolo' => 'S/'],
            ['codigo' => 'USD', 'descripcion' => 'Dólar Estadounidense', 'simbolo' => '$'],
            ['codigo' => 'EUR', 'descripcion' => 'Euro', 'simbolo' => '€'],
        ];

        foreach ($monedas as $moneda) {
            Moneda::create($moneda);
        }
    }
}
