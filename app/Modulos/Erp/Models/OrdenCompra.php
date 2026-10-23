<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Nucleo\Models\User;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use App\Modulos\Nucleo\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Orden de compra al proveedor: se recibe con compras (total o parcial) */
class OrdenCompra extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes;

    public const EMITIDA  = 'emitida';
    public const PARCIAL  = 'parcial';
    public const RECIBIDA = 'recibida';
    public const CERRADA  = 'cerrada';   // recibida en parte y no se esperará el resto
    public const ANULADA  = 'anulada';

    /** estado => [etiqueta, tono del badge] */
    public const ESTADOS = [
        self::EMITIDA  => ['Emitida', 'info'],
        self::PARCIAL  => ['Recibida en parte', 'warning'],
        self::RECIBIDA => ['Recibida', 'success'],
        self::CERRADA  => ['Cerrada', 'neutral'],
        self::ANULADA  => ['Anulada', 'neutral'],
    ];

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'empresa_id', 'proveedor_id', 'almacen_id', 'user_id', 'serie', 'numero', 'fecha_emision', 'fecha_entrega',
        'moneda', 'condicion_pago', 'afecto_igv', 'subtotal', 'total_impuestos', 'total', 'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime', 'fecha_entrega' => 'date', 'numero' => 'integer', 'afecto_igv' => 'boolean',
            'subtotal' => 'float', 'total_impuestos' => 'float', 'total' => 'float',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class)->withTrashed();
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleOrdenCompra::class);
    }

    /** Compras que recibieron mercadería de esta orden */
    public function compras(): BelongsToMany
    {
        return $this->belongsToMany(Compra::class, 'recepciones_orden_compra')->withTimestamps();
    }

    public function esEditable(): bool
    {
        return $this->estado === self::EMITIDA;
    }

    /** Aún espera mercadería */
    public function esRecibible(): bool
    {
        return in_array($this->estado, [self::EMITIDA, self::PARCIAL], true);
    }

    /** Atrasada: pasó la fecha de entrega y aún espera mercadería */
    public function estaAtrasada(): bool
    {
        return $this->esRecibible() && $this->fecha_entrega && $this->fecha_entrega->lt(today());
    }

    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->serie . '-' . Correlativo::formatear((int) $this->numero));
    }

    /** Filtros: q (número o proveedor), estado, proveedor_id, desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $pad = (int) config('ventas.numero_pad', 6);
        $query
            ->when(filled($f['q'] ?? null), function (Builder $q) use ($f, $pad) {
                $t = trim($f['q']);
                $q->where(fn (Builder $w) => $w
                    ->orWhereRaw(Sql::concat('ordenes_compra.serie', "'-'", Sql::lpad('ordenes_compra.numero', $pad)) . ' like ?', ["%{$t}%"])
                    ->orWhereHas('proveedor', fn (Builder $p) => $p->whereLike('razon_social', "%{$t}%")->orWhereLike('nombre_comercial', "%{$t}%")->orWhereLike('numero_documento', "%{$t}%")));
            })
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('ordenes_compra.estado', $f['estado']))
            ->when(filled($f['proveedor_id'] ?? null), fn (Builder $q) => $q->where('ordenes_compra.proveedor_id', $f['proveedor_id']))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('fecha_emision', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('fecha_emision', '<=', $f['hasta']));
    }
}
