<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina cliente_empresa solo si cada vínculo quedó representado en clientes.empresa_id
 * (el mismo cliente, o su copia con el mismo documento en esa empresa).
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinReflejo = DB::table('cliente_empresa as ce')
            ->join('clientes as c', 'c.id', '=', 'ce.cliente_id')
            ->whereNotExists(fn ($q) => $q->from('clientes as x')
                ->whereColumn('x.empresa_id', 'ce.empresa_id')
                ->whereColumn('x.tipo_documento_id', 'c.tipo_documento_id')
                ->whereColumn('x.numero_documento', 'c.numero_documento'))
            ->count();

        if ($sinReflejo > 0) {
            throw new RuntimeException("{$sinReflejo} vínculos de cliente_empresa no tienen su cliente en clientes.empresa_id: no se elimina la tabla.");
        }

        Schema::dropIfExists('cliente_empresa');
    }

    public function down(): void
    {
        Schema::create('cliente_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cliente_id', 'empresa_id'], 'uniq_clienteid_empresaid');
        });

        DB::table('cliente_empresa')->insertUsing(['cliente_id', 'empresa_id', 'created_at', 'updated_at'],
            DB::table('clientes')->select('id', 'empresa_id', 'created_at', 'updated_at'));
    }
};
