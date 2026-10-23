<?php

namespace App\Modulos\Suscripciones\Models;

use App\Modulos\Nucleo\Models\Concerns\EsActivable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Lo que vende la plataforma: ERP, Página web (y más adelante restaurante, canchas…). Cada uno con sus planes */
class ProductoPlataforma extends Model
{
    use EsActivable;

    public const ERP = 'erp';
    public const WEB = 'web';

    protected $table = 'productos_plataforma';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activo' => 'boolean'];
    }

    public function planes(): HasMany
    {
        return $this->hasMany(Plan::class, 'producto_id')->orderBy('orden');
    }

    public static function idDe(string $codigo): int
    {
        return (int) self::where('codigo', $codigo)->valueOrFail('id');
    }
}
