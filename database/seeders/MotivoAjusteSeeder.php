<?php

namespace Database\Seeders;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Erp\Services\EmpresaService;
use Illuminate\Database\Seeder;

/** Motivos de ajuste de cada empresa (la lista está en EmpresaService::MOTIVOS_AJUSTE) */
class MotivoAjusteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Empresa::all() as $empresa) {
            app(EmpresaService::class)->motivosAjuste($empresa);
        }
    }
}
