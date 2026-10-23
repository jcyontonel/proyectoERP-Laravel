<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conexión con el facturador electrónico (ricopet/facturacion):
     *  - facturador_id: id del comprobante en el facturador (null = aún no llegó al facturador).
     *  - estado pasa de enum a texto para admitir "error" (credenciales, certificado, plazo vencido).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE comprobantes_sunat DROP CONSTRAINT IF EXISTS comprobantes_sunat_estado_check');
        }

        Schema::table('comprobantes_sunat', function (Blueprint $table) {
            $table->string('estado', 12)->default('pendiente')->change();
            $table->string('facturador_id', 36)->nullable()->after('comprobante_id')->index();
            $table->json('observaciones')->nullable()->after('mensaje_respuesta');
            $table->string('baja_estado', 12)->nullable()->after('ticket');   // anulación en SUNAT (RA/RC)
            $table->dateTime('proximo_intento_at')->nullable()->after('intentos');
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes_sunat', function (Blueprint $table) {
            $table->dropColumn(['facturador_id', 'observaciones', 'baja_estado', 'proximo_intento_at']);
        });
    }
};
