<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Services\EmpresaService;
use Illuminate\Database\Seeder;

class CajaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Empresa::all() as $empresa) {
            app(EmpresaService::class)->cajaPrincipal($empresa);
        }
    }
}
