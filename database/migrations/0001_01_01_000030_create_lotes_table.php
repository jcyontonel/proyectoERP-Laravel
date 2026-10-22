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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('codigo_lote', 100);
            $table->date('fecha_fabricacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'producto_id', 'codigo_lote'], 'lotes_empresa_producto_codigo_unique');
            $table->index(['empresa_id', 'fecha_vencimiento'], 'lotes_empresa_vencimiento_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
