<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\TipoUnidad;
use Illuminate\Database\Seeder;

class TipoUnidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unidades = [
            ['codigo' => 'NIU', 'descripcion' => 'Unidad (pieza)'],
            ['codigo' => 'KG', 'descripcion' => 'Kilogramo'],
            ['codigo' => 'LT', 'descripcion' => 'Litro'],
        ];

        foreach ($unidades as $unidad) {
            TipoUnidad::create($unidad);
        }
    }
}
