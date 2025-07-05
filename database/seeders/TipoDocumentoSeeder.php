<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

class TipoDocumentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documentos = [
            ['codigo' => 'DNI', 'descripcion' => 'Documento Nacional de Identidad'],
            ['codigo' => 'RUC', 'descripcion' => 'Registro Único de Contribuyentes'],
            ['codigo' => 'PAS', 'descripcion' => 'Pasaporte'],
        ];

        foreach ($documentos as $doc) {
            TipoDocumento::create($doc);
        }
    }
}
