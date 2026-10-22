<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Turno de caja: apertura → cobros y movimientos → cierre con arqueo */
class CajaSesion extends Model
{
    use Auditable, EsDeEmpresa;

    public const ABIERTA = 'abierta';
    public const CERRADA = 'cerrada';

    protected $table = 'caja_sesiones';

    protected $fillable = [
        'empresa_id', 'caja_id', 'user_id', 'cerrado_por', 'estado', 'fecha_apertura', 'monto_apertura',
        'fecha_cierre', 'monto_esperado', 'monto_contado', 'diferencia', 'arqueo', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre'   => 'datetime',
            'monto_apertura' => 'float',
            'monto_esperado' => 'float',
            'monto_contado'  => 'float',
            'diferencia'     => 'float',
            'arqueo'         => 'array',
        ];
    }

    /* ───────── Relaciones ───────── */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    /** Cobros de ventas y pagos a proveedores hechos durante el turno */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /* ───────── Estado ───────── */

    public function scopeAbiertas(Builder $query): void
    {
        $query->where($query->qualifyColumn('estado'), self::ABIERTA);
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ABIERTA;
    }

    /* ───────── Cálculos ───────── */

    /**
     * Efectivo que debería haber en el cajón: apertura + cobros en efectivo − pagos en efectivo
     * + ingresos manuales en efectivo − egresos manuales en efectivo.
     */
    public function efectivoEsperado(): float
    {
        $pagos = (float) $this->pagos()
            ->where('estado', Pago::REGISTRADO)
            ->where('metodo_pago', 'efectivo')
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN monto ELSE -monto END), 0) AS neto', [Pago::TIPO_COBRO])
            ->value('neto');

        $manuales = (float) $this->movimientos()
            ->where('estado', MovimientoCaja::REGISTRADO)
            ->where('metodo_pago', 'efectivo')
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN monto ELSE -monto END), 0) AS neto', [MovimientoCaja::INGRESO])
            ->value('neto');

        return round($this->monto_apertura + $pagos + $manuales, 2);
    }
}
