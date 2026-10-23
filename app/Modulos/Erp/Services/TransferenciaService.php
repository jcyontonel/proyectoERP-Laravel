<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\TransferenciaAlmacen;
use App\Modulos\Nucleo\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transferencias entre almacenes:
 *  borrador → enviar (sale del origen, queda "en tránsito") → recibir (entra al destino lo recibido).
 *  Anular un envío en tránsito devuelve la mercadería al origen.
 */
class TransferenciaService
{
    public function __construct(
        private InventarioService $inventario,
        private LoteService $lotes,
        private CorrelativoService $correlativos,
    ) {
    }

    /** Crea o actualiza el borrador. accion: borrador | enviar */
    public function guardar(array $d, User $user, int $empresaId, ?TransferenciaAlmacen $existente = null): TransferenciaAlmacen
    {
        return DB::transaction(function () use ($d, $user, $empresaId, $existente) {
            $origen  = Almacen::query()->deEmpresa($empresaId)->activo()->findOrFail($d['almacen_origen_id']);
            $destino = Almacen::query()->deEmpresa($empresaId)->activo()->findOrFail($d['almacen_destino_id']);

            if ($origen->id === $destino->id) {
                throw ValidationException::withMessages(['almacen_destino_id' => 'El almacén de destino debe ser distinto al de origen.']);
            }

            if ($existente) {
                $t = TransferenciaAlmacen::query()->lockForUpdate()->findOrFail($existente->id);
                if ($t->estado !== TransferenciaAlmacen::BORRADOR) {
                    throw ValidationException::withMessages(['transferencia' => 'Solo se pueden editar transferencias en borrador.']);
                }
            } else {
                [$serie, $numero] = $this->correlativos->emitir($empresaId, 'transferencia');
                $t = new TransferenciaAlmacen(['empresa_id' => $empresaId, 'user_id' => $user->id, 'serie' => $serie, 'numero' => $numero]);
            }

            $t->fill([
                'almacen_origen_id'  => $origen->id,
                'almacen_destino_id' => $destino->id,
                'estado'             => TransferenciaAlmacen::BORRADOR,
                'fecha_movimiento'   => Carbon::parse($d['fecha_movimiento'])->setTimeFrom(now()),
                'observacion'        => $d['observacion'] ?? null,
            ])->save();

            $t->detalles()->delete();
            $productos = Producto::query()->deEmpresa($empresaId)->where('es_servicio', false)
                ->whereIn('id', collect($d['productos'])->pluck('id'))->get()->keyBy('id');

            foreach ($d['productos'] as $linea) {
                $p = $productos[$linea['id']] ?? null;
                if (! $p) {
                    throw ValidationException::withMessages(['productos' => 'Uno de los productos no está disponible (los servicios no llevan stock).']);
                }

                $codigo = trim((string) ($linea['lote'] ?? ''));
                if ($codigo === '' && $p->maneja_lotes) {
                    throw ValidationException::withMessages(['productos' => "«{$p->nombre}» controla lotes: indica el lote que vas a transferir."]);
                }
                $lote = $codigo !== '' ? $this->lotes->existente($empresaId, $p, $codigo) : null;

                $t->detalles()->create([
                    'producto_id'         => $p->id,
                    'lote_id'             => $lote?->id,
                    'cantidad_solicitada' => (float) $linea['cantidad'],
                    'costo_unitario'      => $lote ? $this->lotes->costo($lote, $origen->id, $p) : $p->costo_unitario,
                ]);
            }

            if (($d['accion'] ?? 'borrador') === 'enviar') {
                $this->enviar($t, $user);
            }

            return $t->refresh();
        });
    }

    /** Saca la mercadería del almacén de origen */
    public function enviar(TransferenciaAlmacen $transferencia, User $user): TransferenciaAlmacen
    {
        return DB::transaction(function () use ($transferencia, $user) {
            $t = TransferenciaAlmacen::query()->lockForUpdate()->with(['detalles.producto', 'detalles.lote', 'destino'])->findOrFail($transferencia->id);

            if ($t->estado !== TransferenciaAlmacen::BORRADOR) {
                throw ValidationException::withMessages(['transferencia' => 'Solo se envían transferencias en borrador.']);
            }

            $faltantes = [];
            foreach ($t->detalles as $det) {
                $disponible = $det->lote ? $this->lotes->stock($det->lote, $t->almacen_origen_id) : $this->stock($det->producto_id, $t->almacen_origen_id);
                if ($det->cantidad_solicitada > $disponible) {
                    $faltantes[] = "Stock insuficiente de «{$det->producto->nombre}»" . ($det->lote ? " (lote {$det->lote->codigo_lote})" : '') . ": disponible {$disponible}, a enviar {$det->cantidad_solicitada}.";
                }
            }
            if ($faltantes) {
                throw ValidationException::withMessages(['productos' => $faltantes]);
            }

            foreach ($t->detalles as $det) {
                $this->inventario->egreso($det->producto, $t->almacen_origen_id, $det->cantidad_solicitada,
                    $this->ctx($t, $user, $det, "Transferencia {$t->numero_completo} → {$t->destino?->nombre}"));
                $det->update(['cantidad_enviada' => $det->cantidad_solicitada]);
            }

            $t->update(['estado' => TransferenciaAlmacen::EN_TRANSITO, 'enviada_at' => now()]);

            return $t;
        });
    }

