<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Nucleo\Models\TipoDocumento;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente de UNA empresa (tipo + número de documento únicos dentro de la empresa).
 * Otra empresa con el mismo DNI/RUC tiene su propio registro: datos de contacto e historial no se comparten.
 */
class Cliente extends Model
{
    use Auditable, HasFactory, EsActivable, EsDeEmpresa, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'tipo_documento_id', 'numero_documento', 'nombre', 'apellido', 'correo', 'telefono', 'direccion', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /* ───────── Relaciones ───────── */

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /* ───────── Scopes ───────── */

    public function scopeBuscar(Builder $query, ?string $texto): void
    {
        $query->when(filled($texto), function (Builder $q) use ($texto) {
            $t = trim($texto);
            $q->where(fn (Builder $w) => $w
                ->whereLike('nombre', "%{$t}%")
                ->orWhereLike('apellido', "%{$t}%")
                ->orWhereLike('numero_documento', "%{$t}%")
                ->orWhereLike('correo', "%{$t}%")
                ->orWhereLike('telefono', "%{$t}%"));
        });
    }

    /** Filtros del listado: q, tipo_documento_id, estado */
    public function scopeFiltrar(Builder $query, array $f): void
    {
        $query
            ->buscar($f['q'] ?? null)
            ->when(filled($f['tipo_documento_id'] ?? null), fn (Builder $q) => $q->where('tipo_documento_id', $f['tipo_documento_id']))
            ->when(($f['estado'] ?? null) === 'activos', fn (Builder $q) => $q->where('activo', true))
            ->when(($f['estado'] ?? null) === 'inactivos', fn (Builder $q) => $q->where('activo', false));
    }

    /* ───────── Accesores ───────── */

    /** "Valeria Mendoza" (el apellido puede estar vacío en empresas) */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim($this->nombre . ' ' . ($this->apellido ?? '')));
    }

    protected function esRuc(): Attribute
    {
        return Attribute::get(fn () => $this->tipoDocumento?->codigo === '6'
            || strlen((string) $this->numero_documento) === 11);
    }

    /** Solo el tipo: "DNI", "RUC", "CE"… (catálogo SUNAT 06, columna abreviatura) */
    protected function tipoDocEtiqueta(): Attribute
    {
        return Attribute::get(fn () => $this->tipoDocumento?->abreviatura
            ?? match (strlen((string) $this->numero_documento)) {
                8       => 'DNI',
                11      => 'RUC',
                default => 'Doc.',
            });
    }

    /** "DNI 74821563" / "RUC 20605432189" */
    protected function documentoEtiqueta(): Attribute
    {
        return Attribute::get(fn () => filled($this->numero_documento)
            ? "{$this->tipo_doc_etiqueta} {$this->numero_documento}"
            : '—');
    }

    /** "VM" para el avatar */
    protected function iniciales(): Attribute
    {
        return Attribute::get(fn () => str($this->nombre_completo)->explode(' ')->filter()
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->join(''));
    }
}
