<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsActivable;
use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Producto extends Model
{
    use Auditable, HasFactory, EsActivable, EsDeEmpresa, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'categoria_id', 'tipo_unidad_id', 'impuesto_id', 'codigo', 'nombre', 'descripcion',
        'precio_unitario', 'costo_unitario', 'es_servicio', 'maneja_lotes', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'float',
            'costo_unitario'  => 'float',
            'es_servicio'     => 'boolean',
            'maneja_lotes'    => 'boolean',
            'activo'          => 'boolean',
        ];
    }

    /* ───────── Relaciones ───────── */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function tipoUnidad(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class, 'tipo_unidad_id');
    }

    /** NULL = se aplica el IGV de la empresa */
    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(AlmacenProducto::class);
    }

    /** Presentaciones: 1 [unidad_origen] = factor × [unidad base] (del mismo producto) */
    public function conversiones(): HasMany
    {
        return $this->hasMany(UnidadConversion::class)->whereNull('producto_destino_id');
    }

    /** Reglas para PARTIR este producto en otro (1 Saco = 15 kg de "… por kilo") */
    public function fraccionaEn(): HasMany
    {
        return $this->hasMany(UnidadConversion::class)->whereNotNull('producto_destino_id');
    }

    /** Reglas que PRODUCEN este producto al partir otro (de qué saco salen estos kilos) */
    public function seObtieneDe(): HasMany
    {
        return $this->hasMany(UnidadConversion::class, 'producto_destino_id');
    }

    /** Se vende en fracciones (kilo, litro, metro…): la cantidad admite decimales */
    public function seVendeFraccionado(): bool
    {
        return in_array($this->tipoUnidad?->codigo, config('inventario.unidades_fraccionables'), true);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    public function kardex(): HasMany
    {
        return $this->hasMany(Kardex::class);
    }

    /* ───────── Reglas ───────── */

    /** Con ventas, compras o kardex el producto ya no puede eliminarse ni cambiar su unidad base */
    public function tieneMovimientos(): bool
    {
        return $this->kardex()->exists()
            || DetalleVenta::withTrashed()->where('producto_id', $this->id)->exists()
            || DetalleCompra::withTrashed()->where('producto_id', $this->id)->exists();
    }

    /**
     * Presentaciones activas listas para mostrar: unidad, equivalencia y precio (el propio o factor × precio base).
     *
     * @return Collection<int, object{unidad: string, factor: float, precio: float, precio_propio: bool}>
     */
    public function presentaciones(): Collection
    {
        $this->loadMissing('conversiones.unidadOrigen');

        return $this->conversiones->where('activo', true)->map(fn (UnidadConversion $c) => (object) [
            'unidad'        => $c->unidadOrigen?->descripcion ?? '—',
            'factor'        => $c->factor,
            'precio'        => $c->precio_venta ?? round($c->factor * $this->precio_unitario, 2),
            'precio_propio' => $c->precio_venta !== null,
        ])->values();
    }

    /** Margen bruto sobre el precio de venta (sin considerar IGV) */
    public function margen(): ?float
    {
        return $this->precio_unitario > 0
            ? round(($this->precio_unitario - $this->costo_unitario) / $this->precio_unitario * 100, 1)
            : null;
    }

    /* ───────── Scopes ───────── */

    /** Con al menos un almacén en o bajo su stock mínimo */
    public function scopeConStockBajo(Builder $query): void
    {
        $query->where('es_servicio', false)
            ->whereHas('stocks', fn (Builder $s) => $s->where('stock_minimo', '>', 0)->whereColumn('stock', '<=', 'stock_minimo'));
    }

    public function scopeAgotados(Builder $query): void
    {
        $query->where('es_servicio', false)
            ->whereRaw('(select COALESCE(sum(ap.stock), 0) from almacen_producto ap where ap.producto_id = productos.id) <= 0');
    }

    /** Filtros del listado: q, categoria_id, tipo, estado, stock */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $query
            ->when(filled($f['q'] ?? null), function (Builder $q) use ($f) {
                $t = trim($f['q']);
                $q->where(fn (Builder $w) => $w
                    ->whereLike('nombre', "%{$t}%")
                    ->orWhereLike('codigo', "%{$t}%")
                    ->orWhereLike('descripcion', "%{$t}%"));
            })
            ->when(filled($f['categoria_id'] ?? null), fn (Builder $q) => $q->where('categoria_id', $f['categoria_id']))
            ->when(($f['tipo'] ?? null) === 'servicio', fn (Builder $q) => $q->where('es_servicio', true))
            ->when(($f['tipo'] ?? null) === 'producto', fn (Builder $q) => $q->where('es_servicio', false))
            ->when(($f['estado'] ?? null) === 'activos', fn (Builder $q) => $q->where('activo', true))
            ->when(($f['estado'] ?? null) === 'inactivos', fn (Builder $q) => $q->where('activo', false))
            ->when(($f['stock'] ?? null) === 'bajo', fn (Builder $q) => $q->conStockBajo())
            ->when(($f['stock'] ?? null) === 'agotado', fn (Builder $q) => $q->agotados());
    }
}
