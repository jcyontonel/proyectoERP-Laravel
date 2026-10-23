<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsDeEmpresa;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cotización (propuesta de venta). "vencida" se deduce de vence_el; "convertida" enlaza la venta */
class Cotizacion extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const EMITIDA    = 'emitida';
    public const ACEPTADA   = 'aceptada';
    public const RECHAZADA  = 'rechazada';
    public const CONVERTIDA = 'convertida';
    public const ANULADA    = 'anulada';
    public const VENCIDA    = 'vencida';   // solo efectivo

    /** estado efectivo => [etiqueta, tono del badge] */
    public const ESTADOS = [
        self::EMITIDA    => ['Emitida', 'info'],
        self::ACEPTADA   => ['Aceptada', 'success'],
        self::VENCIDA    => ['Vencida', 'warning'],
        self::RECHAZADA  => ['Rechazada', 'danger'],
        self::CONVERTIDA => ['Convertida en venta', 'success'],
        self::ANULADA    => ['Anulada', 'neutral'],
    ];

    protected $table = 'cotizaciones';

    protected $fillable = [
        'empresa_id', 'cliente_id', 'user_id', 'serie', 'numero', 'fecha_emision', 'vence_el', 'moneda',
        'descuento_porcentaje', 'descuento', 'subtotal', 'total_impuestos', 'total', 'estado', 'condiciones',
        'observacion', 'venta_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime', 'vence_el' => 'date', 'numero' => 'integer', 'descuento_porcentaje' => 'float',
            'descuento' => 'float', 'subtotal' => 'float', 'total_impuestos' => 'float', 'total' => 'float',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCotizacion::class);
    }

    /** Estado a la fecha: una emitida o aceptada fuera de validez figura como vencida */
    public function estadoEfectivo(): string
    {
        return in_array($this->estado, [self::EMITIDA, self::ACEPTADA], true) && $this->vence_el->lt(today())
            ? self::VENCIDA
            : $this->estado;
    }

    /** Se puede editar mientras no se acepte, convierta, rechace o anule */
    public function esEditable(): bool
    {
        return $this->estado === self::EMITIDA;
    }

    /** Se puede convertir en venta (aunque esté vencida: los precios se actualizan al vender) */
    public function esConvertible(): bool
    {
        return in_array($this->estado, [self::EMITIDA, self::ACEPTADA], true);
    }

    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->serie . '-' . Correlativo::formatear((int) $this->numero));
    }

    /** Filtros: q (número o cliente), estado (incluye "vencida"), desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $pad = (int) config('ventas.numero_pad', 6);
        $hoy = today()->toDateString();
        $query
            ->when(filled($f['q'] ?? null), function (Builder $q) use ($f, $pad) {
                $t = trim($f['q']);
                $q->where(fn (Builder $w) => $w
                    ->orWhereRaw(Sql::concat('cotizaciones.serie', "'-'", Sql::lpad('cotizaciones.numero', $pad)) . ' like ?', ["%{$t}%"])
                    ->orWhereHas('cliente', fn (Builder $c) => $c->whereLike('nombre', "%{$t}%")->orWhereLike('apellido', "%{$t}%")->orWhereLike('numero_documento', "%{$t}%")));
            })
            ->when(($f['estado'] ?? null) === self::VENCIDA, fn (Builder $q) => $q->whereIn('estado', [self::EMITIDA, self::ACEPTADA])->whereDate('vence_el', '<', $hoy))
            ->when(in_array($f['estado'] ?? null, [self::EMITIDA, self::ACEPTADA], true), fn (Builder $q) => $q->where('estado', $f['estado'])->whereDate('vence_el', '>=', $hoy))
            ->when(in_array($f['estado'] ?? null, [self::RECHAZADA, self::CONVERTIDA, self::ANULADA], true), fn (Builder $q) => $q->where('estado', $f['estado']))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('fecha_emision', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('fecha_emision', '<=', $f['hasta']));
    }
}
