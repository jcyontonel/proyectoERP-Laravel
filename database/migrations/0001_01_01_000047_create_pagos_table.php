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
        Schema::create('pagos', function (Blueprint $table) {
            // Cobros de ventas y pagos de compras (cuentas por cobrar / por pagar)
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->restrictOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->restrictOnDelete();
            $table->foreignId('caja_sesion_id')->nullable()->constrained('caja_sesiones')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('tipo', ['cobro', 'pago']);
            $table->string('metodo_pago', 40);
            $table->decimal('monto', 14, 2);
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 10, 4)->default(1);
            $table->string('referencia', 100)->nullable();
            $table->dateTime('fecha_pago');
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete()->cascadeOnUpdate();

            $table->index(['empresa_id', 'fecha_pago']);
            $table->index(['venta_id', 'estado']);
            $table->index(['compra_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
