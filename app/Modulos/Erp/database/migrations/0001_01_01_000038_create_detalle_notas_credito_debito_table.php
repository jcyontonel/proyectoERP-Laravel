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
        Schema::create('detalle_notas_credito_debito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained('notas_credito_debito')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->foreignId('tipo_unidad_id')->nullable()->constrained('tipo_unidades')->restrictOnDelete();
            $table->foreignId('impuesto_id')->nullable()->constrained('impuestos')->restrictOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 14, 4)->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['nota_id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_notas_credito_debito');
    }
};
