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
        Schema::create('planes', function (Blueprint $table) {
            // Planes de suscripción (los gestiona el proyecto admin). Límite NULL = ilimitado.
            $table->id();
            $table->string('codigo', 30)->unique();                         // gratis, basico, premium
            $table->string('nombre', 60);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedInteger('max_comprobantes_mes')->nullable();    // facturas, boletas, tickets y notas por mes calendario
            $table->unsignedInteger('max_productos')->nullable();
            $table->unsignedInteger('max_usuarios')->nullable();
            $table->unsignedInteger('max_almacenes')->nullable();
            $table->unsignedSmallInteger('dias_prueba')->default(0);
            $table->boolean('activo')->default(true);                       // inactivo = no se puede asignar a empresas nuevas
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
