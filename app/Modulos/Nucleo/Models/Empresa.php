<?php

namespace App\Modulos\Nucleo\Models;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Erp\Models\Almacen;
use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Erp\Models\Compra;
use App\Modulos\Erp\Models\Correlativo;
use App\Modulos\Erp\Models\Proveedor;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Suscripciones\Models\Suscripcion;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use Auditable, HasFactory, EsActivable, SoftDeletes;

    protected $hidden = ['sunat_clave_sol', 'certificado_clave'];

    protected $fillable = [
        'ruc', 'razon_social', 'nombre_comercial', 'direccion', 'ubigeo', 'departamento', 'provincia', 'distrito',
        'telefono', 'email', 'logo_path', 'moneda', 'igv', 'precios_incluyen_igv', 'controlar_stock',
        'sunat_modo', 'sunat_usuario_sol', 'sunat_clave_sol', 'certificado_path', 'certificado_clave', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'igv'                  => 'float',
            'precios_incluyen_igv' => 'boolean',
            'controlar_stock'      => 'boolean',
            'activo'               => 'boolean',
            'sunat_clave_sol'      => 'encrypted',
            'certificado_clave'    => 'encrypted',
        ];
    }

    public function correlativos(): HasMany
    {
        return $this->hasMany(Correlativo::class);
    }

    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function proveedores(): HasMany
    {
        return $this->hasMany(Proveedor::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    /** Clientes propios de la empresa */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    /** Suscripción vigente (la no cancelada más reciente) */
    public function suscripcion(): HasOne
    {
        return $this->hasOne(Suscripcion::class)->where('estado', '!=', Suscripcion::CANCELADA)->latestOfMany();
    }

    /** Usuarios con acceso a la empresa (tabla pivote empresa_user; el rol está en model_has_roles por empresa) */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_user')->withTimestamps();
    }
}
