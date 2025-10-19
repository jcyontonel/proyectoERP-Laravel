<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $empresas = Empresa::all();
        $tipoDocs = TipoDocumento::all();

        // Validar que existan empresas y tipos de documentos
        if ($empresas->isEmpty() || $tipoDocs->isEmpty()) {
            $this->command->warn('⚠️ No hay empresas o tipos de documentos registrados. Ejecuta primero sus seeders.');
            return;
        }

        foreach ($empresas as $empresa) {
            Cliente::factory()->count(5)
                        ->create()
                        ->each(fn($cliente) => $cliente->empresas()->attach($empresa->id));
        }
    }
}
