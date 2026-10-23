<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\AlmacenProducto;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\ExistenciaLote;
use App\Modulos\Erp\Models\Fraccionamiento;
use App\Modulos\Erp\Models\Lote;
use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\UnidadConversion;
use App\Modulos\Nucleo\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Partir productos: 1 unidad del producto de origen (Saco "Ricocan 15 kg") se convierte en `factor`
 * unidades del producto de destino ("Ricocan por kilo"), en el mismo almacén.
 *
 *  - Sale del origen y entra al destino con InventarioService (kardex en ambos productos).
 *  - El costo se reparte: costo destino = costo origen / factor (el valor del inventario no cambia).
 *  - Si el origen controla lotes, el destino hereda el mismo código de lote y vencimiento (trazabilidad).
 *  - Las reglas son unidades_conversion con producto_destino_id.
 */
class FraccionamientoService
{
    public function __construct(
        private InventarioService $inventario,
        private LoteService $lotes,
        private CorrelativoService $correlativos,
    ) {
    }

    /** Crea o actualiza la regla "1 [unidad del origen] = factor [unidad del destino]" */
    public function guardarRegla(Producto $origen, Producto $destino, float $factor, bool $activo = true): UnidadConversion
    {
        if ($origen->id === $destino->id) {
            throw ValidationException::withMessages(['producto_destino_id' => 'Elige un producto de destino distinto al de origen.']);
        }
        if ($origen->empresa_id !== $destino->empresa_id || $origen->es_servicio || $destino->es_servicio) {
            throw ValidationException::withMessages(['producto_destino_id' => 'Ambos productos deben ser de tu empresa y llevar stock.']);
        }

        return UnidadConversion::updateOrCreate(
            [
                'producto_id'         => $origen->id,
                'producto_destino_id' => $destino->id,
                'unidad_origen_id'    => $origen->tipo_unidad_id,
                'unidad_destino_id'   => $destino->tipo_unidad_id,
            ],
            ['empresa_id' => $origen->empresa_id, 'factor' => $factor, 'activo' => $activo],
        );
    }

