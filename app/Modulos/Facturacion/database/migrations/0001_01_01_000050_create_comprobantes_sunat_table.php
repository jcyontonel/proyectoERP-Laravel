<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comprobantes_sunat', function (Blueprint $table) {
            // Estado de envío a SUNAT de cada comprobante electrónico (venta o nota)
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->morphs('comprobante'); // App\Models\Venta, App\Models\NotaCreditoDebito
            $table->string('tipo', 2);
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero');
            $table->string('nombre_archivo', 100)->nullable(); // RUC-TIPO-SERIE-NUMERO
            $table->string('hash', 100)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->enum('estado', ['pendiente', 'enviado', 'aceptado', 'observado', 'rechazado', 'anulado'])->default('pendiente');
            $table->string('codigo_respuesta', 10)->nullable();
            $table->text('mensaje_respuesta')->nullable();
            $table->string('ticket', 100)->nullable(); // resúmenes diarios / comunicaciones de baja
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('enviado_at')->nullable();
            $table->dateTime('respondido_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo', 'serie', 'numero'], 'uniq_comprobante_sunat');
            $table->index(['empresa_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comprobantes_sunat');
    }
};
