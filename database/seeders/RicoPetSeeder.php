<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Categoria;
use App\Models\TipoUnidad;
use App\Models\Producto;
use App\Models\Correlativo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class RicoPetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $empresa = Empresa::create([
            'ruc' => '20000000001',
            'razon_social' => 'Rico Pet SAC',
            'direccion' => 'Calle Ferrocarril 235, Barranca - Lima',
            'telefono' => '(+51) 123-4567',
            'email' => '',
            'activo' => true,
        ]);

        // Usuario Admin general
        $user = User::create([
            'name' => 'Admin General',
            'email' => 'jhonnatan@erp.com',
            'password' => Hash::make('admin123'),
        ])->assignRole('Administrador');
        $user->assignRole('Administrador');
        $user->empresas()->attach($empresa->id);

        $user = User::create([
            'name' => 'Admin General',
            'email' => 'sofia@erp.com',
            'password' => Hash::make('admin123'),
        ])->assignRole('Administrador');
        $user->assignRole('Administrador');
        $user->empresas()->attach($empresa->id);

        // Crear una categoría y tipo de unidad por defecto para la empresa
        $categoria = Categoria::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => 'General'],
            ['descripcion' => 'Productos generales para mascotas']
        );

        $unidad = TipoUnidad::firstOrCreate(
            ['descripcion' => 'Unidad'],
            ['codigo' => 'u']
        );

        $correlativo = Correlativo::create([
            'empresa_id' => $empresa->id,
            'tipo' => 'Ticket',
            'serie' => 'T001',
            'numero' => 1,
        ]);

        // Lista de productos base
        $nombres = [
            'Alimento para perros - 1kg',
            'Alimento para gatos - 1kg',
            'Juguete para perros - Pelota',
            'Juguete para gatos - Ratón de juguete',
            'Cama para perros - Mediana',
            'Cama para gatos - Pequeña',
            'Collar para perros - Ajustable',
            'Collar para gatos - Con cascabel',
            'Correa para perros - 1.5m',
            'Caja de arena para gatos - Mediana',
            'Arena para gatos - 5kg',
            'Shampoo para perros - 250ml',
            'Shampoo para gatos - 250ml',
            'Cepillo para perros - Mediano',
            'Cepillo para gatos - Pequeño',
            'Plato para perros - Acero inoxidable',
            'Plato para gatos - Cerámica',
            'Transportadora para perros - Pequeña',
            'Transportadora para gatos - Pequeña',
            'Rascador para gatos - Mediano',
            'Premios para perros - 200g',
            'Premios para gatos - 200g',
        ];

        foreach ($nombres as $nombre) {
            Producto::factory()->create([
                'empresa_id' => $empresa->id,
                'categoria_id' => $categoria->id,
                'tipo_unidad_id' => $unidad->id,
                'nombre' => $nombre,
                'precio_unitario' => $faker->randomFloat(2, 3, 50),
            ]);
        }
    }
}
