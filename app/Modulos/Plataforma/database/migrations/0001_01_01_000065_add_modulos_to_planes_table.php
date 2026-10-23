<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Módulos por plan (config suscripciones.modulos) */
    private const BASICO = ['notas', 'cuentas_cobrar', 'reportes_basicos'];
    private const PRO = ['cotizaciones', 'compras', 'cuentas_pagar', 'inventario', 'reportes_avanzados', 'roles'];
    private const TOTAL = ['ordenes_compra', 'multialmacen', 'lotes', 'fraccionamientos', 'auditoria'];

    /**
     * Tres planes en orden: Básico, Pro y Total. "premium" pasa a ser "total" (mismo registro: sus
     * suscripciones siguen igual); "pro" es nuevo; "gratis" sigue desactivado.
     */
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->json('modulos')->nullable()->after('max_almacenes');   // null = todos los módulos
        });

        $planes = [
            'basico' => ['nombre' => 'Básico', 'orden' => 1, 'descripcion' => 'Para empezar a vender y facturar productos y servicios en un local.',
                'max_comprobantes_mes' => 500, 'max_productos' => 1000, 'max_usuarios' => 3, 'max_almacenes' => 1,
                'modulos' => self::BASICO, 'precios' => ['mensual' => 49, 'trimestral' => 139, 'anual' => 490]],
            'pro' => ['nombre' => 'Pro', 'orden' => 2, 'descripcion' => 'Controla compras e inventario y cotiza tus servicios.',
                'max_comprobantes_mes' => 2000, 'max_productos' => 5000, 'max_usuarios' => 8, 'max_almacenes' => 2,
                'modulos' => [...self::BASICO, ...self::PRO], 'precios' => ['mensual' => 89, 'trimestral' => 249, 'anual' => 890]],
            'total' => ['nombre' => 'Total', 'orden' => 3, 'descripcion' => 'Todo el sistema: varias sedes, lotes, fraccionamientos y auditoría.',
                'max_comprobantes_mes' => null, 'max_productos' => null, 'max_usuarios' => 20, 'max_almacenes' => 10,
                'modulos' => [...self::BASICO, ...self::PRO, ...self::TOTAL], 'precios' => ['mensual' => 149, 'trimestral' => 419, 'anual' => 1490]],
        ];
        $meses = ['mensual' => 1, 'trimestral' => 3, 'anual' => 12];

        DB::transaction(function () use ($planes, $meses) {
            DB::table('planes')->where('codigo', 'premium')->update(['codigo' => 'total']);
            DB::table('planes')->where('codigo', 'gratis')->update(['orden' => 9, 'activo' => false, 'modulos' => json_encode(self::BASICO)]);

            foreach ($planes as $codigo => $p) {
                $precios = $p['precios'];
                unset($p['precios']);
                $p['modulos'] = json_encode($p['modulos']);
                $existe = DB::table('planes')->where('codigo', $codigo)->exists();
                $existe
                    ? DB::table('planes')->where('codigo', $codigo)->update($p + ['updated_at' => now()])
                    : DB::table('planes')->insert($p + ['codigo' => $codigo, 'dias_prueba' => 15, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
                $id = DB::table('planes')->where('codigo', $codigo)->value('id');

                foreach ($precios as $periodicidad => $precio) {
                    DB::table('plan_precios')->updateOrInsert(
                        ['plan_id' => $id, 'periodicidad' => $periodicidad],
                        ['meses' => $meses[$periodicidad], 'precio' => $precio, 'activo' => true, 'updated_at' => now()],
                    );
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('planes')->where('codigo', 'total')->update(['codigo' => 'premium', 'nombre' => 'Premium']);
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn('modulos');
        });
    }
};
