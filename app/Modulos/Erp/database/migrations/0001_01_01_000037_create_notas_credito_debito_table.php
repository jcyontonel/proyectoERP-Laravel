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
        Schema::create('notas_credito_debito', function (Blueprint $table) {
            // Notas de crédito (07) y débito (08) sobre una venta emitida
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 2);                  // 07 crédito, 08 débito
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero');
            $table->dateTime('fecha_emision');
            $table->string('motivo_codigo', 2);         // Catálogo SUNAT 09 (NC) / 10 (ND)
            $table->string('motivo_descripcion');
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['borrador', 'emitida', 'anulada'])->default('borrador');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['empresa_id', 'tipo', 'serie', 'numero'], 'uniq_nota_empresa');
            $table->index(['empresa_id', 'fecha_emision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas_credito_debito');
    }
};
