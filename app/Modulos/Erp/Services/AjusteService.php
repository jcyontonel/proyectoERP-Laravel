<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\AjusteInventario;
use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\MotivoAjuste;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ajustes de inventario:
 *  - ingreso / egreso: cada línea indica la cantidad que entra o sale;
 *  - conteo: cada línea indica el stock contado; al confirmar se mueve la diferencia con el stock de ESE momento.
 * Si el motivo requiere aprobación, solo quien tenga "aprobar-ajustes" puede confirmarlo.
 */
class AjusteService
{
    public function __construct(
        private InventarioService $inventario,
        private LoteService $lotes,
        private CorrelativoService $correlativos,
    ) {
    }

    /** Crea o actualiza el ajuste. accion: borrador | confirmar */
    public function guardar(array $d, User $user, int $empresaId, ?AjusteInventario $existente = null): AjusteInventario
    {
        return DB::transaction(function () use ($d, $user, $empresaId, $existente) {
            $almacen = Almacen::query()->deEmpresa($empresaId)->activo()->findOrFail($d['almacen_id']);
            $motivo  = MotivoAjuste::query()->deEmpresa($empresaId)->activo()->findOrFail($d['motivo_ajuste_id']);
            $this->validarMotivo($motivo, $d['tipo']);

            if ($existente) {
                $ajuste = AjusteInventario::query()->lockForUpdate()->findOrFail($existente->id);
                if (! $ajuste->esEditable()) {
                    throw ValidationException::withMessages(['ajuste' => 'Solo se pueden editar ajustes en borrador o pendientes.']);
                }
            } else {
                [$serie, $numero] = $this->correlativos->emitir($empresaId, 'ajuste');
                $ajuste = new AjusteInventario(['empresa_id' => $empresaId, 'user_id' => $user->id, 'serie' => $serie, 'numero' => $numero]);
            }

            $ajuste->fill([
                'almacen_id'       => $almacen->id,
                'motivo_ajuste_id' => $motivo->id,
                'tipo'             => $d['tipo'],
                'estado'           => AjusteInventario::BORRADOR,
                'fecha_movimiento' => Carbon::parse($d['fecha_movimiento'])->setTimeFrom(now()),
                'observacion'      => $d['observacion'] ?? null,
            ])->save();

            $ajuste->detalles()->delete();
            $productos = Producto::query()->deEmpresa($empresaId)->where('es_servicio', false)
                ->whereIn('id', collect($d['productos'])->pluck('id'))->get()->keyBy('id');

            foreach ($d['productos'] as $linea) {
                $p = $productos[$linea['id']] ?? null;
                if (! $p) {
                    throw ValidationException::withMessages(['productos' => 'Uno de los productos no está disponible (los servicios no llevan stock).']);
                }

                $lote = $this->lote($empresaId, $p, $linea, $d['tipo']);
                $sistema = $lote ? $this->lotes->stock($lote, $almacen->id) : $this->stock($p->id, $almacen->id);
                $esConteo = $d['tipo'] === 'conteo';

                $ajuste->detalles()->create([
                    'producto_id'    => $p->id,
                    'lote_id'        => $lote?->id,
                    'stock_sistema'  => $sistema,
                    'stock_contado'  => $esConteo ? (float) $linea['cantidad'] : null,
                    'cantidad'       => $esConteo ? round((float) $linea['cantidad'] - $sistema, 4) : (float) $linea['cantidad'],
                    'costo_unitario' => $lote ? $this->lotes->costo($lote, $almacen->id, $p) : $p->costo_unitario,
                    'observacion'    => $linea['observacion'] ?? null,
                ]);
            }

            if (($d['accion'] ?? 'borrador') === 'confirmar') {
                if ($motivo->requiere_aprobacion && $user->cannot('aprobar-ajustes')) {
                    $ajuste->update(['estado' => AjusteInventario::PENDIENTE]);
                } else {
                    $this->aplicar($ajuste, $user);
                }
            }

            return $ajuste->refresh();
        });
    }

