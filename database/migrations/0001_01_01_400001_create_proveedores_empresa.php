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

            // 🔗 Relación con empresa
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');

            // 🔗 Relación con tipo de documento
            $table->foreignId('tipo_documento_id')->nullable()->constrained('tipo_documentos')->nullOnDelete();

            // Identificación
            $table->string('numero_documento', 20)->nullable()->index();
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

            // Observaciones y estado
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Restricción de unicidad
            $table->unique(['empresa_id', 'numero_documento']);
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
