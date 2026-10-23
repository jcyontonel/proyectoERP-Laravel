<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación electrónica dentro de la plataforma (antes: proyecto aparte ricopet/facturacion).
 *  - sistemas_externos: sistemas de fuera que emiten por la API v1 con token, cada uno de una empresa.
 *  - documentos: comprobantes (01, 03, 07, 08) y bajas (RA, RC) de cada empresa con su estado en SUNAT;
 *    reemplaza a comprobantes_sunat (ver la migración siguiente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sistemas_externos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->string('token_hash', 64)->unique();      // sha256 del token; el token solo se muestra al crearlo
            $table->string('token_prefijo', 12);             // para reconocerlo en listados
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secreto')->nullable();     // cifrado; firma HMAC de los avisos
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamps();
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->nullableMorphs('comprobante');            // venta, nota, pago_suscripcion (alias del morph map)
            $table->foreignId('sistema_id')->nullable()->constrained('sistemas_externos')->nullOnDelete();
            $table->string('tipo', 2);
            $table->string('serie', 13);                      // F001, B001… o RA-20261023 / RC-20261023
            $table->unsignedBigInteger('numero');
            $table->dateTime('fecha_emision')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->string('cliente_tipo_doc', 2)->nullable();
            $table->string('cliente_num_doc', 20)->nullable();
            $table->string('cliente_nombre')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->json('datos')->nullable();                // lo que se firmó: cliente, ítems calculados y totales
            $table->foreignId('afectado_id')->nullable()->constrained('documentos')->nullOnDelete();   // NC/ND o baja → original
            $table->foreignId('baja_id')->nullable()->constrained('documentos')->nullOnDelete();       // comprobante → su RA/RC

            // Envío a SUNAT
            $table->string('estado', 12)->default('pendiente');
            $table->string('nombre_archivo', 60)->nullable();    // RUC-TIPO-SERIE-NUMERO
            $table->string('hash', 100)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('ticket', 60)->nullable();
            $table->string('sunat_codigo', 10)->nullable();
            $table->text('sunat_descripcion')->nullable();
            $table->json('sunat_observaciones')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->dateTime('proximo_intento_at')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->dateTime('enviado_at')->nullable();
            $table->dateTime('respondido_at')->nullable();

            // Sistema externo (API v1)
            $table->string('referencia', 100)->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_estado', 12)->nullable();    // pendiente, entregado, fallido
            $table->unsignedInteger('webhook_intentos')->default(0);
            $table->dateTime('webhook_proximo_at')->nullable();
            $table->text('webhook_ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo', 'serie', 'numero'], 'uniq_documento');
            $table->index(['estado', 'proximo_intento_at']);
            $table->index(['webhook_estado', 'webhook_proximo_at']);
            $table->index(['empresa_id', 'referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('sistemas_externos');
    }
};
