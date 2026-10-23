<?php

namespace App\Modulos\Web\Models;

use App\Modulos\Erp\Models\Venta;
use App\Modulos\Nucleo\Models\Concerns\EsDeEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pedido hecho en la tienda en línea. Se atiende en el ERP y se convierte en venta (comprobante) */
class PedidoWeb extends Model
{
    use EsDeEmpresa;

    public const NUEVO = 'nuevo';
    public const CONFIRMADO = 'confirmado';
    public const ENVIADO = 'enviado';
    public const ENTREGADO = 'entregado';
    public const CANCELADO = 'cancelado';

    /** estado => [etiqueta, tono] */
    public const ESTADOS = [
        self::NUEVO      => ['Nuevo', 'warning'],
        self::CONFIRMADO => ['Confirmado', 'info'],
        self::ENVIADO    => ['En camino', 'info'],
        self::ENTREGADO  => ['Entregado', 'success'],
        self::CANCELADO  => ['Cancelado', 'neutral'],
    ];

    /** A dónde puede pasar desde cada estado */
    public const FLUJO = [
        self::NUEVO      => [self::CONFIRMADO, self::CANCELADO],
        self::CONFIRMADO => [self::ENVIADO, self::ENTREGADO, self::CANCELADO],
        self::ENVIADO    => [self::ENTREGADO, self::CANCELADO],
        self::ENTREGADO  => [],
        self::CANCELADO  => [],
    ];

    protected $table = 'pedidos_web';

    protected $fillable = [
        'empresa_id', 'numero', 'estado', 'cliente_nombre', 'cliente_tipo_doc', 'cliente_documento', 'cliente_telefono', 'cliente_correo',
        'entrega', 'zona_envio_id', 'direccion', 'referencia', 'subtotal', 'costo_envio', 'total', 'metodo_pago', 'pago_estado',
        'culqi_cargo_id', 'notas', 'venta_id',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'float', 'costo_envio' => 'float', 'total' => 'float', 'numero' => 'integer'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedidoWeb::class, 'pedido_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaEnvio::class, 'zona_envio_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function codigo(): string
    {
        return 'W-' . str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function etiqueta(): string
    {
        return self::ESTADOS[$this->estado][0] ?? $this->estado;
    }

    public function tono(): string
    {
        return self::ESTADOS[$this->estado][1] ?? 'neutral';
    }

    public function puedePasarA(string $estado): bool
    {
        return in_array($estado, self::FLUJO[$this->estado] ?? [], true);
    }

    /** Se puede convertir en venta: no cancelado y aún sin comprobante */
    public function esConvertible(): bool
    {
        return $this->estado !== self::CANCELADO && ! $this->venta_id;
    }
}
