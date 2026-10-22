<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\MotivoAjuste;
use Illuminate\Database\Seeder;

class MotivoAjusteSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            ['codigo' => 'INV-INICIAL', 'nombre' => 'Inventario inicial',          'tipo_movimiento' => 'ingreso', 'requiere_aprobacion' => false],
            ['codigo' => 'CONTEO',      'nombre' => 'Diferencia de conteo físico', 'tipo_movimiento' => 'ambos',   'requiere_aprobacion' => true],
            ['codigo' => 'MERMA',       'nombre' => 'Merma',                       'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => true],
            ['codigo' => 'VENCIDO',     'nombre' => 'Producto vencido',            'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => true],
            ['codigo' => 'DANADO',      'nombre' => 'Producto dañado',             'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => true],
            ['codigo' => 'CONSUMO',     'nombre' => 'Consumo interno',             'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => false],
            ['codigo' => 'OBSEQUIO',    'nombre' => 'Obsequio / muestra',          'tipo_movimiento' => 'egreso',  'requiere_aprobacion' => false],
            ['codigo' => 'HALLAZGO',    'nombre' => 'Sobrante encontrado',         'tipo_movimiento' => 'ingreso', 'requiere_aprobacion' => true],
        ];

        foreach (Empresa::all() as $empresa) {
            foreach ($motivos as $m) {
                MotivoAjuste::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'codigo' => $m['codigo']],
                    $m + ['activo' => true]
                );
            }
        }
    }
}
