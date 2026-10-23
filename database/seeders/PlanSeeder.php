<?php

namespace Database\Seeders;

use App\Modulos\Suscripciones\Models\Plan;
use App\Modulos\Suscripciones\Models\PlanPrecio;
use App\Modulos\Suscripciones\Models\ProductoPlataforma;
use Illuminate\Database\Seeder;

/**
 * Productos de la plataforma y sus planes, con módulos y precio mensual, trimestral y anual:
 *  - ERP: Básico, Pro y Total (en ese orden).
 *  - Página web: Catálogo (muestrario) y Tienda (carrito, pedidos, pago en línea y envíos).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $erp = ProductoPlataforma::firstOrCreate(['codigo' => ProductoPlataforma::ERP], [
            'nombre' => 'ERP', 'descripcion' => 'Ventas, caja, facturación electrónica, inventario, compras y finanzas.', 'orden' => 1, 'activo' => true,
        ]);
        $web = ProductoPlataforma::firstOrCreate(['codigo' => ProductoPlataforma::WEB], [
            'nombre' => 'Página web', 'descripcion' => 'La página de la empresa con sus productos: catálogo o tienda con carrito, pedidos y envíos.', 'orden' => 2, 'activo' => true,
        ]);

        // Límite NULL = ilimitado. Módulos: claves de config suscripciones.modulos / modulos_web (lo demás es la base)
        $basico = ['notas', 'cuentas_cobrar', 'reportes_basicos'];
        $pro = [...$basico, 'cotizaciones', 'compras', 'cuentas_pagar', 'inventario', 'reportes_avanzados', 'roles'];
        $total = [...$pro, 'ordenes_compra', 'multialmacen', 'lotes', 'fraccionamientos', 'auditoria'];

        $planes = [
            ['producto_id' => $erp->id, 'codigo' => 'basico', 'nombre' => 'Básico', 'descripcion' => 'Para empezar a vender y facturar productos y servicios en un local.', 'orden' => 1, 'activo' => true,
                'max_comprobantes_mes' => 500, 'max_productos' => 1000, 'max_usuarios' => 3, 'max_almacenes' => 1, 'max_empresas' => 1, 'dias_prueba' => 15, 'modulos' => $basico,
                'precios' => ['mensual' => 49, 'trimestral' => 139, 'anual' => 490]],
            ['producto_id' => $erp->id, 'codigo' => 'pro', 'nombre' => 'Pro', 'descripcion' => 'Controla compras e inventario y cotiza tus servicios.', 'orden' => 2, 'activo' => true,
                'max_comprobantes_mes' => 2000, 'max_productos' => 5000, 'max_usuarios' => 8, 'max_almacenes' => 2, 'max_empresas' => 2, 'dias_prueba' => 15, 'modulos' => $pro,
                'precios' => ['mensual' => 89, 'trimestral' => 249, 'anual' => 890]],
            ['producto_id' => $erp->id, 'codigo' => 'total', 'nombre' => 'Total', 'descripcion' => 'Todo el sistema: varias sedes, lotes, fraccionamientos y auditoría.', 'orden' => 3, 'activo' => true,
                'max_comprobantes_mes' => null, 'max_productos' => null, 'max_usuarios' => 20, 'max_almacenes' => 10, 'max_empresas' => 5, 'dias_prueba' => 15, 'modulos' => $total,
                'precios' => ['mensual' => 149, 'trimestral' => 419, 'anual' => 1490]],
            ['producto_id' => $web->id, 'codigo' => 'catalogo', 'nombre' => 'Catálogo', 'descripcion' => 'Tu carta o muestrario en internet: productos, precios y contacto por WhatsApp.', 'orden' => 4, 'activo' => true,
                'max_empresas' => 1, 'dias_prueba' => 15, 'modulos' => ['web_catalogo'],
                'precios' => ['mensual' => 29, 'trimestral' => 79, 'anual' => 290]],
            ['producto_id' => $web->id, 'codigo' => 'tienda', 'nombre' => 'Tienda', 'descripcion' => 'Tienda en línea: carrito, pedidos, pago con tarjeta y envíos por zona.', 'orden' => 5, 'activo' => true,
                'max_empresas' => 1, 'dias_prueba' => 15, 'modulos' => ['web_catalogo', 'web_tienda'],
                'precios' => ['mensual' => 69, 'trimestral' => 189, 'anual' => 690]],
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
