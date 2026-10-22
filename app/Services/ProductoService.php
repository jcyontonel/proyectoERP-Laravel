<?php

namespace App\Services;

use App\Models\AlmacenProducto;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\UnidadConversion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductoService
{
    public function __construct(private InventarioService $inventario)
    {
    }

    /**
     * Crea el producto con sus presentaciones, el stock mínimo por almacén y,
     * si se indicó, el stock inicial (entra por InventarioService: queda en kardex).
     */
    public function crear(array $d, int $userId, int $empresaId): Producto
    {
        $empresa = Empresa::findOrFail($empresaId);
        app(SuscripcionService::class)->exigir($empresaId, 'productos');

        return DB::transaction(function () use ($d, $userId, $empresa) {
            $p = Producto::create($this->datos($d) + ['empresa_id' => $empresa->id]);

            $this->sincronizarConversiones($p, $d['conversiones'] ?? []);

            if (! $p->es_servicio) {
                $this->sincronizarAlmacenes($p, $d['almacenes'] ?? [], $userId, $empresa, esAlta: true);
            }

            return $p;
        });
    }

    /** Actualiza datos, presentaciones y stock mínimo. El stock NO se edita aquí: se mueve con compras, ventas y ajustes. */
    public function actualizar(Producto $producto, array $d, int $userId, int $empresaId): Producto
    {
        $empresa = Empresa::findOrFail($empresaId);

        return DB::transaction(function () use ($producto, $d, $userId, $empresa) {
            $p = Producto::query()->lockForUpdate()->findOrFail($producto->id);

            $cambiaBase     = (int) $d['tipo_unidad_id'] !== (int) $p->tipo_unidad_id;
            $cambiaServicio = (bool) $d['es_servicio'] !== (bool) $p->es_servicio;

            // Cambiar la unidad base de un producto con historial reinterpretaría todo su stock y kardex
            if (($cambiaBase || $cambiaServicio) && $p->tieneMovimientos()) {
                throw ValidationException::withMessages([
                    'tipo_unidad_id' => 'Este producto ya tiene movimientos: no se puede cambiar su unidad base ni si es servicio.',
                ]);
            }

            $p->update($this->datos($d));
            $p->refresh();

            $this->sincronizarConversiones($p, $d['conversiones'] ?? []);

            if (! $p->es_servicio) {
                $this->sincronizarAlmacenes($p, $d['almacenes'] ?? [], $userId, $empresa, esAlta: false);
            }

            return $p;
        });
    }

    /** Solo se elimina si nunca tuvo movimientos; si no, conviene desactivarlo */
    public function eliminar(Producto $producto): void
    {
        DB::transaction(function () use ($producto) {
            $p = Producto::query()->lockForUpdate()->findOrFail($producto->id);

            if ($p->tieneMovimientos()) {
                throw ValidationException::withMessages([
                    'producto' => "«{$p->nombre}» tiene ventas, compras o movimientos de kardex: no se puede eliminar. Desactívalo desde Editar.",
                ]);
            }

            $p->conversiones()->delete();
            AlmacenProducto::query()->where('producto_id', $p->id)->delete();
            $p->delete();
        });
    }

    private function datos(array $d): array
    {
        return [
            'categoria_id'    => $d['categoria_id'] ?? null,
            'tipo_unidad_id'  => $d['tipo_unidad_id'],
            'impuesto_id'     => $d['impuesto_id'] ?? null,
            'codigo'          => filled($d['codigo'] ?? null) ? trim($d['codigo']) : null,
            'nombre'          => trim($d['nombre']),
            'descripcion'     => $d['descripcion'] ?? null,
            'precio_unitario' => $d['precio_unitario'],
            'costo_unitario'  => $d['costo_unitario'] ?? 0,
            'es_servicio'     => (bool) ($d['es_servicio'] ?? false),
            'maneja_lotes'    => ! ($d['es_servicio'] ?? false) && (bool) ($d['maneja_lotes'] ?? false),
            'activo'          => (bool) ($d['activo'] ?? true),
        ];
    }

    /** unidad_origen = presentación, unidad_destino = unidad base, factor = unidades base por presentación */
    private function sincronizarConversiones(Producto $p, array $filas): void
    {
        if ($p->es_servicio || ! $p->tipo_unidad_id) {
            $p->conversiones()->delete();

            return;
        }

        $conservar = [];
        foreach ($filas as $f) {
            $c = UnidadConversion::updateOrCreate(
                [
                    'producto_id'         => $p->id,
                    'producto_destino_id' => null,   // presentación (no regla de fraccionamiento)
                    'unidad_origen_id'    => (int) $f['unidad_id'],
                    'unidad_destino_id'   => $p->tipo_unidad_id,
                ],
                [
                    'empresa_id'   => $p->empresa_id,
                    'factor'       => $f['factor'],
                    'precio_venta' => filled($f['precio_venta'] ?? null) ? $f['precio_venta'] : null,
                    'activo'       => (bool) ($f['activo'] ?? true),
                ],
            );
            $conservar[] = $c->id;
        }

        // Lo que ya no está en el formulario (incluye filas viejas con la base invertida) se quita
        $p->conversiones()->whereNotIn('id', $conservar)->delete();
    }

    private function sincronizarAlmacenes(Producto $p, array $filas, int $userId, Empresa $empresa, bool $esAlta): void
    {
        foreach ($filas as $f) {
            $fila = AlmacenProducto::firstOrNew(['almacen_id' => (int) $f['id'], 'producto_id' => $p->id]);

            if (! $fila->exists) {
                $fila->stock = 0;
            }
            $fila->stock_minimo = (float) ($f['stock_minimo'] ?? 0);
            $fila->save();

            $inicial = round((float) ($f['stock_inicial'] ?? 0), 4);

            if ($esAlta && $empresa->controlar_stock && $inicial > 0) {
                $this->inventario->ingreso($p, (int) $f['id'], $inicial, [
                    'empresa_id' => $p->empresa_id,
                    'user_id'    => $userId,
                    'ref'        => ['tipo' => 'stock_inicial', 'id' => $p->id, 'serie' => null, 'numero' => null],
                    'costo'      => $p->costo_unitario,
                    'fecha'      => now(),
                    'obs'        => 'Stock inicial',
                ]);
            }
        }
    }
}