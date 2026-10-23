<?php

namespace App\Modulos\Suscripciones\Models;

use App\Modulos\Nucleo\Models\Concerns\Auditable;
use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Plan de suscripción de un producto (ERP, Página web) con sus límites (NULL = ilimitado) */
class Plan extends Model
{
    use Auditable, EsActivable;

    protected $table = 'planes';

    /** Recursos limitados: recurso => [columna, etiqueta] */
    public const LIMITES = [
        'comprobantes' => ['max_comprobantes_mes', 'comprobantes al mes'],
        'productos'    => ['max_productos', 'productos'],
        'usuarios'     => ['max_usuarios', 'usuarios'],
        'almacenes'    => ['max_almacenes', 'almacenes'],
    ];

    protected $fillable = [
        'producto_id', 'codigo', 'nombre', 'descripcion', 'max_comprobantes_mes', 'max_productos', 'max_usuarios', 'max_almacenes', 'max_empresas',
        'dias_prueba', 'activo', 'orden', 'modulos',
    ];

    protected function casts(): array
    {
        return [
            'max_comprobantes_mes' => 'integer', 'max_productos' => 'integer', 'max_usuarios' => 'integer',
            'max_almacenes' => 'integer', 'max_empresas' => 'integer', 'dias_prueba' => 'integer', 'activo' => 'boolean', 'orden' => 'integer',
            'modulos' => 'array',   // claves de config suscripciones.modulos; null = todos
        ];
    }

    /** El plan incluye el módulo (null = todos los módulos) */
    public function incluye(string $modulo): bool
    {
        return $this->modulos === null || in_array($modulo, $this->modulos, true);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoPlataforma::class, 'producto_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(PlanPrecio::class)->orderBy('meses');
    }

    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }

    /** Límite del recurso (null = ilimitado) */
    public function limite(string $recurso): ?int
    {
        return $this->{self::LIMITES[$recurso][0]};
    }

    public function precio(string $periodicidad): ?PlanPrecio
    {
        return $this->precios->firstWhere('periodicidad', $periodicidad);
    }
}
