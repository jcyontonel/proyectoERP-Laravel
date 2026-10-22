<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Services\EmpresaService;
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
