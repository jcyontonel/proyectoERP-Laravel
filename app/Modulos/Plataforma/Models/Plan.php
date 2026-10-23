<?php

namespace App\Modulos\Plataforma\Models;

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
            'modulos' => 'array',   // funcionalidades OPCIONALES elegidas (config suscripciones.productos); null = todas
        ];
    }

    /** Código del sistema del plan (erp, web…) */
    public function codigoProducto(): string
    {
        return $this->producto?->codigo ?? ProductoPlataforma::ERP;
    }

    /**
     * Funcionalidades que da el plan: las base de su sistema más las opcionales que eligió
     * (modulos null = todas las opcionales). Catálogo en config suscripciones.funcionalidades / productos.
     *
     * @return array<int, string>
     */
    public function funcionalidades(): array
    {
        $p = $this->codigoProducto();
        $opcionales = self::opcionalesDe($p);

        return array_values(array_unique([...self::baseDe($p), ...($this->modulos === null ? $opcionales : array_intersect($opcionales, $this->modulos))]));
    }

    public function incluye(string $funcionalidad): bool
    {
        return in_array($funcionalidad, $this->funcionalidades(), true);
    }

    /** @return array<int, string> */
    public static function baseDe(string $producto): array
    {
        return config("suscripciones.productos.{$producto}.base", []);
    }

    /** @return array<int, string> */
    public static function opcionalesDe(string $producto): array
    {
        return config("suscripciones.productos.{$producto}.opcionales", []);
    }

    /** Todo lo que puede dar un sistema (base + opcionales) */
    public static function todasDe(string $producto): array
    {
        return array_values(array_unique([...self::baseDe($producto), ...self::opcionalesDe($producto)]));
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
