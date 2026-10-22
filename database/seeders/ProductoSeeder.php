<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\AlmacenProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductoSeeder extends Seeder
{
    private const STOCK_INICIAL = 5;
    private const STOCK_MINIMO  = 5;

    public function run(InventarioService $inventario): void
    {
        DB::transaction(function () use ($inventario) {
            $empresa = Empresa::where('ruc', '20000000001')->firstOrFail();

            $almacen = Almacen::where('empresa_id', $empresa->id)
                ->where('codigo', 'ALM-01')
                ->firstOrFail();

            $usuarioId = $empresa->usuarios()->orderBy('users.id')->value('users.id');

            $productosData = require database_path('seeders/data/productos.php');

            foreach ($productosData as $data) {
                // Costo de referencia (sin IGV) si el dato no lo trae: ~60 % del precio, para que los reportes de utilidad tengan sentido
                $data['costo_unitario'] ??= round($data['precio_unitario'] / 1.18 * 0.72, 2);

                $producto = Producto::create($data + ['empresa_id' => $empresa->id]);

                if ($producto->es_servicio) {
                    continue;
                }

                // El stock inicial pasa por InventarioService: queda en almacen_producto Y en kardex
                $inventario->ingreso($producto, $almacen->id, self::STOCK_INICIAL, [
                    'empresa_id' => $empresa->id,
                    'user_id'    => $usuarioId,
                    'ref'        => ['tipo' => 'stock_inicial'],
                    'costo'      => $producto->costo_unitario,
                    'fecha'      => Carbon::now(),
                    'obs'        => 'Stock inicial (seeder)',
                ]);

                AlmacenProducto::where('almacen_id', $almacen->id)
                    ->where('producto_id', $producto->id)
                    ->update(['stock_minimo' => self::STOCK_MINIMO]);
            }
        });
    }
}
