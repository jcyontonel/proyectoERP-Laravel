<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan contratado por una empresa. `estado` guarda lo que decide el administrador (prueba, activa,
 * suspendida, cancelada); vencida/en gracia se deduce de vence_el con estadoEfectivo().
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
        'empresa_id', 'plan_id', 'periodicidad', 'precio', 'oferta_id', 'estado', 'inicio', 'vence_el',
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

    /** No canceladas (la vigente de cada empresa) */
    public function scopeVigentes(Builder $q): void
    {
        $q->where('estado', '!=', self::CANCELADA);
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
