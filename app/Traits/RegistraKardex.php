<?php

namespace App\Traits;

use App\Models\Kardex;
use App\Models\DetalleKardex;

trait RegistraKardex
{
    /**
     * Registra un movimiento en el Kardex y opcionalmente sus detalles.
     */
    public function registrarMovimientoKardex(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1️⃣ Crear el registro principal del Kardex
            $kardex = Kardex::create([
                'empresa_id' => $data['empresa_id'],
                'producto_id' => $data['producto_id'],
                'tipo_movimiento' => $data['tipo_movimiento'], // ingreso | egreso
                'motivo' => $data['motivo'] ?? null,
                'referencia_tipo' => $data['referencia_tipo'] ?? null,
                'referencia_id' => $data['referencia_id'] ?? null,
                'referencia_serie' => $data['referencia_serie'] ?? null,
                'referencia_numero' => $data['referencia_numero'] ?? null,
                'cantidad' => $data['cantidad'],
                'costo_unitario' => $data['costo_unitario'] ?? 0,
                'costo_total' => $data['cantidad'] * ($data['costo_unitario'] ?? 0),
                'saldo_cantidad' => $data['saldo_cantidad'] ?? null,
                'saldo_valorizado' => $data['saldo_valorizado'] ?? null,
                'observacion' => $data['observacion'] ?? null,
            ]);

            // 2️⃣ Si el movimiento tiene detalle de lote/serie/vencimiento
            if (!empty($data['detalles']) && is_array($data['detalles'])) {
                foreach ($data['detalles'] as $detalle) {
                    DetalleKardex::create([
                        'kardex_id' => $kardex->id,
                        'producto_id' => $data['producto_id'],
                        'cantidad' => $detalle['cantidad'],
                        'costo_unitario' => $detalle['costo_unitario'] ?? $data['costo_unitario'] ?? 0,
                        'total' => ($detalle['cantidad'] ?? 0) * ($detalle['costo_unitario'] ?? $data['costo_unitario'] ?? 0),
                        'lote' => $detalle['lote'] ?? null,
                        'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                    ]);
                }
            }
            return $kardex;
        });
        
    }
}
