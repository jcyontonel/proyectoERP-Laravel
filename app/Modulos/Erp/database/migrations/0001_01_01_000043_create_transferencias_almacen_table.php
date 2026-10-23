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
        Schema::create('transferencias_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('almacen_origen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('almacen_destino_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('serie', 20);
            $table->unsignedBigInteger('numero');
            $table->enum('estado', ['borrador', 'pendiente', 'en_transito', 'recibida', 'anulada'])->default('borrador');
            $table->dateTime('fecha_movimiento');
            $table->dateTime('enviada_at')->nullable();
            $table->dateTime('recibida_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'serie', 'numero'], 'transferencias_empresa_serie_numero_unique');
            $table->index(['empresa_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferencias_almacen');
    }
};
