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
        // 🧾 Tabla principal de compras
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Datos generales
            $table->string('serie', 10)->nullable();
            $table->string('numero', 20)->nullable();
            $table->dateTime('fecha_emision');
            $table->string('tipo_comprobante', 50)->nullable(); // Ejemplo: FACTURA, BOLETA, NOTA DE COMPRA
            $table->string('moneda', 10)->default('PEN'); // PEN, USD, etc.

            // Totales
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Estado de la compra
            $table->string('estado', 30)->default('registrada'); // registrada, anulada, recibida, etc.

            $table->text('observacion')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'serie', 'numero']);
        });

        // 📦 Tabla detalle de compras
        Schema::create('detalle_compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('productos')->onDelete('restrict');
            
            $table->string('descripcion')->nullable();
            $table->decimal('cantidad', 10, 2)->default(0);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_compras');
        Schema::dropIfExists('compras');
    }
};
