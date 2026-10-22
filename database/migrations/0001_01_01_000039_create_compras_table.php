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
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete(); // a dónde ingresa el stock
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Comprobante del proveedor
            $table->string('tipo_comprobante', 50)->nullable(); // factura, boleta, guia, recibo, otro
            $table->string('serie', 10)->nullable();
            $table->string('numero', 20)->nullable();
            $table->dateTime('fecha_emision');
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 10, 4)->default(1);

            // Pago: crédito queda en cuentas por pagar (saldo = total - pagos)
            $table->string('condicion_pago', 20)->default('contado'); // contado, credito
            $table->date('fecha_vencimiento')->nullable();

            // Totales
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('estado', 30)->default('registrada'); // registrada, anulada
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['empresa_id', 'proveedor_id', 'tipo_comprobante', 'serie', 'numero'], 'uniq_compra_empresa');
            $table->index(['empresa_id', 'fecha_emision'], 'idx_compra_fecha_emision');
            $table->index(['empresa_id', 'estado'], 'idx_compra_estado');
            $table->index(['empresa_id', 'condicion_pago', 'estado'], 'idx_compras_por_pagar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
