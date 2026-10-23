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
        Schema::create('ajustes_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('motivo_ajuste_id')->constrained('motivos_ajuste')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('serie', 20);
            $table->unsignedBigInteger('numero');
            $table->enum('tipo', ['ingreso', 'egreso', 'conteo']);
            $table->enum('estado', ['borrador', 'pendiente', 'confirmado', 'anulado'])->default('borrador');
            $table->dateTime('fecha_movimiento');
            $table->text('observacion')->nullable();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'serie', 'numero'], 'ajustes_empresa_serie_numero_unique');
            $table->index(['empresa_id', 'fecha_movimiento']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes_inventario');
    }
};
