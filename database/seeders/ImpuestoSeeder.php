<?php

namespace Database\Seeders;

use App\Modulos\Nucleo\Models\Impuesto;
use Illuminate\Database\Seeder;

class ImpuestoSeeder extends Seeder
{
    public function run(): void
    {
        // tipo_afectacion: catálogo SUNAT 07 · codigo_tributo: catálogo SUNAT 05
        $impuestos = [
            ['codigo' => 'IGV', 'nombre' => 'Gravado - IGV',  'tipo_afectacion' => '10', 'codigo_tributo' => '1000', 'porcentaje' => 18.00],
            ['codigo' => 'EXO', 'nombre' => 'Exonerado',      'tipo_afectacion' => '20', 'codigo_tributo' => '9997', 'porcentaje' => 0.00],
            ['codigo' => 'INA', 'nombre' => 'Inafecto',       'tipo_afectacion' => '30', 'codigo_tributo' => '9998', 'porcentaje' => 0.00],
            ['codigo' => 'EXP', 'nombre' => 'Exportación',    'tipo_afectacion' => '40', 'codigo_tributo' => '9995', 'porcentaje' => 0.00],
        ];

        foreach ($impuestos as $imp) {
            Impuesto::updateOrCreate(['codigo' => $imp['codigo']], $imp + ['activo' => true]);
        }
    }
}
