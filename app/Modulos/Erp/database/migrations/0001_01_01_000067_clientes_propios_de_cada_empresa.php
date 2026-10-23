<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los clientes pasan a ser de cada empresa (clientes.empresa_id) en lugar de globales con cliente_empresa.
 *
 *  - Un cliente vinculado a una sola empresa: se le asigna esa empresa.
 *  - Un cliente compartido por varias: la primera empresa se queda con el registro y las demás reciben una copia;
 *    sus ventas y cotizaciones pasan a apuntar a la copia de su empresa.
 *  - Un cliente sin vínculo: toma la empresa de sus ventas o cotizaciones. Si no tiene ninguna, la migración
 *    se detiene ANTES de cambiar nada (hay que decidir a mano a qué empresa pertenece).
 *
 * El documento deja de ser único en toda la base: ahora es único dentro de cada empresa.
 * La tabla cliente_empresa se elimina en la migración siguiente, tras verificar que todo quedó asignado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinEmpresa = $this->huerfanos()->filter(fn ($c) => $c->empresa_id === null);
        if ($sinEmpresa->isNotEmpty()) {
            throw new RuntimeException('Clientes sin empresa (ni vínculo, ni ventas, ni cotizaciones): ids ' . $sinEmpresa->pluck('id')->join(', ') . '. Asígnalos a mano en cliente_empresa y vuelve a migrar.');
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->restrictOnDelete();
            $table->index('tipo_documento_id');   // MySQL usaba uniq_cliente como índice de esa FK
            $table->dropUnique('uniq_cliente');   // las copias por empresa repiten el documento
        });

        DB::transaction(function () {
            $vinculos = DB::table('cliente_empresa')->orderBy('cliente_id')->orderBy('id')->get()->groupBy('cliente_id');

            foreach ($vinculos as $clienteId => $filas) {
                DB::table('clientes')->where('id', $clienteId)->update(['empresa_id' => $filas->first()->empresa_id]);

                foreach ($filas->skip(1) as $fila) {
                    $copia = (array) DB::table('clientes')->where('id', $clienteId)->first();
                    unset($copia['id']);
                    $copiaId = DB::table('clientes')->insertGetId(['empresa_id' => $fila->empresa_id] + $copia);

                    foreach (['ventas', 'cotizaciones'] as $tabla) {
                        DB::table($tabla)->where('cliente_id', $clienteId)->where('empresa_id', $fila->empresa_id)->update(['cliente_id' => $copiaId]);
                    }
                }
            }

            foreach ($this->huerfanos() as $c) {
                DB::table('clientes')->where('id', $c->id)->update(['empresa_id' => $c->empresa_id]);
            }
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
            $table->unique(['empresa_id', 'tipo_documento_id', 'numero_documento'], 'uniq_cliente_empresa');
        });
    }

    public function down(): void
    {
        // Vuelve al cliente global. Si dos empresas tienen el mismo documento hay que fusionarlos a mano antes.
        $repetidos = DB::table('clientes')->select('tipo_documento_id', 'numero_documento')
            ->groupBy('tipo_documento_id', 'numero_documento')->havingRaw('COUNT(*) > 1')->pluck('numero_documento');
        if ($repetidos->isNotEmpty()) {
            throw new RuntimeException('Documentos registrados en varias empresas: ' . $repetidos->join(', ') . '. Fusiónalos antes de revertir.');
        }

        // Primero la FK: MySQL usa uniq_cliente_empresa como su índice
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropUnique('uniq_cliente_empresa');
            $table->unique(['tipo_documento_id', 'numero_documento'], 'uniq_cliente');
            $table->dropIndex(['tipo_documento_id']);
            $table->dropColumn('empresa_id');
        });
    }

    /** Clientes sin fila en cliente_empresa, con la empresa de su primera venta o cotización (o null) */
    private function huerfanos()
    {
        return DB::table('clientes as c')
            ->whereNotExists(fn ($q) => $q->from('cliente_empresa as ce')->whereColumn('ce.cliente_id', 'c.id'))
            ->select('c.id')
            ->selectSub(fn ($q) => $q->from('ventas')->whereColumn('cliente_id', 'c.id')->orderBy('id')->limit(1)->select('empresa_id'), 'de_venta')
            ->selectSub(fn ($q) => $q->from('cotizaciones')->whereColumn('cliente_id', 'c.id')->orderBy('id')->limit(1)->select('empresa_id'), 'de_cotizacion')
            ->get()
            ->map(fn ($c) => (object) ['id' => $c->id, 'empresa_id' => $c->de_venta ?? $c->de_cotizacion]);
    }
};
