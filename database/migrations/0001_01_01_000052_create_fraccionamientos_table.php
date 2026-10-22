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
        Schema::create('fraccionamientos', function (Blueprint $table) {
            // Partir un producto en otro: 1 Saco de "Ricocan 15 kg" → 15 Kilogramo de "Ricocan por kilo".
            // Sale del origen y entra al destino en el mismo almacén, con el costo repartido (kardex en ambos).
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('unidad_conversion_id')->nullable()->constrained('unidades_conversion')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete(); // si se partió al vender
            $table->string('serie', 20);
            $table->unsignedBigInteger('numero');
            $table->enum('origen', ['manual', 'venta'])->default('manual');

            $table->foreignId('producto_origen_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_origen_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->decimal('cantidad_origen', 14, 4);
            $table->decimal('costo_origen', 14, 6)->default(0);   // costo unitario del origen

            $table->foreignId('producto_destino_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_destino_id')->nullable()->constrained('lotes')->restrictOnDelete();
            $table->decimal('factor', 14, 6);
            $table->decimal('cantidad_destino', 14, 4);           // cantidad_origen x factor
            $table->decimal('costo_destino', 14, 6)->default(0);  // costo_origen / factor

            $table->enum('estado', ['confirmado', 'anulado'])->default('confirmado');
            $table->dateTime('fecha_movimiento');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'serie', 'numero'], 'fraccionamientos_empresa_serie_numero_unique');
            $table->index(['empresa_id', 'fecha_movimiento']);
            $table->index(['empresa_id', 'producto_destino_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraccionamientos');
    }
};
