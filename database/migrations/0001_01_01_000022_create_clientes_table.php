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
        Schema::create('clientes', function (Blueprint $table) {
            // Clientes globales: se comparten entre empresas vía cliente_empresa
            $table->id();
            $table->foreignId('tipo_documento_id')->constrained('tipo_documentos')->restrictOnDelete();
            $table->string('numero_documento', 15);
            $table->string('nombre');
            $table->string('apellido')->nullable();
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tipo_documento_id', 'numero_documento'], 'uniq_cliente');
            $table->index('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
