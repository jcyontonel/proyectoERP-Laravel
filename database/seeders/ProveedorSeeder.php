<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Proveedor;

class ProveedorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Genera 20 proveedores distribuidos entre las empresas existentes
        Proveedor::factory()->count(20)->create();
    }
}
