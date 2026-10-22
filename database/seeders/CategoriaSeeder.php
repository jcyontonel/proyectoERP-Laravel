<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::where('ruc', '20000000001')
            ->firstOrFail();

        $categorias = [
            ['empresa_id' => $empresa->id, 'nombre' => 'Alimentos perros', 'descripcion' => 'Alimentos diseñados para cubrir las necesidades nutricionales diarias del perro.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Snacks perros', 'descripcion' => 'Premios y golosinas para entrenamiento y refuerzo positivo.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Juguetes perros', 'descripcion' => 'Juguetes diseñados para estimular física y mentalmente al perro.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Ropa perros', 'descripcion' => 'Prendas para protección del clima o uso estético en perros.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Camas perros', 'descripcion' => 'Camas y colchones para el descanso cómodo del perro.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Accesorios perros', 'descripcion' => 'Artículos como collares, arneses, platos y otros complementos.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Higiene perros', 'descripcion' => 'Productos de limpieza como shampoo, toallitas y cepillos para perros.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Salud perros', 'descripcion' => 'Vitaminas, antipulgas y productos veterinarios para el bienestar del perro.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Transportadoras perros', 'descripcion' => 'Jaulas y mochilas para traslado seguro del perro.'],

            ['empresa_id' => $empresa->id, 'nombre' => 'Alimentos gatos', 'descripcion' => 'Alimentos formulados para cubrir las necesidades nutricionales del gato.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Snacks gatos', 'descripcion' => 'Premios y bocaditos especiales para gatos.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Juguetes gatos', 'descripcion' => 'Juguetes que estimulan el instinto cazador y la actividad del gato.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Ropa gatos', 'descripcion' => 'Prendas ligeras o protectoras diseñadas para gatos.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Camas gatos', 'descripcion' => 'Camas y refugios cómodos donde el gato puede descansar y dormir.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Accesorios gatos', 'descripcion' => 'Collares, platos, areneros y otros complementos para gatos.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Higiene gatos', 'descripcion' => 'Productos como arena, shampoo y toallitas para la limpieza felina.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Salud gatos', 'descripcion' => 'Suplementos, antipulgas y productos veterinarios para gatos.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Transportadoras gatos', 'descripcion' => 'Mochilas y cajas para transportar al gato de forma segura.'],

            ['empresa_id' => $empresa->id, 'nombre' => 'Alimentos roedores', 'descripcion' => 'Alimentos balanceados para conejos, hámsters y otros roedores.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Snacks roedores', 'descripcion' => 'Golosinas ligeras para enriquecer la dieta de pequeños roedores.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Juguetes roedores', 'descripcion' => 'Juguetes para morder y entretener a roedores.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Camas roedores', 'descripcion' => 'Refugios y materiales para descanso cómodo de pequeños roedores.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Accesorios roedores', 'descripcion' => 'Bebederos, comederos y elementos para jaulas de roedores.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Higiene roedores', 'descripcion' => 'Sustratos y productos para mantener limpia la jaula.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Salud roedores', 'descripcion' => 'Suplementos y productos básicos para el bienestar de roedores.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Transportadoras roedores', 'descripcion' => 'Cajas pequeñas para traslado seguro de roedores.'],

            ['empresa_id' => $empresa->id, 'nombre' => 'Alimentos aves', 'descripcion' => 'Mezcla de semillas o pellets formulados para aves pequeñas y grandes.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Snacks aves', 'descripcion' => 'Premios y golosinas que complementan la dieta de las aves.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Juguetes aves', 'descripcion' => 'Artículos que estimulan la actividad y reducen el estrés en aves.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Accesorios aves', 'descripcion' => 'Perchas, comederos, columpios y artículos para jaulas.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Higiene aves', 'descripcion' => 'Productos para limpieza y cuidado del plumaje.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Salud aves', 'descripcion' => 'Vitaminas y suplementos que fortalecen la salud de las aves.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Transportadoras aves', 'descripcion' => 'Jaulas de transporte seguras para aves domésticas.'],

            ['empresa_id' => $empresa->id, 'nombre' => 'Alimentos peces', 'descripcion' => 'Alimento en escamas o pellet diseñado para peces de acuario.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Accesorios peces', 'descripcion' => 'Decoración, filtros y equipamiento para acuarios.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Salud peces', 'descripcion' => 'Tratamientos para el agua y productos que previenen enfermedades.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Higiene peces', 'descripcion' => 'Materiales para mantenimiento y limpieza del acuario.'],
            ['empresa_id' => $empresa->id, 'nombre' => 'Transportadoras peces', 'descripcion' => 'Recipientes apropiados para traslado corto de peces.'],
        ]  ;

        foreach ($categorias as $categoria) {
            Categoria::updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'nombre' => $categoria['nombre'],
                ],
                [
                    'descripcion' => $categoria['descripcion'],
                ]
            );
        }
    }
}