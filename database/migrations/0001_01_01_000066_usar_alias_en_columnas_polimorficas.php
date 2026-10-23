<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las columnas polimórficas guardaban el nombre completo de la clase (App\Models\Venta). Pasan a alias
 * estables (venta) definidos en AppServiceProvider::MORPH_MAP, para poder mover los modelos a sus módulos
 * sin desconectar roles, tokens ni comprobantes.
 */
return new class extends Migration
{
    private const ALIAS = [
        'App\\Models\\User'            => 'user',
        'App\\Models\\Venta'           => 'venta',
        'App\\Models\\Nota'            => 'nota',
        'App\\Models\\PagoSuscripcion' => 'pago_suscripcion',
    ];

    private const COLUMNAS = [
        'comprobantes_sunat'     => 'comprobante_type',
        'model_has_roles'        => 'model_type',
        'model_has_permissions'  => 'model_type',
        'personal_access_tokens' => 'tokenable_type',
    ];

    public function up(): void
    {
        $this->convertir(self::ALIAS);
    }

    public function down(): void
    {
        $this->convertir(array_flip(self::ALIAS));
    }

    private function convertir(array $de): void
    {
        foreach (self::COLUMNAS as $tabla => $columna) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($de as $antes => $despues) {
                DB::table($tabla)->where($columna, $antes)->update([$columna => $despues]);
            }
        }
    }
};
