<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fragmentos SQL que cambian según el motor (MySQL/MariaDB, PostgreSQL/Supabase, SQLite de pruebas).
 * Úsalos en selectRaw/groupByRaw para que los reportes funcionen igual en todos.
 */
class Sql
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /** Fecha como texto agrupable: 'dia' → 2026-10-21 · 'mes' → 2026-10 */
    public static function periodo(string $columna, string $agrupar = 'dia'): string
    {
        $mes = $agrupar === 'mes';

        return match (self::driver()) {
            'pgsql'  => "to_char({$columna}, '" . ($mes ? 'YYYY-MM' : 'YYYY-MM-DD') . "')",
            'sqlite' => "strftime('" . ($mes ? '%Y-%m' : '%Y-%m-%d') . "', {$columna})",
            default  => "DATE_FORMAT({$columna}, '" . ($mes ? '%Y-%m' : '%Y-%m-%d') . "')",
        };
    }

    /** Número rellenado con ceros a la izquierda como texto (LPAD exige texto en PostgreSQL) */
    public static function lpad(string $columna, int $largo): string
    {
        return match (self::driver()) {
            'pgsql'  => "LPAD(CAST({$columna} AS TEXT), {$largo}, '0')",
            'sqlite' => "substr('" . str_repeat('0', $largo) . "' || {$columna}, -{$largo}, {$largo})",
            default  => "LPAD({$columna}, {$largo}, '0')",
        };
    }

    /** Concatenación de textos (SQLite no tiene CONCAT antes de 3.44) */
    public static function concat(string ...$partes): string
    {
        return self::driver() === 'sqlite'
            ? implode(' || ', $partes)
            : 'CONCAT(' . implode(', ', $partes) . ')';
    }
}
