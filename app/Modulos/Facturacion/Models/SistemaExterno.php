<?php

namespace App\Modulos\Facturacion\Models;

use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Sistema de fuera de la plataforma (POS, tienda propia, otro ERP) que emite comprobantes de UNA empresa
 * por la API v1. Se autentica con su token (solo se guarda el hash) y recibe webhooks firmados.
 */
class SistemaExterno extends Model
{
    use EsDeEmpresa;

    protected $table = 'sistemas_externos';

    protected $fillable = ['empresa_id', 'nombre', 'token_hash', 'token_prefijo', 'webhook_url', 'webhook_secreto', 'activo', 'ultimo_uso_at'];

    protected $hidden = ['token_hash', 'webhook_secreto'];

    protected function casts(): array
    {
        return ['webhook_secreto' => 'encrypted', 'activo' => 'boolean', 'ultimo_uso_at' => 'datetime'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** Crea el sistema y devuelve [sistema, token en claro]: el token no se vuelve a mostrar */
    public static function registrar(Empresa $empresa, string $nombre, ?string $webhookUrl = null): array
    {
        $token = 'fct_' . Str::random(40);
        $sistema = self::create([
            'empresa_id'      => $empresa->id,
            'nombre'          => $nombre,
            'token_hash'      => hash('sha256', $token),
            'token_prefijo'   => substr($token, 0, 10),
            'webhook_url'     => $webhookUrl,
            'webhook_secreto' => 'whs_' . Str::random(40),
        ]);

        return [$sistema, $token];
    }

    public static function porToken(?string $token): ?self
    {
        return filled($token) ? self::where('token_hash', hash('sha256', $token))->where('activo', true)->first() : null;
    }
}
