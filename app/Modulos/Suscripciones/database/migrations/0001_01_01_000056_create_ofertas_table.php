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
        Schema::create('ofertas', function (Blueprint $table) {
            // Descuentos sobre los precios de suscripción. plan_id / periodicidad NULL = aplica a todos.
            $table->id();
            $table->string('nombre', 80);
            $table->string('codigo', 30)->nullable()->unique();             // cupón opcional
            $table->foreignId('plan_id')->nullable()->constrained('planes')->restrictOnDelete();
            $table->string('periodicidad', 12)->nullable();
            $table->string('tipo', 12);                                     // porcentaje | monto
            $table->decimal('valor', 10, 2);
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->unsignedInteger('max_usos')->nullable();
            $table->unsignedInteger('usos')->default(0);
            $table->boolean('solo_primer_pago')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ofertas');
    }
};
