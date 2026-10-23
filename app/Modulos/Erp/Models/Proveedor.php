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

class Proveedor extends Model
{
    use Auditable, HasFactory, EsActivable, EsDeEmpresa, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id', 'tipo_documento_id', 'numero_documento', 'razon_social', 'nombre_comercial',
        'contacto_nombre', 'contacto_cargo', 'telefono', 'celular', 'email',
        'pais', 'departamento', 'provincia', 'distrito', 'direccion', 'observacion', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    /** Razón social, o el nombre comercial si no hay */
    protected function nombre(): Attribute
    {
        return Attribute::get(fn () => $this->razon_social ?: ($this->nombre_comercial ?: 'Sin nombre'));
    }

    /** "RUC 20605432189" */
    protected function documentoEtiqueta(): Attribute
    {
        return Attribute::get(function () {
            $doc = (string) $this->numero_documento;
            if ($doc === '') {
                return '—';
            }

            $tipo = match (true) {
                $this->tipoDocumento?->codigo === '6' => 'RUC',
                $this->tipoDocumento?->codigo === '1' => 'DNI',
                $this->tipoDocumento?->codigo === '4' => 'CE',
                $this->tipoDocumento?->codigo === '7' => 'Pasaporte',
                strlen($doc) === 11                   => 'RUC',
                strlen($doc) === 8                    => 'DNI',
                default                               => 'Doc.',
            };

            return "{$tipo} {$doc}";
        });
    }

    /** Solo el tipo: "RUC", "DNI"… (vacío si no tiene documento) */
    protected function tipoDocEtiqueta(): Attribute
    {
        return Attribute::get(fn () => $this->numero_documento ? explode(' ', $this->documento_etiqueta)[0] : '');
    }

    public function scopeBuscar(Builder $query, ?string $texto): void
    {
        $query->when(filled($texto), function (Builder $q) use ($texto) {
            $t = trim($texto);
            $q->where(fn (Builder $w) => $w
                ->whereLike('razon_social', "%{$t}%")
                ->orWhereLike('nombre_comercial', "%{$t}%")
                ->orWhereLike('numero_documento', "%{$t}%")
                ->orWhereLike('contacto_nombre', "%{$t}%"));
        });
    }
}
