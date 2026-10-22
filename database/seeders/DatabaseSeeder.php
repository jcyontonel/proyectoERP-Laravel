<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1) Catálogos globales (SUNAT / ISO)
            MonedaSeeder::class,
            TipoDocumentoSeeder::class,
            TipoUnidadSeeder::class,
            ImpuestoSeeder::class,

            // 2) Roles y permisos globales (se asignan por empresa) y planes de suscripción
            RolesYPermisosSeeder::class,
            PlanSeeder::class,

            // 3) Empresas y usuarios
            RicoPetSeeder::class,
            //EmpresaSeeder::class,   // empresas demo para probar multiempresa
            //UsuarioSeeder::class,   // un usuario por rol en cada empresa demo

            // 4) Configuración de cada empresa (recorren todas las empresas)
            AlmacenSeeder::class,
            CorrelativoSeeder::class,
            CajaSeeder::class,
            MotivoAjusteSeeder::class,

            // 5) Datos de RicoPet
            CategoriaSeeder::class,
            ClienteSeeder::class,
            ProveedorSeeder::class,
            ProductoSeeder::class,
            UnidadesConversionSeeder::class,   // presentaciones y reglas de fraccionamiento (saco → kilos)
        ]);
    }
}
