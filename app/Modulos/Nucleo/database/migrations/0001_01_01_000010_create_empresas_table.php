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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 11)->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ubigeo', 6)->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('distrito', 100)->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();

            // Configuración comercial
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('igv', 5, 2)->default(18);
            $table->boolean('precios_incluyen_igv')->default(true);
            $table->boolean('controlar_stock')->default(true);

            // Facturación electrónica SUNAT (claves cifradas desde la app)
            $table->enum('sunat_modo', ['beta', 'produccion'])->default('beta');
            $table->string('sunat_usuario_sol', 50)->nullable();
            $table->text('sunat_clave_sol')->nullable();
            $table->string('certificado_path')->nullable();
            $table->text('certificado_clave')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda')->references('codigo')->on('monedas')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
