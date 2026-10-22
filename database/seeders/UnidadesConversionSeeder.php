<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Producto;
use App\Models\TipoUnidad;
use App\Models\UnidadConversion;
use Illuminate\Database\Seeder;

/**
 * unidades_conversion de RicoPet:
 *  - Reglas de FRACCIONAMIENTO (producto_destino_id): partir un producto en otro para vender por partes.
 *    Justifican en el kardex de dónde salen los kilos/unidades sueltas que se venden.
 *  - PRESENTACIONES (producto_destino_id NULL): el mismo producto comprado o vendido en otra unidad.
 */
class UnidadesConversionSeeder extends Seeder
{
    /** [producto de origen, producto de destino, cuántas unidades del destino salen de 1 del origen] */
    private const FRACCIONAMIENTOS = [
        ['Alimento seco Ricocan Adulto Pollo 15kg', 'Alimento seco Ricocan Adulto Pollo por kilo', 15],
        ['Alimento seco Whiskas Gatito Pollo 10kg', 'Alimento seco Whiskas Gatito Pollo por kilo', 10],
        ['Arena sanitaria aglomerante 10kg', 'Arena sanitaria aglomerante por kilo', 10],
        ['Snack Pedigree Dentastix Medianos 7 unidades', 'Snack Pedigree Dentastix Medianos (unidad)', 7],
    ];

    /** [producto, código de unidad de la presentación, unidades base por presentación, precio propio] */
    private const PRESENTACIONES = [
        ['Alimento seco Ricocan Adulto Pollo 1kg', 'CJ', 10, 118.00],
        ['Snack Felix Gelatinas 4 unidades', 'CJ', 12, 90.00],
        ['Toallitas húmedas felinas 50 unidades', 'PQT', 3, 22.00],
    ];

    public function run(): void
    {
        $empresa = Empresa::where('ruc', '20000000001')->firstOrFail();
        $producto = fn (string $nombre) => Producto::where('empresa_id', $empresa->id)->where('nombre', $nombre)->first();

        foreach (self::FRACCIONAMIENTOS as [$origen, $destino, $factor]) {
            $o = $producto($origen);
            $d = $producto($destino);
            if (! $o || ! $d) {
                continue;
            }

            UnidadConversion::updateOrCreate(
                ['producto_id' => $o->id, 'producto_destino_id' => $d->id, 'unidad_origen_id' => $o->tipo_unidad_id, 'unidad_destino_id' => $d->tipo_unidad_id],
                ['empresa_id' => $empresa->id, 'factor' => $factor, 'activo' => true],
            );

            // El producto partido toma el costo del origen repartido
            $d->update(['costo_unitario' => round($o->costo_unitario / $factor, 6)]);
        }

        foreach (self::PRESENTACIONES as [$nombre, $codigoUnidad, $factor, $precio]) {
            $p = $producto($nombre);
            $unidad = TipoUnidad::where('codigo', $codigoUnidad)->first();
            if (! $p || ! $unidad || $unidad->id === $p->tipo_unidad_id) {
                continue;
            }

            UnidadConversion::updateOrCreate(
                ['producto_id' => $p->id, 'producto_destino_id' => null, 'unidad_origen_id' => $unidad->id, 'unidad_destino_id' => $p->tipo_unidad_id],
                ['empresa_id' => $empresa->id, 'factor' => $factor, 'precio_venta' => $precio, 'activo' => true],
            );
        }
    }
}
