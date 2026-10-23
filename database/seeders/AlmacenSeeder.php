<?php

namespace Database\Seeders;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Services\EmpresaService;
use Illuminate\Database\Seeder;

class AlmacenSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Empresa::all() as $empresa) {
            app(EmpresaService::class)->almacenPrincipal($empresa);
        }
    }
}