    /**
     * Parte `cantidad` unidades del origen según la regla. Llamar dentro o fuera de una transacción.
     * $loteCodigo: obligatorio si el origen controla lotes (en automático se elige el que vence primero).
     */
    public function fraccionar(UnidadConversion $regla, int $almacenId, float $cantidad, User $user, ?string $loteCodigo = null,
        string $origen = 'manual', ?string $observacion = null): Fraccionamiento
    {
        return DB::transaction(function () use ($regla, $almacenId, $cantidad, $user, $loteCodigo, $origen, $observacion) {
            $regla->loadMissing(['producto', 'productoDestino']);
            $pOrigen  = $regla->producto;
            $pDestino = $regla->productoDestino;

            if (! $regla->activo || ! $pDestino) {
                throw ValidationException::withMessages(['regla' => 'La regla de fraccionamiento no está activa.']);
            }
            if ($cantidad <= 0) {
                throw ValidationException::withMessages(['cantidad' => 'Indica cuántas unidades vas a partir.']);
            }

            $loteOrigen = null;
            if ($loteCodigo !== null && trim($loteCodigo) !== '') {
                $loteOrigen = $this->lotes->existente($regla->empresa_id, $pOrigen, $loteCodigo);
            } elseif ($pOrigen->maneja_lotes) {
                throw ValidationException::withMessages(['lote' => "«{$pOrigen->nombre}» controla lotes: elige el lote que vas a partir."]);
            }

            $costoOrigen = $loteOrigen ? $this->lotes->costo($loteOrigen, $almacenId, $pOrigen) : (float) $pOrigen->costo_unitario;
            $cantidadDestino = round($cantidad * $regla->factor, 4);
            $costoDestino = $regla->factor > 0 ? round($costoOrigen / $regla->factor, 6) : 0;

            // El destino hereda el lote del origen (mismo código y vencimiento)
            $loteDestino = $loteOrigen
                ? $this->lotes->obtenerOCrear($regla->empresa_id, $pDestino, $loteOrigen->codigo_lote, $loteOrigen->fecha_vencimiento?->format('Y-m-d'))
                : null;

            [$serie, $numero] = $this->correlativos->emitir($regla->empresa_id, 'fraccionamiento');

            $f = Fraccionamiento::create([
                'empresa_id'           => $regla->empresa_id,
                'almacen_id'           => $almacenId,
                'user_id'              => $user->id,
                'unidad_conversion_id' => $regla->id,
                'serie'                => $serie,
                'numero'               => $numero,
                'origen'               => $origen,
                'producto_origen_id'   => $pOrigen->id,
                'lote_origen_id'       => $loteOrigen?->id,
                'cantidad_origen'      => $cantidad,
                'costo_origen'         => $costoOrigen,
                'producto_destino_id'  => $pDestino->id,
                'lote_destino_id'      => $loteDestino?->id,
                'factor'               => $regla->factor,
                'cantidad_destino'     => $cantidadDestino,
                'costo_destino'        => $costoDestino,
                'estado'               => Fraccionamiento::CONFIRMADO,
                'fecha_movimiento'     => now(),
                'observacion'          => $observacion,
            ]);

            $ref = ['tipo' => 'fraccionamiento', 'id' => $f->id, 'serie' => $serie, 'numero' => Correlativo::formatear($numero)];
            $obs = "Fraccionamiento {$f->numero_completo}: {$cantidad} → {$cantidadDestino}";

            $this->inventario->egreso($pOrigen, $almacenId, $cantidad, [
                'empresa_id' => $regla->empresa_id, 'user_id' => $user->id, 'ref' => $ref,
                'costo' => $costoOrigen, 'lote' => $loteOrigen, 'fecha' => $f->fecha_movimiento, 'obs' => "{$obs} {$pDestino->nombre}",
            ]);
            $this->inventario->ingreso($pDestino, $almacenId, $cantidadDestino, [
                'empresa_id' => $regla->empresa_id, 'user_id' => $user->id, 'ref' => $ref,
                'costo' => $costoDestino, 'lote' => $loteDestino, 'fecha' => $f->fecha_movimiento, 'obs' => "{$obs} desde {$pOrigen->nombre}",
            ]);

            // El costo del destino pasa a ser el del origen repartido (el kardex valoriza igual)
            $pDestino->update(['costo_unitario' => $costoDestino]);

            return $f;
        });
    }

    /**
     * Al vender: si al destino le faltan `faltante` unidades en el almacén, parte las unidades enteras
     * necesarias del origen (por la primera regla con stock). Devuelve los fraccionamientos creados
     * (vacío si no hay regla o el origen no alcanza: la venta mostrará el faltante de siempre).
     *
     * @return list<Fraccionamiento>
     */
    public function cubrirFaltante(Producto $destino, int $almacenId, float $faltante, User $user): array
    {
        $creados = [];
        $reglas = $destino->seObtieneDe()->where('activo', true)->with('producto')->orderByDesc('factor')->get();

        foreach ($reglas as $regla) {
            if ($faltante <= 0) {
                break;
            }
            $origen = $regla->producto;
            if (! $origen || ! $origen->activo || $regla->factor <= 0) {
                continue;
            }

            $necesarias = (int) ceil(round($faltante / $regla->factor, 6));

            // Con lotes: del que vence primero (FEFO), lote por lote
            if ($origen->maneja_lotes) {
                foreach ($this->lotesFefo($origen, $almacenId) as [$lote, $disponible]) {
                    $partir = min($necesarias, (int) floor($disponible));
                    if ($partir < 1) {
                        continue;
                    }
                    $creados[] = $this->fraccionar($regla, $almacenId, $partir, $user, $lote->codigo_lote, 'venta', 'Automático al vender');
                    $necesarias -= $partir;
                    $faltante = round($faltante - $partir * $regla->factor, 4);
                    if ($necesarias <= 0) {
                        break;
                    }
                }
                continue;
            }

            $disponible = (int) floor((float) (AlmacenProducto::query()->where('almacen_id', $almacenId)->where('producto_id', $origen->id)->value('stock') ?? 0));
            $partir = min($necesarias, $disponible);
            if ($partir >= 1) {
                $creados[] = $this->fraccionar($regla, $almacenId, $partir, $user, null, 'venta', 'Automático al vender');
                $faltante = round($faltante - $partir * $regla->factor, 4);
            }
        }

        return $creados;
    }

