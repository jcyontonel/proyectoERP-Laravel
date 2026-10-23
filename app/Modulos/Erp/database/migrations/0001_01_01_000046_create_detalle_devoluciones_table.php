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
        Schema::create('detalle_devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->decimal('cantidad', 14, 4);
            $table->decimal('precio_unitario', 14, 6)->default(0);
            $table->decimal('costo_unitario', 14, 6)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('disposicion', 30)->default('reingresar'); // reingresar, merma
            $table->timestamps();

            $table->index(['devolucion_id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_devoluciones');
    }
};
