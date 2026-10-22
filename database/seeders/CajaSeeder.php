<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class CajaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Empresa::all() as $empresa) {
            $principal = Almacen::where('empresa_id', $empresa->id)->where('es_principal', true)->first();

            Caja::firstOrCreate(
                ['empresa_id' => $empresa->id, 'codigo' => 'CAJA-01'],
                ['almacen_id' => $principal?->id, 'nombre' => 'Caja Principal', 'activo' => true]
            );
        }
    }
}