    /**
     * Ingresa al destino lo recibido. $recibidas: [detalle_id => cantidad] (por defecto, lo enviado).
     * Lo que no llega queda registrado como faltante en la observación.
     */
    public function recibir(TransferenciaAlmacen $transferencia, array $recibidas, User $user, ?string $observacion = null): TransferenciaAlmacen
    {
        return DB::transaction(function () use ($transferencia, $recibidas, $user, $observacion) {
            $t = TransferenciaAlmacen::query()->lockForUpdate()->with(['detalles.producto', 'detalles.lote', 'origen'])->findOrFail($transferencia->id);

            if ($t->estado !== TransferenciaAlmacen::EN_TRANSITO) {
                throw ValidationException::withMessages(['transferencia' => 'Solo se reciben transferencias en tránsito.']);
            }

            $faltantes = [];
            foreach ($t->detalles as $det) {
                $cant = round((float) ($recibidas[$det->id] ?? $det->cantidad_enviada), 4);

                if ($cant < 0 || $cant > $det->cantidad_enviada) {
                    throw ValidationException::withMessages(['recibidas' => "La cantidad recibida de «{$det->producto->nombre}» debe estar entre 0 y {$det->cantidad_enviada}."]);
                }
                if ($cant < $det->cantidad_enviada) {
                    $faltantes[] = "{$det->producto->nombre}: faltaron " . round($det->cantidad_enviada - $cant, 4);
                }
                if ($cant > 0) {
                    $this->inventario->ingreso($det->producto, $t->almacen_destino_id, $cant,
                        $this->ctx($t, $user, $det, "Transferencia {$t->numero_completo} ← {$t->origen?->nombre}"));
                }
                $det->update(['cantidad_recibida' => $cant]);
            }

            $nota = collect([$t->observacion, $observacion, $faltantes ? 'Faltantes al recibir: ' . implode('; ', $faltantes) . '.' : null])->filter()->implode("\n");

            $t->update([
                'estado'       => TransferenciaAlmacen::RECIBIDA,
                'recibido_por' => $user->id,
                'recibida_at'  => now(),
                'observacion'  => $nota ?: null,
            ]);

            return $t;
        });
    }

    /** Borrador → anulada. En tránsito → anulada y la mercadería vuelve al origen. */
    public function anular(TransferenciaAlmacen $transferencia, User $user): void
    {
        DB::transaction(function () use ($transferencia, $user) {
            $t = TransferenciaAlmacen::query()->lockForUpdate()->with(['detalles.producto', 'detalles.lote'])->findOrFail($transferencia->id);

            if (! in_array($t->estado, [TransferenciaAlmacen::BORRADOR, TransferenciaAlmacen::EN_TRANSITO], true)) {
                throw ValidationException::withMessages(['transferencia' => 'Una transferencia recibida o anulada no se puede anular.']);
            }

            if ($t->estado === TransferenciaAlmacen::EN_TRANSITO) {
                foreach ($t->detalles as $det) {
                    $this->inventario->ingreso($det->producto, $t->almacen_origen_id, $det->cantidad_enviada,
                        $this->ctx($t, $user, $det, "Anulación de transferencia {$t->numero_completo}"));
                }
            }

            $t->update(['estado' => TransferenciaAlmacen::ANULADA]);
        });
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function ctx(TransferenciaAlmacen $t, User $user, $det, string $obs): array
    {
        return [
            'empresa_id' => $t->empresa_id,
            'user_id'    => $user->id,
            'ref'        => ['tipo' => 'transferencia', 'id' => $t->id, 'serie' => $t->serie, 'numero' => Correlativo::formatear($t->numero)],
            'costo'      => $det->costo_unitario,
            'lote'       => $det->lote,
            'fecha'      => now(),
            'obs'        => $obs,
        ];
    }

    private function stock(int $productoId, int $almacenId): float
    {
        return (float) (AlmacenProducto::query()->where('almacen_id', $almacenId)->where('producto_id', $productoId)->value('stock') ?? 0);
    }
}
