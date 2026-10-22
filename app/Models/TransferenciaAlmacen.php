<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transferencia entre almacenes de la misma empresa.
 * borrador → en_transito (sale del origen) → recibida (entra al destino) · anulada
 */
class TransferenciaAlmacen extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const BORRADOR    = 'borrador';
    public const EN_TRANSITO = 'en_transito';
    public const RECIBIDA    = 'recibida';
    public const ANULADA     = 'anulada';

    public const ESTADOS = [
        self::BORRADOR    => 'Borrador',
        self::EN_TRANSITO => 'En tránsito',
        self::RECIBIDA    => 'Recibida',
        self::ANULADA     => 'Anulada',
    ];

    protected $table = 'transferencias_almacen';

    protected $fillable = [
        'empresa_id', 'almacen_origen_id', 'almacen_destino_id', 'user_id', 'recibido_por', 'serie', 'numero',
        'estado', 'fecha_movimiento', 'enviada_at', 'recibida_at', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'numero'           => 'integer',
            'fecha_movimiento' => 'datetime',
            'enviada_at'       => 'datetime',
            'recibida_at'      => 'datetime',
        ];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id')->withTrashed();
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id')->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleTransferencia::class, 'transferencia_id');
    }

    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->serie . '-' . Correlativo::formatear((int) $this->numero));
    }

    protected function estadoEtiqueta(): Attribute
    {
        return Attribute::get(fn () => self::ESTADOS[$this->estado] ?? $this->estado);
    }

    /** Filtros: q, estado, almacen_id (origen o destino), desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $query
            ->when(filled($f['q'] ?? null), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('numero', '%' . ltrim(trim($f['q']), '0') . '%')
                ->orWhereLike('observacion', '%' . trim($f['q']) . '%')))
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('estado', $f['estado']))
            ->when(filled($f['almacen_id'] ?? null), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('almacen_origen_id', $f['almacen_id'])->orWhere('almacen_destino_id', $f['almacen_id'])))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('fecha_movimiento', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('fecha_movimiento', '<=', $f['hasta']));
    }
}