    /** Revierte: saca del destino lo que entró y devuelve el origen (no si ya se vendió lo partido) */
    public function anular(Fraccionamiento $fraccionamiento, User $user): void
    {
        DB::transaction(function () use ($fraccionamiento, $user) {
            $f = Fraccionamiento::query()->lockForUpdate()->with(['productoOrigen', 'productoDestino', 'loteOrigen', 'loteDestino'])->findOrFail($fraccionamiento->id);

            if ($f->esAnulado()) {
                throw ValidationException::withMessages(['fraccionamiento' => 'El fraccionamiento ya estaba anulado.']);
            }

            $ref = ['tipo' => 'fraccionamiento', 'id' => $f->id, 'serie' => $f->serie, 'numero' => Correlativo::formatear($f->numero)];

            try {
                $this->inventario->egreso($f->productoDestino, $f->almacen_id, $f->cantidad_destino, [
                    'empresa_id' => $f->empresa_id, 'user_id' => $user->id, 'ref' => $ref, 'costo' => $f->costo_destino,
                    'lote' => $f->loteDestino, 'fecha' => now(), 'obs' => "Anulación de fraccionamiento {$f->numero_completo}",
                ]);
            } catch (ValidationException) {
                throw ValidationException::withMessages([
                    'fraccionamiento' => "No se puede anular: ya no queda la cantidad partida de «{$f->productoDestino->nombre}» (se vendió o se movió).",
                ]);
            }

            $this->inventario->ingreso($f->productoOrigen, $f->almacen_id, $f->cantidad_origen, [
                'empresa_id' => $f->empresa_id, 'user_id' => $user->id, 'ref' => $ref, 'costo' => $f->costo_origen,
                'lote' => $f->loteOrigen, 'fecha' => now(), 'obs' => "Anulación de fraccionamiento {$f->numero_completo}",
            ]);

            $f->update(['estado' => Fraccionamiento::ANULADO]);
        });
    }

    /**
     * Cuánto del destino se puede obtener partiendo el stock de origen, por almacén: [almacen_id => cantidad]
     *
     * @return array<int, float>
     */
    public function disponiblePorFraccionar(Producto $destino): array
    {
        $total = [];
        foreach ($destino->seObtieneDe()->where('activo', true)->get() as $regla) {
            $stocks = AlmacenProducto::query()->where('producto_id', $regla->producto_id)->where('stock', '>=', 1)->pluck('stock', 'almacen_id');
            foreach ($stocks as $almacenId => $stock) {
                $total[$almacenId] = round(($total[$almacenId] ?? 0) + floor((float) $stock) * $regla->factor, 4);
            }
        }

        return $total;
    }

    /** @return list<array{0: Lote, 1: float}> lotes con stock en el almacén, el que vence primero arriba */
    private function lotesFefo(Producto $p, int $almacenId): array
    {
        return ExistenciaLote::query()
            ->join('lotes', 'lotes.id', '=', 'existencias_lote.lote_id')
            ->where('lotes.producto_id', $p->id)->where('lotes.activo', true)->whereNull('lotes.deleted_at')
            ->where('existencias_lote.almacen_id', $almacenId)->where('existencias_lote.cantidad', '>=', 1)
            ->orderByRaw('lotes.fecha_vencimiento IS NULL')->orderBy('lotes.fecha_vencimiento')
            ->get(['existencias_lote.lote_id', 'existencias_lote.cantidad'])
            ->map(fn ($e) => [Lote::find($e->lote_id), (float) $e->cantidad])
            ->all();
    }
}
