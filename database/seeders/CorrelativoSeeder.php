<?php

namespace Database\Seeders;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Services\EmpresaService;
use Illuminate\Database\Seeder;

/** Series iniciales de cada empresa (la lista está en EmpresaService::SERIES) */
class CorrelativoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Empresa::all() as $empresa) {
            app(EmpresaService::class)->series($empresa);
        }
    }
}
