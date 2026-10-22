<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanPrecio;
use Illuminate\Database\Seeder;

/** Planes iniciales: Gratis (desactivado), Básico y Premium, con precio mensual, trimestral y anual */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Límite NULL = ilimitado
        $planes = [
            ['codigo' => 'gratis', 'nombre' => 'Gratis', 'descripcion' => 'Para probar el sistema con pocos documentos.', 'orden' => 1, 'activo' => false,
                'max_comprobantes_mes' => 50, 'max_productos' => 50, 'max_usuarios' => 1, 'max_almacenes' => 1, 'dias_prueba' => 0,
                'precios' => ['mensual' => 0]],
            ['codigo' => 'basico', 'nombre' => 'Básico', 'descripcion' => 'Para negocios con un local.', 'orden' => 2, 'activo' => true,
                'max_comprobantes_mes' => 500, 'max_productos' => 1000, 'max_usuarios' => 3, 'max_almacenes' => 1, 'dias_prueba' => 15,
                'precios' => ['mensual' => 49, 'trimestral' => 139, 'anual' => 490]],
            ['codigo' => 'premium', 'nombre' => 'Premium', 'descripcion' => 'Sin límite de comprobantes ni productos, varias sedes.', 'orden' => 3, 'activo' => true,
                'max_comprobantes_mes' => null, 'max_productos' => null, 'max_usuarios' => 10, 'max_almacenes' => 5, 'dias_prueba' => 15,
                'precios' => ['mensual' => 99, 'trimestral' => 279, 'anual' => 990]],
        ];

        foreach ($planes as $datos) {
            $precios = $datos['precios'];
            unset($datos['precios']);

            // firstOrCreate: si el administrador ya ajustó un plan, el seeder no lo pisa
            $plan = Plan::firstOrCreate(['codigo' => $datos['codigo']], $datos);

            foreach ($precios as $periodicidad => $precio) {
                PlanPrecio::firstOrCreate(
                    ['plan_id' => $plan->id, 'periodicidad' => $periodicidad],
                    ['meses' => config('suscripciones.periodicidades')[$periodicidad][1], 'precio' => $precio, 'activo' => true]
                );
            }
        }
    }
}
