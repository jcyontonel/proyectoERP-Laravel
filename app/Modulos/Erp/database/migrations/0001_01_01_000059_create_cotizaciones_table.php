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
        Schema::create('cotizaciones', function (Blueprint $table) {
            // Propuesta de venta al cliente. No mueve stock ni consume comprobantes; se convierte en venta.
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero');
            $table->dateTime('fecha_emision');
            $table->date('vence_el');                                       // validez de la oferta
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('estado', 12)->default('emitida');              // emitida | aceptada | rechazada | convertida | anulada
            $table->text('condiciones')->nullable();                        // forma de pago, entrega, garantía…
            $table->text('observacion')->nullable();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['empresa_id', 'serie', 'numero'], 'uniq_cotizacion_empresa');
            $table->index(['empresa_id', 'fecha_emision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
