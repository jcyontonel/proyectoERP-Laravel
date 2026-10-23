<?php

namespace App\Modulos\Suscripciones\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** Descuento sobre el precio de una suscripción (porcentaje o monto fijo) */
class Oferta extends Model
{
    use Auditable;

    public const PORCENTAJE = 'porcentaje';
    public const MONTO      = 'monto';

    protected $fillable = [
        'nombre', 'codigo', 'plan_id', 'periodicidad', 'tipo', 'valor', 'desde', 'hasta',
        'max_usos', 'usos', 'solo_primer_pago', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'float', 'desde' => 'date', 'hasta' => 'date', 'max_usos' => 'integer', 'usos' => 'integer',
            'solo_primer_pago' => 'boolean', 'activo' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Activas, dentro de su vigencia y con usos disponibles */
    public function scopeVigentes(Builder $q, ?Carbon $fecha = null): void
    {
        $f = ($fecha ?? today())->toDateString();
        $q->where('activo', true)->whereDate('desde', '<=', $f)
            ->where(fn ($w) => $w->whereNull('hasta')->orWhereDate('hasta', '>=', $f))
            ->where(fn ($w) => $w->whereNull('max_usos')->orWhereColumn('usos', '<', 'max_usos'));
    }

    /** ¿Aplica a este plan y periodicidad? */
    public function aplicaA(int $planId, string $periodicidad): bool
    {
        return ($this->plan_id === null || $this->plan_id === $planId)
            && ($this->periodicidad === null || $this->periodicidad === $periodicidad);
    }

    /** Descuento sobre un monto (nunca mayor que el monto) */
    public function descuento(float $monto): float
    {
        $d = $this->tipo === self::PORCENTAJE ? $monto * $this->valor / 100 : $this->valor;

        return round(min(max($d, 0), $monto), 2);
    }
}
