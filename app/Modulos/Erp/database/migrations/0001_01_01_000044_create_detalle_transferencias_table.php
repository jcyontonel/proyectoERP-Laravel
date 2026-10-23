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
        Schema::create('detalle_transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transferencia_id')->constrained('transferencias_almacen')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->decimal('cantidad_solicitada', 14, 4);
            $table->decimal('cantidad_enviada', 14, 4)->default(0);
            $table->decimal('cantidad_recibida', 14, 4)->default(0);
            $table->decimal('costo_unitario', 14, 6)->default(0);
            $table->timestamps();

            $table->index(['transferencia_id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_transferencias');
    }
};
