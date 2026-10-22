<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class ProveedorSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('ruc', '20000000001')->firstOrFail();

        Proveedor::factory()->count(10)->create(['empresa_id' => $empresa->id]);
    }
}
