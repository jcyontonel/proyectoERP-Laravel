<?php

namespace App\Modulos\Catalogo\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1 unidad_origen = factor × unidad_destino. Dos usos:
 *  - presentación (producto_destino_id NULL): el mismo producto en otra unidad, con precio propio opcional;
 *  - regla de fraccionamiento (producto_destino_id): 1 unidad del producto se parte en `factor`
 *    unidades de OTRO producto (1 "Ricocan 15 kg" = 15 kg de "Ricocan por kilo").
 */
class UnidadConversion extends Model
{
    use Auditable, EsActivable;

    protected $table = 'unidades_conversion';

    protected $fillable = [
        'empresa_id', 'producto_id', 'producto_destino_id', 'unidad_origen_id', 'unidad_destino_id', 'factor', 'precio_venta', 'activo',
    ];

    protected function casts(): array
    {
        return ['factor' => 'float', 'precio_venta' => 'float', 'activo' => 'boolean'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function productoDestino(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_destino_id');
    }

    public function unidadOrigen(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class, 'unidad_origen_id');
    }

    public function unidadDestino(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class, 'unidad_destino_id');
    }

    public function scopePresentaciones(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('producto_destino_id'));
    }

    public function scopeFraccionamientos(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('producto_destino_id'));
    }

    public function esFraccionamiento(): bool
    {
        return $this->producto_destino_id !== null;
    }
}
