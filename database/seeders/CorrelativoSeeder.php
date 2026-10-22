<?php

namespace Database\Seeders;

use App\Models\Correlativo;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class CorrelativoSeeder extends Seeder
{
    public function run(): void
    {
        // modulo: el formulario de ventas solo muestra las series de 'venta'
        $series = [
            ['modulo' => 'venta',         'tipo' => '00', 'destipo' => 'Ticket',                 'serie' => 'T001'],
            ['modulo' => 'venta',         'tipo' => '01', 'destipo' => 'Factura',                'serie' => 'F001'],
            ['modulo' => 'venta',         'tipo' => '03', 'destipo' => 'Boleta',                 'serie' => 'B001'],
            ['modulo' => 'nota',          'tipo' => '07', 'destipo' => 'Nota de crédito',        'serie' => 'FC01'], // sobre facturas
            ['modulo' => 'nota',          'tipo' => '07', 'destipo' => 'Nota de crédito',        'serie' => 'BC01'], // sobre boletas
            ['modulo' => 'nota',          'tipo' => '08', 'destipo' => 'Nota de débito',         'serie' => 'FD01'],
            ['modulo' => 'nota',          'tipo' => '08', 'destipo' => 'Nota de débito',         'serie' => 'BD01'],
            ['modulo' => 'ajuste',        'tipo' => 'AJ', 'destipo' => 'Ajuste de inventario',   'serie' => 'AJ01'],
            ['modulo' => 'transferencia', 'tipo' => 'TR', 'destipo' => 'Transferencia',          'serie' => 'TR01'],
            ['modulo' => 'devolucion',    'tipo' => 'DV', 'destipo' => 'Devolución',             'serie' => 'DV01'],
            ['modulo' => 'fraccionamiento', 'tipo' => 'FR', 'destipo' => 'Fraccionamiento',      'serie' => 'FR01'],
        ];

        foreach (Empresa::all() as $empresa) {
            foreach ($series as $s) {
                Correlativo::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'tipo' => $s['tipo'], 'serie' => $s['serie']],
                    ['modulo' => $s['modulo'], 'destipo' => $s['destipo'], 'numero' => 0, 'activo' => true]
                );
            }
        }
    }
}
