<?php

namespace App\Modulos\Erp\Models;

use App\Modulos\Catalogo\Models\Producto;
use App\Modulos\Nucleo\Models\User;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historial de movimientos de inventario (ingreso / egreso / ajuste) */
class Kardex extends Model
{
    protected $table = 'kardex';

    protected $fillable = [
        'empresa_id', 'almacen_id', 'producto_id', 'lote_id', 'user_id',
        'referencia_tipo', 'referencia_id', 'referencia_serie', 'referencia_numero',
        'tipo_movimiento', 'cantidad', 'costo_unitario', 'costo_total',
        'stock_anterior', 'stock_nuevo', 'fecha_vencimiento', 'fecha_movimiento', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'          => 'float',
            'costo_unitario'    => 'float',
            'costo_total'       => 'float',
            'stock_anterior'    => 'float',
            'stock_nuevo'       => 'float',
            'fecha_vencimiento' => 'date',
            'fecha_movimiento'  => 'datetime',
        ];
    }

    /** "Venta B001-000129", "Compra F001-123", "Stock inicial"… */
    protected function referenciaLabel(): Attribute
    {
        return Attribute::get(function () {
            $tipo = match ($this->referencia_tipo) {
                'venta'             => 'Venta',
                'compra'            => 'Compra',
                'stock_inicial'     => 'Stock inicial',
                'anulacion_venta'   => 'Anulación de venta',
                'anulacion_compra'  => 'Anulación de compra',
                'nota_credito'      => 'Nota de crédito',
                'anulacion_nota'    => 'Anulación de nota',
                default             =>ucfirst(str_replace('_', ' ', (string) $this->referencia_tipo)) ?: 'Movimiento',
            };
            $doc = trim(($this->referencia_serie ? $this->referencia_serie . '-' : '') . $this->referencia_numero, '-');

            return trim($tipo . ' ' . $doc);
        });
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** URL del documento que originó el movimiento (si tiene pantalla) */
    public function referenciaUrl(): ?string
    {
        if (! $this->referencia_id) {
            return null;
        }

        return match ($this->referencia_tipo) {
            'venta', 'anulacion_venta'   => route('ventas.show', $this->referencia_id),
            'compra', 'anulacion_compra' => route('compras.show', $this->referencia_id),
            'ajuste'                     => route('ajustes.show', $this->referencia_id),
            'transferencia'              => route('transferencias.show', $this->referencia_id),
            'fraccionamiento'            => route('fraccionamientos.show', $this->referencia_id),
            'stock_inicial'              => route('productos.show', $this->producto_id),
            'nota_credito', 'anulacion_nota' => route('notas.show', $this->referencia_id),
            default                      => null,
        };
    }
}