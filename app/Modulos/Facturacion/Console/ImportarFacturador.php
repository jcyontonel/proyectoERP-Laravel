<?php

namespace App\Modulos\Facturacion\Console;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Trae del facturador separado (ricopet/facturacion) los documentos de un sistema: datos firmados, estado,
 * respuestas de SUNAT, XML y CDR, y las bajas (RA/RC). Se emparejan por uuid (= facturador_id del ERP) o por
 * empresa + tipo + serie + número. Se puede repetir: no duplica. Los documentos de otros sistemas (pruebas) no se tocan.
 */
class ImportarFacturador extends Command
{
    protected $signature = 'facturacion:importar-facturador
        {--base=ricopet_facturacion : Base de datos del facturador (mismo servidor)}
        {--sistema=ERP RicoPet : Sistema del facturador cuyos documentos se importan}
        {--archivos= : Carpeta storage/app/private del facturador (XML y CDR)}
        {--simular : Muestra lo que haría sin guardar nada}';

    protected $description = 'Importa los comprobantes del facturador electrónico separado';

    public function handle(): int
    {
        $origen = $this->conexion();
        $archivos = rtrim($this->option('archivos') ?: base_path('../ricopet/facturacion/storage/app/private'), '/\\');
        if (! is_dir($archivos)) {
            $this->error("No existe la carpeta de archivos del facturador: {$archivos}");

            return self::FAILURE;
        }

        $filas = DB::connection($origen)->table('documentos as d')
            ->join('emisores as e', 'e.id', '=', 'd.emisor_id')
            ->join('sistemas as s', 's.id', '=', 'e.sistema_id')
            ->where('s.nombre', $this->option('sistema'))
            ->orderBy('d.id')
            ->select('d.*', 'e.ruc')
            ->get();
        if ($filas->isEmpty()) {
            $this->warn('El sistema «' . $this->option('sistema') . '» no tiene documentos en el facturador.');

            return self::SUCCESS;
        }

        $res = ['creados' => 0, 'actualizados' => 0, 'sin_empresa' => 0, 'archivos' => 0, 'faltan_archivos' => 0];
        $copiar = [];

        DB::beginTransaction();
        try {
            $ids = [];   // id en el facturador => id en documentos
            foreach ($filas as $f) {
                $empresa = Empresa::where('ruc', $f->ruc)->first();
                if (! $empresa) {
                    $res['sin_empresa']++;
                    $this->warn("  {$f->ruc} {$f->tipo} {$f->serie}-{$f->correlativo}: no hay empresa con ese RUC (se omite).");

                    continue;
                }

                $doc = Documento::where('uuid', $f->uuid)->first()
                    ?? Documento::where('empresa_id', $empresa->id)->where('tipo', $f->tipo)->where('serie', $f->serie)->where('numero', (int) $f->correlativo)->first();

                $campos = [
                    'uuid' => $f->uuid, 'fecha_emision' => $f->fecha_emision, 'moneda' => $f->moneda, 'cliente_tipo_doc' => $f->cliente_tipo_doc,
                    'cliente_num_doc' => $f->cliente_num_doc, 'cliente_nombre' => $f->cliente_nombre, 'total' => $f->total,
                    'datos' => json_decode((string) $f->datos, true), 'estado' => $f->estado, 'nombre_archivo' => $f->nombre_archivo,
                    'hash' => $f->hash, 'xml_path' => $f->xml_path, 'cdr_path' => $f->cdr_path, 'ticket' => $f->ticket,
                    'sunat_codigo' => $f->sunat_codigo, 'sunat_descripcion' => $f->sunat_descripcion,
                    'sunat_observaciones' => $f->sunat_observaciones ? json_decode($f->sunat_observaciones, true) : null,
                    'intentos' => $f->intentos, 'proximo_intento_at' => $f->proximo_intento_at, 'ultimo_error' => $f->ultimo_error,
                    'enviado_at' => $f->enviado_at, 'respondido_at' => $f->respondido_at, 'referencia' => $f->referencia,
                ];

                if ($doc) {
                    $doc->update($campos);
                    $res['actualizados']++;
                } else {
                    $doc = Documento::create($campos + [
                        'empresa_id' => $empresa->id, 'tipo' => $f->tipo, 'serie' => $f->serie, 'numero' => (int) $f->correlativo,
                    ]);
                    $doc->forceFill(['created_at' => $f->created_at])->saveQuietly();
                    $res['creados']++;
                }
                $ids[$f->id] = $doc->id;
                $this->line("  {$f->ruc} {$f->tipo} {$f->serie}-{$f->correlativo}: {$f->estado}");

                foreach ([$f->xml_path, $f->cdr_path] as $ruta) {
                    if (! $ruta) {
                        continue;
                    }
                    is_file("{$archivos}/{$ruta}") ? $copiar[$ruta] = "{$archivos}/{$ruta}" : $res['faltan_archivos']++;
                }
            }

            // Relaciones entre documentos: nota → comprobante, baja → comprobante y comprobante → su baja
            foreach ($filas as $f) {
                if (! isset($ids[$f->id])) {
                    continue;
                }
                Documento::whereKey($ids[$f->id])->update([
                    'afectado_id' => $f->afectado_id ? ($ids[$f->afectado_id] ?? null) : null,
                    'baja_id'     => $f->baja_id ? ($ids[$f->baja_id] ?? null) : null,
                ]);
            }

            if ($this->option('simular')) {
                DB::rollBack();
                $this->warn('Simulación: no se guardó nada.');
            } else {
                foreach ($copiar as $ruta => $absoluta) {
                    if (! Storage::disk('local')->exists($ruta)) {
                        Storage::disk('local')->put($ruta, file_get_contents($absoluta));
                    }
                    $res['archivos']++;
                }
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->info("Creados: {$res['creados']} · actualizados: {$res['actualizados']} · sin empresa: {$res['sin_empresa']}"
            . " · archivos copiados: {$res['archivos']}" . ($res['faltan_archivos'] ? " · archivos que no se encontraron: {$res['faltan_archivos']}" : '') . '.');

        return self::SUCCESS;
    }

    /** Conexión a la base del facturador con las mismas credenciales que la del ERP */
    private function conexion(): string
    {
        $base = (string) $this->option('base');
        if (! preg_match('/^[A-Za-z0-9_]+$/', $base)) {
            throw new RuntimeException('Nombre de base de datos no válido.');
        }
        $actual = config('database.connections.' . config('database.default'));
        config(['database.connections.facturador_origen' => ['database' => $base] + $actual]);

        return 'facturador_origen';
    }
}
