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
        Schema::create('preferencias_usuario', function (Blueprint $table) {
            // Preferencias personales: valen en todas las empresas del usuario
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('empresa_inicio_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('ticket_ancho', 2)->default('80');            // 80 | 58 (mm)
            $table->boolean('imprimir_al_vender')->default(false);       // abre la impresión del ticket al registrar
            $table->json('alertas_ocultas')->nullable();                 // claves de NotificacionService::ALERTAS
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferencias_usuario');
    }
};
