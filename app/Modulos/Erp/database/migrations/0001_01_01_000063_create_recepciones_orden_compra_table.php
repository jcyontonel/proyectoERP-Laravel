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
        Schema::create('recepciones_orden_compra', function (Blueprint $table) {
            // Qué compra recibió (parte de) qué orden. Una compra atiende a lo más una orden.
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->restrictOnDelete();
            $table->foreignId('compra_id')->unique()->constrained('compras')->restrictOnDelete();
            // Lo que esta compra sumó a cada línea: {detalle_orden_id: cantidad}. Se resta tal cual al anular la compra
            $table->json('cantidades')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recepciones_orden_compra');
    }
};
