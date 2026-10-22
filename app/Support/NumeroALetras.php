<?php

namespace App\Support;

/** 410.29 → "CUATROCIENTOS DIEZ CON 29/100 SOLES" (formato de comprobantes) */
class NumeroALetras
{
    private const UNIDADES = [
        '', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDOS', 'VEINTITRES', 'VEINTICUATRO', 'VEINTICINCO',
        'VEINTISEIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE',
    ];

    private const DECENAS = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];

    private const CENTENAS = [
        1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS',
        6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS',
    ];

    public static function moneda(float $monto, string $moneda = 'SOLES'): string
    {
        $centavosTotales = (int) round($monto * 100);
        $entero  = intdiv($centavosTotales, 100);
        $centavo = $centavosTotales % 100;

        return self::convertir($entero) . ' CON ' . str_pad((string) $centavo, 2, '0', STR_PAD_LEFT) . '/100 ' . $moneda;
    }

    private static function convertir(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }
        if ($n === 100) {
            return 'CIEN';
        }
        if ($n < 30) {
            return self::UNIDADES[$n];
        }
        if ($n < 100) {
            $u = $n % 10;
            return self::DECENAS[intdiv($n, 10)] . ($u ? ' Y ' . self::UNIDADES[$u] : '');
        }
        if ($n < 1000) {
            $r = $n % 100;
            return self::CENTENAS[intdiv($n, 100)] . ($r ? ' ' . self::convertir($r) : '');
        }
        if ($n < 1_000_000) {
            $miles = intdiv($n, 1000);
            $r     = $n % 1000;
            $texto = $miles === 1 ? 'MIL' : self::apocope(self::convertir($miles)) . ' MIL';
            return $texto . ($r ? ' ' . self::convertir($r) : '');
        }

        $millones = intdiv($n, 1_000_000);
        $r        = $n % 1_000_000;
        $texto    = $millones === 1 ? 'UN MILLON' : self::apocope(self::convertir($millones)) . ' MILLONES';

        return $texto . ($r ? ' ' . self::convertir($r) : '');
    }

    /** "VEINTIUNO" → "VEINTIUN" cuando va antes de MIL / MILLONES */
    private static function apocope(string $texto): string
    {
        return preg_replace('/UNO$/', 'UN', $texto);
    }
}
