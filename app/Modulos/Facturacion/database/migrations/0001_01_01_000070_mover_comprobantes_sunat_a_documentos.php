<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * comprobantes_sunat → documentos. El id del facturador (facturador_id) pasa a ser el uuid del documento,
 * así facturacion:importar-facturador puede completar después el XML, el CDR y los datos firmados.
 * Una baja pedida en el facturador (baja_estado) se reconstruye al importar; aquí no hay datos para el RA/RC.
 * comprobantes_sunat se elimina solo si se copiaron todas sus filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $filas = DB::table('comprobantes_sunat')->orderBy('id')->get();

        DB::transaction(function () use ($filas) {
            foreach ($filas as $c) {
                DB::table('documentos')->insert([
                    'uuid'                => $c->facturador_id ?: (string) Str::uuid(),
                    'empresa_id'          => $c->empresa_id,
                    'comprobante_type'    => $c->comprobante_type,
                    'comprobante_id'      => $c->comprobante_id,
                    'tipo'                => $c->tipo,
                    'serie'               => $c->serie,
                    'numero'              => $c->numero,
                    'fecha_emision'       => $c->enviado_at ?? $c->created_at,
                    'estado'              => $c->estado,
                    'nombre_archivo'      => $c->nombre_archivo,
                    'hash'                => $c->hash,
                    'ticket'              => $c->ticket,
                    'sunat_codigo'        => $c->codigo_respuesta,
                    'sunat_descripcion'   => $c->codigo_respuesta !== null ? $c->mensaje_respuesta : null,
                    'ultimo_error'        => $c->codigo_respuesta === null ? $c->mensaje_respuesta : null,
                    'sunat_observaciones' => $c->observaciones,
                    'intentos'            => $c->intentos,
                    'proximo_intento_at'  => $c->proximo_intento_at,
                    'enviado_at'          => $c->enviado_at,
                    'respondido_at'       => $c->respondido_at,
                    'created_at'          => $c->created_at,
                    'updated_at'          => $c->updated_at,
                ]);
            }
        });

        $copiados = DB::table('documentos')->whereIn('uuid', $filas->pluck('facturador_id')->filter())->count()
            + $filas->whereNull('facturador_id')->count();
        if ($copiados !== $filas->count()) {
            throw new RuntimeException("Se copiaron {$copiados} de {$filas->count()} comprobantes: no se elimina comprobantes_sunat.");
        }

        Schema::drop('comprobantes_sunat');
    }

    public function down(): void
    {
        Schema::create('comprobantes_sunat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->morphs('comprobante');
            $table->string('facturador_id', 36)->nullable()->index();
            $table->string('tipo', 2);
            $table->string('serie', 10);
            $table->unsignedBigInteger('numero');
            $table->string('nombre_archivo', 100)->nullable();
            $table->string('hash', 100)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('cdr_path')->nullable();
            $table->string('estado', 12)->default('pendiente');
            $table->string('codigo_respuesta', 10)->nullable();
            $table->text('mensaje_respuesta')->nullable();
            $table->json('observaciones')->nullable();
            $table->string('ticket', 100)->nullable();
            $table->string('baja_estado', 12)->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->dateTime('proximo_intento_at')->nullable();
            $table->dateTime('enviado_at')->nullable();
            $table->dateTime('respondido_at')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo', 'serie', 'numero'], 'uniq_comprobante_sunat');
            $table->index(['empresa_id', 'estado']);
        });

        $bajas = DB::table('documentos')->whereIn('tipo', ['RA', 'RC'])->pluck('estado', 'id');
        DB::table('documentos')->whereNotNull('comprobante_type')->orderBy('id')->get()
            ->each(fn ($d) => DB::table('comprobantes_sunat')->insert([
                'empresa_id' => $d->empresa_id, 'comprobante_type' => $d->comprobante_type, 'comprobante_id' => $d->comprobante_id,
                'facturador_id' => $d->uuid, 'tipo' => $d->tipo, 'serie' => $d->serie, 'numero' => $d->numero,
                'nombre_archivo' => $d->nombre_archivo, 'hash' => $d->hash, 'estado' => $d->estado, 'codigo_respuesta' => $d->sunat_codigo,
                'mensaje_respuesta' => $d->sunat_descripcion ?? $d->ultimo_error, 'observaciones' => $d->sunat_observaciones, 'ticket' => $d->ticket,
                'baja_estado' => $d->baja_id ? ($bajas[$d->baja_id] ?? null) : null, 'intentos' => min($d->intentos, 255),
                'proximo_intento_at' => $d->proximo_intento_at, 'enviado_at' => $d->enviado_at, 'respondido_at' => $d->respondido_at,
                'created_at' => $d->created_at, 'updated_at' => $d->updated_at,
            ]));
        DB::table('documentos')->delete();
    }
};
