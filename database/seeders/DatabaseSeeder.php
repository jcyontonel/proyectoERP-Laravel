<?php

namespace Database\Seeders;

use Database\Seeders\Negocios\BoticaSeeder;
use Database\Seeders\Negocios\CaralCodeSeeder;
use Database\Seeders\Negocios\RicoPetSeeder;
use Database\Seeders\Negocios\TiendaVariedadesSeeder;
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

            // 3) Un seeder por negocio: empresa (almacén, caja, series), usuarios, categorías, productos con stock,
            //    clientes, proveedores y plan. Jhonnatan es Administrador en todas.
            CaralCodeSeeder::class,          // consultora dueña del ERP: sus servicios incluyen la suscripción
            RicoPetSeeder::class,            // tienda de mascotas
            BoticaSeeder::class,             // farmacia (roles propios, lotes y vencimientos)
            TiendaVariedadesSeeder::class,   // minimarket de productos variados

            //EmpresaSeeder::class,   // empresas demo al azar para probar multiempresa
            //UsuarioSeeder::class,   // un usuario por rol en cada empresa demo
        ]);
    }
}
