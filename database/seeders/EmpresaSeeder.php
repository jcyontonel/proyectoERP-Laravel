<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Seeder;

/** Empresas demo para probar el aislamiento multiempresa */
class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::factory()->count(2)->create();
    }
}
