<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los planes de la página web pasan a ser un sistema propio (ventas, clientes, productos y facturación en su base,
     * config suscripciones.productos.web) y necesitan límites: sin ellos, una empresa con ERP + WEB quedaba sin límites
     * (se toma el mayor de sus planes). Solo se tocan los planes que siguen como se crearon (límites vacíos): si el
     * administrador ya los ajustó desde Plataforma, se respetan.
     */
    private const PLANES = [
        'catalogo' => ['max_comprobantes_mes' => 300, 'max_productos' => 300, 'max_usuarios' => 2, 'max_almacenes' => 1,
            'modulos' => []],
        'tienda' => ['max_comprobantes_mes' => 1000, 'max_productos' => 2000, 'max_usuarios' => 5, 'max_almacenes' => 1,
            'modulos' => ['web_tienda', 'reportes_basicos', 'notas']],
    ];

    public function up(): void
    {
        foreach (self::PLANES as $codigo => $p) {
            DB::table('planes')->where('codigo', $codigo)
                ->whereNull('max_comprobantes_mes')->whereNull('max_productos')->whereNull('max_usuarios')->whereNull('max_almacenes')
                ->update(['modulos' => json_encode($p['modulos'])] + $p + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('planes')->where('codigo', 'catalogo')->update(['modulos' => json_encode(['web_catalogo'])]);
        DB::table('planes')->where('codigo', 'tienda')->update(['modulos' => json_encode(['web_catalogo', 'web_tienda'])]);
        DB::table('planes')->whereIn('codigo', array_keys(self::PLANES))->update([
            'max_comprobantes_mes' => null, 'max_productos' => null, 'max_usuarios' => null, 'max_almacenes' => null,
        ]);
    }
};
