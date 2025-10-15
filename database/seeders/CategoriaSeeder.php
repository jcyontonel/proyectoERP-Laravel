<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Categoria;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresas = Empresa::all();
        foreach ($empresas as $empresa) {
            Categoria::create([
                'empresa_id' => $empresa->id,
                'nombre' => 'General',
                'descripcion' => 'Productos variados',
            ]);
        }
    }
}
