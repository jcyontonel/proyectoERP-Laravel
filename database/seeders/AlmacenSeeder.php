<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class AlmacenSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Empresa::all() as $empresa) {
            Almacen::firstOrCreate(
                ['empresa_id' => $empresa->id, 'codigo' => 'ALM-01'],
                [
                    'nombre' => 'Almacén Principal',
                    'direccion' => $empresa->direccion ?? 'Dirección General',
                    'es_principal' => true,
                    'activo' => true,
                ]
            );
        }
    }
}
