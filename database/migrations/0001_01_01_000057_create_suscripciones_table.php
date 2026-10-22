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
        Schema::create('suscripciones', function (Blueprint $table) {
            // Plan contratado por una empresa. Solo una está vigente (no cancelada) por empresa.
            // vence_el = pagado hasta; el estado efectivo se calcula por fechas (SuscripcionService).
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->string('periodicidad', 12);
            $table->decimal('precio', 10, 2);                               // precio del periodo al contratar (sin descuento)
            $table->foreignId('oferta_id')->nullable()->constrained('ofertas')->nullOnDelete();
            $table->string('estado', 15)->default('activa');                // prueba | activa | suspendida | cancelada
            $table->date('inicio');
            $table->date('vence_el');
            $table->boolean('renovacion_automatica')->default(true);       // genera el cobro del siguiente periodo
            $table->string('motivo_suspension', 255)->nullable();
            $table->timestamp('cancelada_el')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index('vence_el');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
