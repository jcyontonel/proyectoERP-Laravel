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
        Schema::create('correlativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('modulo', 20)->default('venta');    // venta, nota, ajuste, transferencia, devolucion
            $table->string('tipo', 2);                         // 00 ticket, 01 factura, 03 boleta, 07 NC, 08 ND, AJ, TR, DV
            $table->string('destipo', 30);                     // Ticket, Factura, Boleta...
            $table->string('serie', 10);                       // F001, B001, FC01...
            $table->unsignedBigInteger('numero')->default(0); // último número emitido
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'tipo', 'serie']);
            $table->index(['empresa_id', 'modulo', 'activo'], 'idx_correlativos_empresa_modulo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correlativos');
    }
};
