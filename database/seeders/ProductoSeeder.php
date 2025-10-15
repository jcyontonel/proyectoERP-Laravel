<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\Empresa;
use App\Models\Categoria;
use App\Models\TipoUnidad;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresas = Empresa::all();
        $unidad = TipoUnidad::inRandomOrder()->first();

        foreach ($empresas as $empresa) {
            $categoria = Categoria::where('empresa_id', $empresa->id)->first();

            if (!$categoria) {
                $this->command->warn("⚠️ No se encontró categoría para la empresa ID {$empresa->id}, se omite.");
                continue;
            }

            Producto::factory()
                ->count(5)
                ->state([
                    'empresa_id' => $empresa->id,
                    'categoria_id' => $categoria->id,
                    'tipo_unidad_id' => $unidad->id,
                ])
                ->create();
        }

    }
}
