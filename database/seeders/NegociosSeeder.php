<?php

namespace Database\Seeders;

use Database\Seeders\Negocios\BoticaSeeder;
use Database\Seeders\Negocios\CaralCodeSeeder;
use Database\Seeders\Negocios\RicoPetSeeder;
use Database\Seeders\Negocios\TiendaVariedadesSeeder;
use Illuminate\Database\Seeder;

/**
 * Solo los negocios, sobre una base que ya tiene catálogos, roles y planes:
 *   php artisan db:seed --class=NegociosSeeder
 * No borra nada: crea lo que falta y no toca lo que ya existe (ni contraseñas ni suscripciones).
 */
class NegociosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CaralCodeSeeder::class,
            RicoPetSeeder::class,
            BoticaSeeder::class,
            TiendaVariedadesSeeder::class,
        ]);
    }
}
