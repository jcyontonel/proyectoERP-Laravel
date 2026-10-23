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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('tipo_unidad_id')->nullable()->constrained('tipo_unidades')->restrictOnDelete();
            $table->foreignId('impuesto_id')->nullable()->constrained('impuestos')->restrictOnDelete(); // NULL = IGV de la empresa
            $table->string('codigo', 50)->nullable();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('costo_unitario', 14, 6)->default(0);
            $table->boolean('es_servicio')->default(false);
            $table->boolean('maneja_lotes')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'nombre'], 'uniq_productos_empresa_nombre');
            $table->index(['empresa_id', 'codigo'], 'idx_productos_empresa_codigo');
            $table->index(['empresa_id', 'activo'], 'idx_productos_empresa_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
