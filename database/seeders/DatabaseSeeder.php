<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesYPermisosSeeder::class,
            EmpresaSeeder::class,
            UsuarioSeeder::class,
            TipoDocumentoSeeder::class,
            CorrelativoSeeder::class,
            ClienteSeeder::class,
            TipoUnidadSeeder::class,
            CategoriaSeeder::class,
            ProductoSeeder::class,
            VentaSeeder::class,
            DetalleVentaSeeder::class,
            ProveedorSeeder::class,
            RicoPetSeeder::class,
        ]);
    }
}
