<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Facturacion\Models\ComprobanteSunat;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\User;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use App\Modulos\Erp\Models\Concerns\TieneSaldo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modulos\Nucleo\Support\Sql;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Venta extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes, TieneSaldo;

    public const CONTADO = 'contado';
    public const CREDITO = 'credito';

    // 'registrada' es el valor por defecto de la columna (en pantalla se muestra como "Completada")
    public const COMPLETADA = 'completada';
    public const BORRADOR   = 'borrador';
    public const ANULADA    = 'anulada';

    protected $fillable = [
        'empresa_id', 'almacen_id', 'caja_sesion_id', 'cliente_id', 'user_id', 'serie', 'numero', 'fecha_emision',
        'tipo', 'metodo_pago', 'condicion_pago', 'fecha_vencimiento', 'estado', 'subtotal', 'total_impuestos', 'total',
        'descuento_porcentaje', 'descuento', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'numero'               => 'integer',
            'fecha_emision'        => 'datetime',
            'fecha_vencimiento'    => 'date',
            'subtotal'             => 'float',
            'total_impuestos'      => 'float',
            'total'                => 'float',
            'descuento_porcentaje' => 'float',
            'descuento'            => 'float',
        ];
    }

    /* ───────── Relaciones ───────── */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    /** Cobros (ventas al crédito y cobros parciales) */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /** Notas de crédito y débito emitidas sobre este comprobante */
    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    /** Estado en SUNAT (facturador electrónico) */
    public function sunat(): MorphOne
    {
        return $this->morphOne(ComprobanteSunat::class, 'comprobante');
    }

    /** Solo facturas y boletas admiten notas (no los tickets) */
    public function admiteNotas(): bool
    {
        return $this->esCompletada() && in_array($this->tipo, ['01', '03'], true);
    }

    /* ───────── Scopes de reporte ───────── */

    public function scopeCompletadas(Builder $query): void
    {
        $query->where($query->qualifyColumn('estado'), self::COMPLETADA);
    }

    /** Ventas emitidas entre dos fechas (inclusive, por día) */
    public function scopeEntre(Builder $query, \DateTimeInterface $desde, \DateTimeInterface $hasta): void
    {
        $query->whereBetween($query->qualifyColumn('fecha_emision'), [
            Carbon::parse($desde)->startOfDay(),
            Carbon::parse($hasta)->endOfDay(),
        ]);
    }

    /* ───────── Estado ───────── */

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }

    public function esCompletada(): bool
    {
        return $this->estado === self::COMPLETADA;
    }

    public function esAnulada(): bool
    {
        return $this->estado === self::ANULADA;
    }

    /** Solo los borradores se pueden editar */
    public function esEditable(): bool
    {
        return $this->esBorrador();
    }

    /** Solo las completadas se pueden anular */
    public function esCredito(): bool
    {
        return $this->condicion_pago === self::CREDITO;
    }

    /** Venta al crédito con saldo pendiente: admite cobros */
    public function puedeRegistrarCobro(): bool
    {
        return $this->esCompletada() && $this->saldo() > 0;
    }

    public function esAnulable(): bool
    {
        return $this->esCompletada();
    }

    /* ───────── Accesores ───────── */

    /** "B001-000129" */
    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->serie . '-' . $this->numero);
    }

    /** "Boleta", "Factura"… */
    protected function tipoEtiqueta(): Attribute
    {
        return Attribute::get(fn () => Str::headline((string) $this->tipo));
    }

    /** "Yape", "Tarjeta"… (según config/ventas.php) */
    protected function metodoLabel(): Attribute
    {
        return Attribute::get(fn () => config('ventas.metodos_pago')[$this->metodo_pago] ?? Str::headline((string) $this->metodo_pago));
    }

    /* ───────── Scopes ───────── */

    /** Filtros del listado: q, tipo, estado, desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $pad = (int) config('ventas.numero_pad', 6);

        $query
            ->when(filled($f['q'] ?? null), function (Builder $q) use ($f, $pad) {
                $t = trim($f['q']);
                $q->where(function (Builder $w) use ($t, $pad) {
                    $w->whereLike('ventas.numero', "%{$t}%")
                        ->orWhereRaw(Sql::concat('ventas.serie', "'-'", Sql::lpad('ventas.numero', $pad)) . ' like ?', ["%{$t}%"])
                        ->orWhereHas('cliente', fn (Builder $c) => $c->where(fn (Builder $c) => $c
                            ->whereLike('nombre', "%{$t}%")
                            ->orWhereLike('apellido', "%{$t}%")
                            ->orWhereLike('numero_documento', "%{$t}%")));
                });
            })
            ->when(filled($f['tipo'] ?? null), fn (Builder $q) => $q->where('ventas.tipo', $f['tipo']))
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('ventas.estado', $f['estado']))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('ventas.fecha_emision', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('ventas.fecha_emision', '<=', $f['hasta']));
    }
}
