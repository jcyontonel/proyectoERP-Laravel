<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\EsDeEmpresa;
use App\Models\Concerns\TieneSaldo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Sql;
use Illuminate\Support\Str;

class Compra extends Model
{
    use Auditable, EsDeEmpresa, SoftDeletes, TieneSaldo;

    public const REGISTRADA = 'registrada';
    public const ANULADA    = 'anulada';

    protected $fillable = [
        'empresa_id', 'almacen_id', 'proveedor_id', 'user_id', 'serie', 'numero', 'fecha_emision',
        'tipo_comprobante', 'moneda', 'condicion_pago', 'fecha_vencimiento', 'subtotal', 'total_impuestos', 'total', 'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'   => 'datetime',
            'fecha_vencimiento' => 'date',
            'subtotal'        => 'float',
            'total_impuestos' => 'float',
            'total'           => 'float',
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

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /* ───────── Estado ───────── */

    public function esRegistrada(): bool
    {
        return $this->estado === self::REGISTRADA;
    }

    public function esAnulada(): bool
    {
        return $this->estado === self::ANULADA;
    }

    public function esAnulable(): bool
    {
        return $this->esRegistrada();
    }

    /* ───────── Pagos ───────── */

    public function puedeRegistrarPago(): bool
    {
        return $this->esRegistrada() && $this->saldo() > 0;
    }

    /* ───────── Accesores ───────── */

    protected function numeroCompleto(): Attribute
    {
        return Attribute::get(fn () => ($this->serie && $this->numero)
            ? $this->serie . '-' . $this->numero
            : 'Compra #' . $this->id);
    }

    protected function tipoEtiqueta(): Attribute
    {
        return Attribute::get(fn () => config('compras.tipos_comprobante')[$this->tipo_comprobante] ?? Str::headline((string) $this->tipo_comprobante));
    }

    /* ───────── Scopes ───────── */

    /** Filtros: q, estado, pago (pendiente|pagada), proveedor_id, desde, hasta */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $query
            ->when(filled($f['q'] ?? null), function (Builder $q) use ($f) {
                $t = trim($f['q']);
                $q->where(function (Builder $w) use ($t) {
                    $w->whereLike('compras.numero', "%{$t}%")
                        ->orWhereRaw(Sql::concat("COALESCE(compras.serie, '')", "'-'", "COALESCE(compras.numero, '')") . ' like ?', ["%{$t}%"])
                        ->orWhereHas('proveedor', fn (Builder $p) => $p->where(fn (Builder $p) => $p
                            ->whereLike('razon_social', "%{$t}%")
                            ->orWhereLike('nombre_comercial', "%{$t}%")
                            ->orWhereLike('numero_documento', "%{$t}%")));
                });
            })
            ->when(filled($f['estado'] ?? null), fn (Builder $q) => $q->where('compras.estado', $f['estado']))
            ->when(filled($f['proveedor_id'] ?? null), fn (Builder $q) => $q->where('compras.proveedor_id', $f['proveedor_id']))
            ->when(filled($f['desde'] ?? null), fn (Builder $q) => $q->whereDate('compras.fecha_emision', '>=', $f['desde']))
            ->when(filled($f['hasta'] ?? null), fn (Builder $q) => $q->whereDate('compras.fecha_emision', '<=', $f['hasta']))
            ->when(filled($f['pago'] ?? null), function (Builder $q) use ($f) {
                $pagado = '(select COALESCE(sum(p.monto), 0) from pagos p where p.compra_id = compras.id and p.estado = ? and p.deleted_at is null)';
                $q->where('compras.estado', self::REGISTRADA)
                    ->whereRaw($f['pago'] === 'pendiente' ? "compras.total > {$pagado}" : "compras.total <= {$pagado}", [Pago::REGISTRADO]);
            });
    }
}
