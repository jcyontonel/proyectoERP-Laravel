<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Nucleo\Models\User;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ajuste de inventario. Tipos: ingreso / egreso (cantidades) y conteo (stock contado → diferencia).
 * borrador → (pendiente si el motivo requiere aprobación) → confirmado (mueve stock) · anulado
 */
class AjusteInventario extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const BORRADOR   = 'borrador';
    public const PENDIENTE  = 'pendiente';
    public const CONFIRMADO = 'confirmado';
    public const ANULADO    = 'anulado';

    public const TIPOS = ['ingreso' => 'Ingreso', 'egreso' => 'Salida', 'conteo' => 'Conteo físico'];

    protected $table = 'ajustes_inventario';

    protected $fillable = [
        'empresa_id', 'almacen_id', 'motivo_ajuste_id', 'user_id', 'aprobado_por', 'serie', 'numero',
        'tipo', 'estado', 'fecha_movimiento', 'observacion', 'confirmado_at',
    ];

    protected function casts(): array
    {
        return ['numero' => 'integer', 'fecha_movimiento' => 'datetime', 'confirmado_at' => 'datetime'];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class)->withTrashed();
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(MotivoAjuste::class, 'motivo_ajuste_id')->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleAjusteInventario::class, 'ajuste_id');
    }

    public function esEditable(): bool
    {
        return in_array($this->estado, [self::BORRADOR, self::PENDIENTE], true);
    }

    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->serie . '-' . Correlativo::formatear((int) $this->numero));
    }

    protected function tipoEtiqueta(): Attribute
    {
        return Attribute::get(fn () => self::TIPOS[$this->tipo] ?? $this->tipo);
    }

    /** Filtros: q, estado, tipo, almacen_id, desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $query
            ->when(filled($f['q'] ?? null), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('numero', '%' . ltrim(trim($f['q']), '0') . '%')
                ->orWhereLike('observacion', '%' . trim($f['q']) . '%')))
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('estado', $f['estado']))
            ->when(filled($f['tipo'] ?? null), fn (Builder $q) => $q->where('tipo', $f['tipo']))
            ->when(filled($f['almacen_id'] ?? null), fn (Builder $q) => $q->where('almacen_id', $f['almacen_id']))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('fecha_movimiento', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('fecha_movimiento', '<=', $f['hasta']));
    }
}
