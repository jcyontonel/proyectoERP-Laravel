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
        Schema::create('movimientos_caja', function (Blueprint $table) {
            // Todo ingreso/egreso de una sesión de caja: cobros (pago_id), gastos, retiros, depósitos
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('caja_sesion_id')->constrained('caja_sesiones')->restrictOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->string('concepto', 150);
            $table->string('metodo_pago', 40)->default('efectivo');
            $table->decimal('monto', 12, 2);
            $table->dateTime('fecha_movimiento');
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['caja_sesion_id', 'estado']);
            $table->index(['empresa_id', 'fecha_movimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
