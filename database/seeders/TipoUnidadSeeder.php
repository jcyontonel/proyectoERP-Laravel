<?php

namespace Database\Seeders;

use App\Modulos\Catalogo\Models\TipoUnidad;
use Illuminate\Database\Seeder;

class TipoUnidadSeeder extends Seeder
{
    public function run(): void
    {
        // El orden importa: seeders/data/productos.php usa estos ids (11 = Bolsa, 12 = Saco, 7 = Kilogramo…)
        // codigo_sunat: catálogo SUNAT 03 (UN/ECE rec 20)
        $unidades = [
            ['codigo' => 'UND', 'codigo_sunat' => 'NIU', 'descripcion' => 'Unidad'],
            ['codigo' => 'CJ',  'codigo_sunat' => 'BX',  'descripcion' => 'Caja'],
            ['codigo' => 'PQT', 'codigo_sunat' => 'PK',  'descripcion' => 'Paquete'],
            ['codigo' => 'KIT', 'codigo_sunat' => 'KT',  'descripcion' => 'Kit'],
            ['codigo' => 'PAR', 'codigo_sunat' => 'PR',  'descripcion' => 'Par'],
            ['codigo' => 'DOC', 'codigo_sunat' => 'DZN', 'descripcion' => 'Docena'],
            ['codigo' => 'KG',  'codigo_sunat' => 'KGM', 'descripcion' => 'Kilogramo'],
            ['codigo' => 'LT',  'codigo_sunat' => 'LTR', 'descripcion' => 'Litro'],
            ['codigo' => 'GLN', 'codigo_sunat' => 'GLL', 'descripcion' => 'Galón'],
            ['codigo' => 'MT',  'codigo_sunat' => 'MTR', 'descripcion' => 'Metro'],
            ['codigo' => 'BLS', 'codigo_sunat' => 'BG',  'descripcion' => 'Bolsa'],
            ['codigo' => 'SCO', 'codigo_sunat' => 'SA',  'descripcion' => 'Saco'],
            ['codigo' => 'LTA', 'codigo_sunat' => 'CA',  'descripcion' => 'Lata'],
            ['codigo' => 'SBR', 'codigo_sunat' => 'EV',  'descripcion' => 'Sobre'],
            ['codigo' => 'BLD', 'codigo_sunat' => 'BJ',  'descripcion' => 'Balde'],
            ['codigo' => 'RLL', 'codigo_sunat' => 'RO',  'descripcion' => 'Rollo'],
            ['codigo' => 'FRS', 'codigo_sunat' => 'JR',  'descripcion' => 'Frasco'],
            ['codigo' => 'BLT', 'codigo_sunat' => 'NIU', 'descripcion' => 'Blíster'],
            ['codigo' => 'SRV', 'codigo_sunat' => 'ZZ',  'descripcion' => 'Servicio'],
            ['codigo' => 'PIP', 'codigo_sunat' => 'NIU', 'descripcion' => 'Pipeta'],
            ['codigo' => 'PAS', 'codigo_sunat' => 'NIU', 'descripcion' => 'Pastilla'],
        ];

        foreach ($unidades as $unidad) {
            TipoUnidad::updateOrCreate(
                ['codigo' => $unidad['codigo']],
                ['codigo_sunat' => $unidad['codigo_sunat'], 'descripcion' => $unidad['descripcion']]
            );
        }
    }
}
