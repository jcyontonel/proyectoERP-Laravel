<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanPrecio;
use Illuminate\Database\Seeder;

/** Planes: Básico, Pro y Total (en ese orden), con sus módulos y precio mensual, trimestral y anual */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Límite NULL = ilimitado. Módulos: claves de config suscripciones.modulos (lo demás es la base de todos)
        $basico = ['notas', 'cuentas_cobrar', 'reportes_basicos'];
        $pro = [...$basico, 'cotizaciones', 'compras', 'cuentas_pagar', 'inventario', 'reportes_avanzados', 'roles'];
        $total = [...$pro, 'ordenes_compra', 'multialmacen', 'lotes', 'fraccionamientos', 'auditoria'];

        $planes = [
            ['codigo' => 'basico', 'nombre' => 'Básico', 'descripcion' => 'Para empezar a vender y facturar productos y servicios en un local.', 'orden' => 1, 'activo' => true,
                'max_comprobantes_mes' => 500, 'max_productos' => 1000, 'max_usuarios' => 3, 'max_almacenes' => 1, 'dias_prueba' => 15, 'modulos' => $basico,
                'precios' => ['mensual' => 49, 'trimestral' => 139, 'anual' => 490]],
            ['codigo' => 'pro', 'nombre' => 'Pro', 'descripcion' => 'Controla compras e inventario y cotiza tus servicios.', 'orden' => 2, 'activo' => true,
                'max_comprobantes_mes' => 2000, 'max_productos' => 5000, 'max_usuarios' => 8, 'max_almacenes' => 2, 'dias_prueba' => 15, 'modulos' => $pro,
                'precios' => ['mensual' => 89, 'trimestral' => 249, 'anual' => 890]],
            ['codigo' => 'total', 'nombre' => 'Total', 'descripcion' => 'Todo el sistema: varias sedes, lotes, fraccionamientos y auditoría.', 'orden' => 3, 'activo' => true,
                'max_comprobantes_mes' => null, 'max_productos' => null, 'max_usuarios' => 20, 'max_almacenes' => 10, 'dias_prueba' => 15, 'modulos' => $total,
                'precios' => ['mensual' => 149, 'trimestral' => 419, 'anual' => 1490]],
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
