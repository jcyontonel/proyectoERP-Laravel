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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');

            $table->foreignId('cliente_id')
              ->nullable()
              ->constrained('clientes')
              ->nullOnDelete(); 

            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('serie')->nullable();
            $table->string('numero')->nullable();
            $table->dateTime('fecha_emision');
            $table->string('tipo')->default('factura');
            $table->string('estado')->default('registrada');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_impuestos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
