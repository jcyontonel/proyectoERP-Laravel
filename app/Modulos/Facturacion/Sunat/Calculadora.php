<?php

namespace App\Modulos\Facturacion\Sunat;

use Illuminate\Validation\ValidationException;

/**
 * Montos de cada línea y totales tal como los valida SUNAT:
 *  - valor unitario sin IGV, descuento por línea (cargo "00", afecta la base), valor de venta, IGV y precio unitario.
 *  - Si el sistema envía valor_venta e igv de la línea, se respetan (deben cuadrar con el cálculo: tolerancia S/ 1),
 *    así el XML coincide al céntimo con lo que el sistema ya registró.
 * Afectación (catálogo 07): 10 gravado, 20 exonerado, 30 inafecto, 40 exportación.
 */
class Calculadora
{
    public const GRAVADO = '10';
    public const EXONERADO = '20';
    public const INAFECTO = '30';
    public const EXPORTACION = '40';

    private const TOLERANCIA = 1.0;

    /**
     * @param  array<int, array{cantidad: float, precio_unitario: float, descuento?: float, afectacion?: string, valor_venta?: ?float, igv?: ?float}>  $items
     * @return array{items: array, totales: array}
     */
    public static function calcular(array $items, float $igvPct, bool $preciosIncluyenIgv): array
    {
        $tasa = $igvPct / 100;
        $tot = ['gravadas' => 0.0, 'exoneradas' => 0.0, 'inafectas' => 0.0, 'exportacion' => 0.0, 'igv' => 0.0, 'descuentos' => 0.0];
        $lineas = [];

        foreach (array_values($items) as $i => $it) {
            $afect = (string) ($it['afectacion'] ?? self::GRAVADO);
            $gravado = $afect === self::GRAVADO;
            $cant = (float) $it['cantidad'];
            $precio = (float) $it['precio_unitario'];
            $desc = (float) ($it['descuento'] ?? 0);

            if ($preciosIncluyenIgv) {
                $neto = round(round($precio * $cant, 2) - $desc, 2);
                $base = $gravado ? round($neto / (1 + $tasa), 2) : $neto;
                $igv = $gravado ? round($neto - $base, 2) : 0.0;
                $valorUnit = $gravado ? $precio / (1 + $tasa) : $precio;
            } else {
                $base = round(round($precio * $cant, 2) - $desc, 2);
                $igv = $gravado ? round($base * $tasa, 2) : 0.0;
                $valorUnit = $precio;
            }

            // Montos que ya calculó el sistema cliente
            if (isset($it['valor_venta'], $it['igv'])) {
                if (abs($it['valor_venta'] - $base) > self::TOLERANCIA || abs($it['igv'] - $igv) > self::TOLERANCIA) {
                    throw ValidationException::withMessages(["items.$i" => "Los montos de la línea " . ($i + 1) . " no cuadran: con cantidad y precio da base {$base} e IGV {$igv}."]);
                }
                $base = round((float) $it['valor_venta'], 2);
                $igv = $gravado ? round((float) $it['igv'], 2) : 0.0;
            }
            if ($base < 0) {
                throw ValidationException::withMessages(["items.$i.descuento" => 'El descuento de la línea ' . ($i + 1) . ' supera su importe.']);
            }

            $bruto = round($valorUnit * $cant, 2);
            if ($bruto < $base) {   // redondeo: el valor unitario no puede quedar debajo de la base
                $valorUnit = $base / $cant;
                $bruto = $base;
            }
            $descBase = round($bruto - $base, 2);

            $lineas[] = [
                'codigo'          => $it['codigo'] ?? null,
                'descripcion'     => $it['descripcion'],
                'unidad'          => $it['unidad'] ?? 'NIU',
                'cantidad'        => $cant,
                'afectacion'      => $afect,
                'valor_unitario'  => round($valorUnit, 10),
                'precio_unitario' => round(($base + $igv) / $cant, 10),
                'valor_bruto'     => $bruto,
                'descuento'       => $descBase,
                'valor_venta'     => $base,
                'igv'             => $igv,
                'total'           => round($base + $igv, 2),
            ];

            $clave = match ($afect) {
                self::GRAVADO => 'gravadas', self::EXONERADO => 'exoneradas', self::INAFECTO => 'inafectas', default => 'exportacion',
            };
            $tot[$clave] += $base;
            $tot['igv'] += $igv;
            $tot['descuentos'] += $descBase;
        }

        $tot = array_map(fn ($v) => round($v, 2), $tot);
        $tot['valor_venta'] = round($tot['gravadas'] + $tot['exoneradas'] + $tot['inafectas'] + $tot['exportacion'], 2);
        $tot['total'] = round($tot['valor_venta'] + $tot['igv'], 2);

        return ['items' => $lineas, 'totales' => $tot];
    }
}
