<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso global de la plataforma (administrar empresas, planes, suscripciones y pagos de todas las empresas).
 * No depende de ninguna empresa (los roles de Spatie sí) y solo se asigna por consola:
 *   php artisan plataforma:asignar-admin {correo}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('administra_plataforma')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('administra_plataforma');
        });
    }
};
