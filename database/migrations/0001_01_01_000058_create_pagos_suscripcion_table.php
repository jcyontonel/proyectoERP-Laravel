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
        Schema::create('pagos_suscripcion', function (Blueprint $table) {
            // Cobros de suscripción: pendiente (generado al acercarse el vencimiento) → pagado (extiende vence_el) | anulado
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('suscripcion_id')->constrained('suscripciones')->restrictOnDelete();
            $table->foreignId('oferta_id')->nullable()->constrained('ofertas')->nullOnDelete();
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->unsignedTinyInteger('meses');
            $table->decimal('monto_bruto', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('monto', 10, 2);                                 // lo que se cobra
            $table->string('estado', 12)->default('pendiente');             // pendiente | pagado | anulado
            $table->string('metodo', 20)->nullable();
            $table->string('referencia', 100)->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->string('registrado_por', 120)->nullable();              // administrador del proyecto admin
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index(['estado', 'fecha_pago']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_suscripcion');
    }
};
