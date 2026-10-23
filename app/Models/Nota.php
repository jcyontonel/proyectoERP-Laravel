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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Nota de crédito (07) o de débito (08) sobre una factura o boleta emitida */
class Nota extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const CREDITO = '07';
    public const DEBITO  = '08';

    public const EMITIDA = 'emitida';
    public const ANULADA = 'anulada';

    /**
     * Motivos (catálogos SUNAT 09 y 10) => [descripción, alcance]
     *   total: toda la venta (anula o devuelve todo) · items: productos y cantidades · monto: un importe
     */
    public const MOTIVOS = [
        self::CREDITO => [
            '01' => ['Anulación de la operación', 'total'],
            '02' => ['Anulación por error en el RUC', 'total'],
            '06' => ['Devolución total', 'total'],
            '07' => ['Devolución por ítem', 'items'],
            '04' => ['Descuento global', 'monto'],
            '05' => ['Descuento por ítem', 'monto'],
            '09' => ['Disminución en el valor', 'monto'],
            '10' => ['Otros conceptos', 'monto'],
        ],
        self::DEBITO => [
            '01' => ['Intereses por mora', 'monto'],
            '02' => ['Aumento en el valor', 'monto'],
            '03' => ['Penalidades / otros conceptos', 'monto'],
        ],
    ];

    protected $table = 'notas_credito_debito';

    protected $fillable = [
        'empresa_id', 'venta_id', 'user_id', 'tipo', 'serie', 'numero', 'fecha_emision', 'motivo_codigo',
        'motivo_descripcion', 'moneda', 'subtotal', 'total_impuestos', 'total', 'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime', 'numero' => 'integer',
            'subtotal' => 'float', 'total_impuestos' => 'float', 'total' => 'float',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleNota::class, 'nota_id');
    }

    /** Estado en SUNAT (facturador electrónico) */
    public function sunat(): MorphOne
    {
        return $this->morphOne(ComprobanteSunat::class, 'comprobante');
    }

    public function devolucion(): HasOne
    {
        return $this->hasOne(Devolucion::class, 'nota_id');
    }

    public function esCredito(): bool
    {
        return $this->tipo === self::CREDITO;
    }

    public function esAnulada(): bool
    {
        return $this->estado === self::ANULADA;
    }

    /** total | items | monto */
    public function alcance(): string
    {
        return self::MOTIVOS[$this->tipo][$this->motivo_codigo][1] ?? 'monto';
    }

    /** "FC01-000012" */
    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->serie . '-' . Correlativo::formatear((int) $this->numero));
    }

    protected function tipoEtiqueta(): Attribute
    {
        return Attribute::get(fn () => $this->tipo === self::CREDITO ? 'Nota de crédito' : 'Nota de débito');
    }

    public function scopeEmitidas(Builder $q): void
    {
        $q->where('estado', self::EMITIDA);
    }

    /** Filtros: q (número o comprobante), tipo, estado, desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $pad = (int) config('ventas.numero_pad', 6);
        $query
            ->when(filled($f['q'] ?? null), function (Builder $q) use ($f, $pad) {
                $t = trim($f['q']);
                $q->where(fn (Builder $w) => $w
                    ->orWhereRaw(Sql::concat('notas_credito_debito.serie', "'-'", Sql::lpad('notas_credito_debito.numero', $pad)) . ' like ?', ["%{$t}%"])
                    ->orWhereHas('venta', fn (Builder $v) => $v->whereRaw(Sql::concat('ventas.serie', "'-'", Sql::lpad('ventas.numero', $pad)) . ' like ?', ["%{$t}%"])
                        ->orWhereHas('cliente', fn (Builder $c) => $c->whereLike('nombre', "%{$t}%")->orWhereLike('numero_documento', "%{$t}%"))));
            })
            ->when(filled($f['tipo'] ?? null), fn (Builder $q) => $q->where('notas_credito_debito.tipo', $f['tipo']))
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('notas_credito_debito.estado', $f['estado']))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('notas_credito_debito.fecha_emision', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('notas_credito_debito.fecha_emision', '<=', $f['hasta']));
    }
}
