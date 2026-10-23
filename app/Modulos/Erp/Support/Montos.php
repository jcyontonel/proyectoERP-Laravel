<?php

namespace App\Modulos\Erp\Support;

/**
 * Totales de un documento de venta (venta, cotización, nota): descuento global, base imponible e IGV.
 * Es la misma fórmula que usa el navegador. El residuo de redondeo va a la última línea, así la suma
 * de las líneas siempre coincide con la cabecera.
 */
class Montos
{
    /**
     * @param  array<int, array{gross: float}>  $lineas  importe bruto de cada línea (precio × cantidad); se conservan sus demás claves
     * @return array{bruto: float, descuento: float, base: float, igv: float, total: float, lineas: array}
     */
    public static function calcular(array $lineas, float $descuentoPct, float $igvPct, bool $incluyeIgv): array
    {
        $bruto = round(array_sum(array_column($lineas, 'gross')), 2);
        $pct = min(max($descuentoPct, 0), 100);
        $descTotal = round($bruto * $pct / 100, 2);
        $neto = round($bruto - $descTotal, 2);

        if ($incluyeIgv) {
            $total = $neto;
            $base = round($total / (1 + $igvPct / 100), 2);
            $igv = round($total - $base, 2);
        } else {
            $base = $neto;
            $igv = round($base * $igvPct / 100, 2);
            $total = round($base + $igv, 2);
        }

        // Reparto por línea (descuento, base e IGV)
        foreach ($lineas as &$l) {
            $l['desc'] = round($l['gross'] * $pct / 100, 2);
            $netoLinea = round($l['gross'] - $l['desc'], 2);
            if ($incluyeIgv) {
                $l['base'] = round($netoLinea / (1 + $igvPct / 100), 2);
                $l['igv'] = round($netoLinea - $l['base'], 2);
            } else {
                $l['base'] = $netoLinea;
                $l['igv'] = round($netoLinea * $igvPct / 100, 2);
            }
        }
        unset($l);

        if ($lineas) {
            $ult = array_key_last($lineas);
            $lineas[$ult]['desc'] = round($lineas[$ult]['desc'] + ($descTotal - array_sum(array_column($lineas, 'desc'))), 2);
            $lineas[$ult]['base'] = round($lineas[$ult]['base'] + ($base - array_sum(array_column($lineas, 'base'))), 2);
            $lineas[$ult]['igv'] = round($lineas[$ult]['igv'] + ($igv - array_sum(array_column($lineas, 'igv'))), 2);
        }
        foreach ($lineas as &$l) {
            $l['total'] = round($l['base'] + $l['igv'], 2);
        }
        unset($l);

        return ['bruto' => $bruto, 'pct' => $pct, 'descuento' => $descTotal, 'base' => $base, 'igv' => $igv, 'total' => $total, 'lineas' => $lineas];
    }

    /** Un monto con IGV incluido (o no) → [base, igv, total] */
    public static function desglosar(float $monto, float $igvPct, bool $incluyeIgv = true): array
    {
        $r = self::calcular([['gross' => round($monto, 2)]], 0, $igvPct, $incluyeIgv);

        return [$r['base'], $r['igv'], $r['total']];
    }
}
