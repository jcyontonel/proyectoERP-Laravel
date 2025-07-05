<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Empresa;
use App\Models\Correlativo;

class CorrelativoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresas = Empresa::all();

        foreach ($empresas as $empresa) {
            Correlativo::insert([
                [
                    'empresa_id' => $empresa->id,
                    'tipo' => 'factura',
                    'serie' => 'F001',
                    'numero' => 0
                ],
                [
                    'empresa_id' => $empresa->id,
                    'tipo' => 'boleta',
                    'serie' => 'B001',
                    'numero' => 0
                ],
            ]);
        }
    }
}
