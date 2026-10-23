<?php

namespace App\Modulos\Catalogo\Support;

/** Formato de cantidades: el stock se guarda con 4 decimales pero se muestra sin ceros de más */
class Unidades
{
    /** 148.5000 → "148.5" · 12.0000 → "12" · 1250.25 → "1,250.25" */
    public static function cantidad(float|int|string|null $valor, int $decimales = 4): string
    {
        $texto = number_format((float) $valor, $decimales, '.', ',');

        return str_contains($texto, '.') ? rtrim(rtrim($texto, '0'), '.') : $texto;
    }

    /** "15 Kilogramo" */
    public static function conUnidad(float|int|string|null $valor, ?string $unidad): string
    {
        return trim(self::cantidad($valor) . ' ' . ($unidad ?? ''));
    }
}
