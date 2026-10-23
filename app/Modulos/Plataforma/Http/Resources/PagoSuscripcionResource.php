<?php

namespace App\Modulos\Plataforma\Http\Resources;

use App\Modulos\Facturacion\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modulos\Suscripciones\Models\PagoSuscripcion */
class PagoSuscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'empresa_id'     => $this->empresa_id,
            'empresa'        => $this->whenLoaded('empresa', fn () => $this->empresa->nombre_comercial ?: $this->empresa->razon_social),
            'suscripcion_id' => $this->suscripcion_id,
            'plan'           => $this->whenLoaded('suscripcion', fn () => $this->suscripcion->plan?->nombre),
            'periodicidad'   => $this->whenLoaded('suscripcion', fn () => $this->suscripcion->periodicidad),
            'oferta'         => $this->whenLoaded('oferta', fn () => $this->oferta?->nombre),
            'periodo_desde'  => $this->periodo_desde?->toDateString(),
            'periodo_hasta'  => $this->periodo_hasta?->toDateString(),
            'meses'          => $this->meses,
            'monto_bruto'    => $this->monto_bruto,
            'descuento'      => $this->descuento,
            'monto'          => $this->monto,
            'estado'         => $this->estado,
            'metodo'         => $this->metodo,
            'referencia'     => $this->referencia,
            'fecha_pago'     => $this->fecha_pago?->toIso8601String(),
            'registrado_por' => $this->registrado_por,
            'notas'          => $this->notas,
            'creado'         => $this->created_at?->toIso8601String(),
            // Factura/boleta electrónica del pago y, si se anuló fuera de plazo, su nota de crédito
            'comprobante'    => $this->whenLoaded('comprobante', fn () => self::comprobante($this->comprobante)),
            'nota_credito'   => $this->whenLoaded('notaCredito', fn () => self::comprobante($this->notaCredito)),
        ];
    }

    public static function comprobante(?Documento $c): ?array
    {
        return $c ? [
            'tipo'          => $c->tipo,
            'tipo_nombre'   => ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito'][$c->tipo] ?? $c->tipo,
            'numero'        => $c->numeroCompleto(),
            'estado'        => $c->estado,
            'estado_nombre' => $c->etiqueta(),
            'tono'          => $c->tono(),
            'aceptado'      => $c->fueAceptado(),
            'codigo'        => $c->sunat_codigo,
            'mensaje'       => $c->mensaje(),
            'observaciones' => $c->sunat_observaciones ?? [],
            'hash'          => $c->hash,
            'baja_estado'   => $c->baja_estado,
            'reenviable'    => $c->esReenviable(),
            'tiene_archivos' => $c->tieneArchivos(),
            'intentos'      => $c->intentos,
            'emitido'       => $c->created_at?->toIso8601String(),
        ] : null;
    }
}
