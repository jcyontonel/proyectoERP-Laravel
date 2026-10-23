<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Catalogo\Models\UnidadConversion;
use App\Modulos\Nucleo\Models\User;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Un producto partido en otro (ej. 1 saco → 15 kg). Manual o automático al vender. */
class Fraccionamiento extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const CONFIRMADO = 'confirmado';
    public const ANULADO    = 'anulado';

    protected $fillable = [
        'empresa_id', 'almacen_id', 'user_id', 'unidad_conversion_id', 'venta_id', 'serie', 'numero', 'origen',
        'producto_origen_id', 'lote_origen_id', 'cantidad_origen', 'costo_origen',
        'producto_destino_id', 'lote_destino_id', 'factor', 'cantidad_destino', 'costo_destino',
        'estado', 'fecha_movimiento', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'numero'           => 'integer',
            'cantidad_origen'  => 'float',
            'costo_origen'     => 'float',
            'factor'           => 'float',
            'cantidad_destino' => 'float',
            'costo_destino'    => 'float',
            'fecha_movimiento' => 'datetime',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(UnidadConversion::class, 'unidad_conversion_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function productoOrigen(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_origen_id')->withTrashed();
    }

    public function productoDestino(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_destino_id')->withTrashed();
    }

    public function loteOrigen(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_origen_id')->withTrashed();
    }

    public function loteDestino(): BelongsTo
    {
        return $this->belongsTo(Lote::class, 'lote_destino_id')->withTrashed();
    }

    public function esAnulado(): bool
    {
        return $this->estado === self::ANULADO;
    }

    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->serie . '-' . Correlativo::formatear((int) $this->numero));
    }

    /** Filtros: q (producto), origen, estado, almacen_id, desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $query
            ->when(filled($f['q'] ?? null), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('productoOrigen', fn ($p) => $p->whereLike('nombre', '%' . trim($f['q']) . '%'))
                ->orWhereHas('productoDestino', fn ($p) => $p->whereLike('nombre', '%' . trim($f['q']) . '%'))))
            ->when(filled($f['tipo'] ?? null), fn (Builder $q) => $q->where('origen', $f['tipo']))
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('estado', $f['estado']))
            ->when(filled($f['almacen_id'] ?? null), fn (Builder $q) => $q->where('almacen_id', $f['almacen_id']))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('fecha_movimiento', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('fecha_movimiento', '<=', $f['hasta']));
    }
}
