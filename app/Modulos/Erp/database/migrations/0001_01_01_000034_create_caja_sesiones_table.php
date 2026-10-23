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
        Schema::create('caja_sesiones', function (Blueprint $table) {
            // Turno de caja: apertura -> ventas, cobros y movimientos -> cierre con arqueo
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();             // quién abre
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->dateTime('fecha_apertura');
            $table->decimal('monto_apertura', 12, 2)->default(0);
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('monto_esperado', 12, 2)->nullable(); // apertura + ingresos - egresos en efectivo
            $table->decimal('monto_contado', 12, 2)->nullable();  // arqueo físico
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->json('arqueo')->nullable();                   // conteo por denominación {"200": 1, "0.5": 4}
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['caja_id', 'estado']);
            $table->index(['empresa_id', 'fecha_apertura']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caja_sesiones');
    }
};
