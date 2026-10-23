<?php

namespace Database\Seeders;

use App\Modulos\Nucleo\Models\TipoDocumento;
use Illuminate\Database\Seeder;

class TipoDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogo SUNAT 06 (es lo que validan ClienteRequest y los modelos Cliente/Proveedor)
        $tipos = [
            ['codigo' => '0', 'abreviatura' => 'S/D', 'descripcion' => 'Sin documento'],
            ['codigo' => '1', 'abreviatura' => 'DNI', 'descripcion' => 'Documento Nacional de Identidad'],
            ['codigo' => '4', 'abreviatura' => 'CE', 'descripcion' => 'Carné de Extranjería'],
            ['codigo' => '6', 'abreviatura' => 'RUC', 'descripcion' => 'Registro Único de Contribuyentes'],
            ['codigo' => '7', 'abreviatura' => 'PAS', 'descripcion' => 'Pasaporte'],
            ['codigo' => 'A', 'abreviatura' => 'CD', 'descripcion' => 'Cédula Diplomática de Identidad'],
        ];

        foreach ($tipos as $tipo) {
            TipoDocumento::updateOrCreate(
                ['codigo' => $tipo['codigo']],
                ['abreviatura' => $tipo['abreviatura'], 'descripcion' => $tipo['descripcion']]
            );
        }
    }
}
