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
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->nullable()->constrained('tipo_documentos')->nullOnDelete();

            // Identificación
            $table->string('numero_documento', 20)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('nombre_comercial')->nullable();

            // Contacto principal
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_cargo')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('email')->nullable();

            // Dirección
            $table->string('pais', 100)->default('Perú');
            $table->string('departamento', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('distrito', 100)->nullable();
            $table->string('direccion')->nullable();

            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'razon_social'], 'uniq_empresa_razon_social');
            $table->unique(['empresa_id', 'tipo_documento_id', 'numero_documento'], 'uniq_empresa_proveedor');
            $table->index('numero_documento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
