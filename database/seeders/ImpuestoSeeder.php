<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Impuesto;
use Illuminate\Database\Seeder;

class ImpuestoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $impuestos = [
            ['nombre' => 'IGV', 'porcentaje' => 18.00, 'activo' => true],
            ['nombre' => 'Exonerado', 'porcentaje' => 0.00, 'activo' => true],
            ['nombre' => 'Inafecto', 'porcentaje' => 0.00, 'activo' => true],
        ];

        foreach ($impuestos as $imp) {
            Impuesto::create($imp);
        }
    }
}
