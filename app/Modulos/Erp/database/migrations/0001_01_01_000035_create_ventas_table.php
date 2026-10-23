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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete(); // de dónde sale el stock
            $table->foreignId('caja_sesion_id')->nullable()->constrained('caja_sesiones')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Comprobante
            $table->string('tipo', 2)->default('03');
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero');
            $table->dateTime('fecha_emision');
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 10, 4)->default(1);

            // Pago: contado se cobra al emitir; crédito queda en cuentas por cobrar (saldo = total - pagos)
            $table->string('condicion_pago', 20)->default('contado'); // contado, credito
            $table->date('fecha_vencimiento')->nullable();
            $table->string('metodo_pago', 30)->default('efectivo');

            $table->string('estado', 30)->default('borrador'); // borrador, completada, anulada

            // Totales
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['empresa_id', 'tipo', 'serie', 'numero'], 'uniq_comprobante_empresa');
            $table->index(['empresa_id', 'fecha_emision'], 'idx_ventas_empresa_fecha');
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'condicion_pago', 'estado'], 'idx_ventas_por_cobrar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