    /** Aprueba y aplica un ajuste en borrador o pendiente */
    public function confirmar(AjusteInventario $ajuste, User $user): AjusteInventario
    {
        return DB::transaction(function () use ($ajuste, $user) {
            $a = AjusteInventario::query()->lockForUpdate()->with('motivo')->findOrFail($ajuste->id);

            if (! $a->esEditable()) {
                throw ValidationException::withMessages(['ajuste' => 'Este ajuste ya fue confirmado o anulado.']);
            }
            if ($a->motivo?->requiere_aprobacion && $user->cannot('aprobar-ajustes')) {
                throw ValidationException::withMessages(['ajuste' => 'El motivo «' . $a->motivo->nombre . '» requiere la aprobación de un responsable.']);
            }

            $this->aplicar($a, $user);

            return $a->refresh();
        });
    }

    public function anular(AjusteInventario $ajuste): void
    {
        DB::transaction(function () use ($ajuste) {
            $a = AjusteInventario::query()->lockForUpdate()->findOrFail($ajuste->id);

            if (! $a->esEditable()) {
                throw ValidationException::withMessages([
                    'ajuste' => 'Un ajuste confirmado ya movió el stock: para corregirlo registra un ajuste en sentido contrario.',
                ]);
            }

            $a->update(['estado' => AjusteInventario::ANULADO]);
        });
    }

    /* ───────────────────────── Privado ───────────────────────── */

    /** Mueve el stock de cada línea (en un conteo, la diferencia con el stock actual) */
    private function aplicar(AjusteInventario $a, User $user): void
    {
        $a->loadMissing(['detalles.producto', 'detalles.lote', 'motivo']);
        $ref = ['tipo' => 'ajuste', 'id' => $a->id, 'serie' => $a->serie, 'numero' => Correlativo::formatear($a->numero)];

        foreach ($a->detalles as $det) {
            $p = $det->producto;

            if ($a->tipo === 'conteo') {
                $actual = $det->lote ? $this->lotes->stock($det->lote, $a->almacen_id) : $this->stock($p->id, $a->almacen_id);
                $diferencia = round($det->stock_contado - $actual, 4);
                $det->update(['stock_sistema' => $actual, 'cantidad' => $diferencia]);
            } else {
                $diferencia = $a->tipo === 'egreso' ? -$det->cantidad : $det->cantidad;
            }

            if (abs($diferencia) < 0.00005) {
                continue;
            }

            $ctx = [
                'empresa_id' => $a->empresa_id,
                'user_id'    => $user->id,
                'ref'        => $ref,
                'costo'      => $det->costo_unitario,
                'lote'       => $det->lote,
                'fecha'      => $a->fecha_movimiento,
                'obs'        => "Ajuste {$a->numero_completo} · {$a->motivo?->nombre}",
            ];

            $diferencia > 0
                ? $this->inventario->ingreso($p, $a->almacen_id, $diferencia, $ctx)
                : $this->inventario->egreso($p, $a->almacen_id, abs($diferencia), $ctx);
        }

        $a->update([
            'estado'        => 'confirmado',
            'aprobado_por'  => $a->motivo?->requiere_aprobacion ? $user->id : null,
            'confirmado_at' => now(),
        ]);
    }

    private function validarMotivo(MotivoAjuste $motivo, string $tipo): void
    {
        $permitidos = match ($tipo) {
            'ingreso' => ['ingreso', 'ambos'],
            'egreso'  => ['egreso', 'ambos'],
            default   => ['ambos'],
        };

        if (! in_array($motivo->tipo_movimiento, $permitidos, true)) {
            throw ValidationException::withMessages([
                'motivo_ajuste_id' => "El motivo «{$motivo->nombre}» no se puede usar en un ajuste de tipo " . (AjusteInventario::TIPOS[$tipo] ?? $tipo) . '.',
            ]);
        }
    }

    /** Lote de la línea: en ingresos se crea si no existe; en salidas y conteos debe existir */
    private function lote(int $empresaId, Producto $p, array $linea, string $tipo)
    {
        $codigo = trim((string) ($linea['lote'] ?? ''));

        if ($codigo === '') {
            if ($p->maneja_lotes) {
                throw ValidationException::withMessages(['productos' => "«{$p->nombre}» controla lotes: indica el lote de la línea."]);
            }

            return null;
        }

        return $tipo === 'ingreso'
            ? $this->lotes->obtenerOCrear($empresaId, $p, $codigo, $linea['vence'] ?? null)
            : $this->lotes->existente($empresaId, $p, $codigo);
    }

    private function stock(int $productoId, int $almacenId): float
    {
        return (float) (AlmacenProducto::query()->where('almacen_id', $almacenId)->where('producto_id', $productoId)->value('stock') ?? 0);
    }
}
