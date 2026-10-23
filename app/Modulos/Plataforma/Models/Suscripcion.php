<?php

namespace App\Modulos\Plataforma\Models;

use App\Modulos\Nucleo\Models\Empresa;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan contratado por un titular para una o más empresas (suscripcion_empresa, hasta plan.max_empresas).
 * empresa_id es la empresa principal (la que la contrató). Una empresa tiene a lo más una vigente por producto.
 * `estado` guarda lo que decide el administrador (prueba, activa, suspendida, cancelada); vencida/en gracia
 * se deduce de vence_el con estadoEfectivo().
 */
class Suscripcion extends Model
{
    use Auditable;

    public const PRUEBA     = 'prueba';
    public const ACTIVA     = 'activa';
    public const VENCIDA    = 'vencida';      // solo efectivo: pasó vence_el, dentro de la gracia
    public const SUSPENDIDA = 'suspendida';   // manual o pasada la gracia
    public const CANCELADA  = 'cancelada';

    protected $table = 'suscripciones';

    protected $fillable = [
        'empresa_id', 'titular_id', 'plan_id', 'periodicidad', 'precio', 'oferta_id', 'estado', 'inicio', 'vence_el',
        'renovacion_automatica', 'motivo_suspension', 'cancelada_el', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'float', 'inicio' => 'date', 'vence_el' => 'date', 'renovacion_automatica' => 'boolean',
            'cancelada_el' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function titular(): BelongsTo
    {
        return $this->belongsTo(Titular::class);
    }

    /** Empresas que cubre (incluida la principal) */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'suscripcion_empresa')->withTimestamps();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoSuscripcion::class);
    }

    /** No canceladas (la vigente de cada empresa y producto) */
    public function scopeVigentes(Builder $q): void
    {
        $q->where('estado', '!=', self::CANCELADA);
    }

    /** Que cubren a la empresa */
    public function scopeDeEmpresa(Builder $q, int $empresaId): void
    {
        $q->whereHas('empresas', fn (Builder $e) => $e->where('empresas.id', $empresaId));
    }

    /** De planes del producto (erp, web…) */
    public function scopeDelProducto(Builder $q, string $producto): void
    {
        $q->whereHas('plan.producto', fn (Builder $p) => $p->where('codigo', $producto));
    }

    /**
     * Estado real a la fecha: prueba/activa mientras no venza; vencida durante los días de gracia;
     * suspendida pasada la gracia o si el administrador la suspendió.
     */
    public function estadoEfectivo(): string
    {
        if (in_array($this->estado, [self::SUSPENDIDA, self::CANCELADA], true)) {
            return $this->estado;
        }
        if (today()->lte($this->vence_el)) {
            return $this->estado;   // prueba | activa
        }

        return today()->lte($this->vence_el->copy()->addDays((int) config('suscripciones.dias_gracia')))
            ? self::VENCIDA
            : self::SUSPENDIDA;
    }

    /** ¿Puede emitir comprobantes y registrar? */
    public function permiteOperar(): bool
    {
        return in_array($this->estadoEfectivo(), [self::PRUEBA, self::ACTIVA, self::VENCIDA], true);
    }

    public function meses(): int
    {
        return (int) (config('suscripciones.periodicidades')[$this->periodicidad][1] ?? 1);
    }

    /** Días que faltan para el vencimiento (negativo si ya venció) */
    public function diasRestantes(): int
    {
        return (int) today()->diffInDays($this->vence_el, false);
    }
}
