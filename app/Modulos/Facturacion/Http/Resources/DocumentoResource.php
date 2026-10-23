<?php

namespace App\Modulos\Facturacion\Http\Resources;

use App\Modulos\Facturacion\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Comprobante en la API v1 (misma forma que tenía el facturador separado, para no romper a sus clientes).
 *
 * @mixin Documento
 */
class DocumentoResource extends JsonResource
{
    /** Sin envoltorio "data" (como respondía el facturador); los listados paginados sí lo llevan */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('empresa');

        return [
            'id'              => $this->uuid,
            'emisor'          => $this->empresa->ruc,
            'tipo'            => $this->tipo,
            'tipo_nombre'     => Documento::TIPOS[$this->tipo] ?? $this->tipo,
            'serie'           => $this->serie,
            'numero'          => (int) $this->numero,
            'numero_completo' => $this->numeroCompleto(),
            'fecha_emision'   => $this->fecha_emision?->toIso8601String(),
            'moneda'          => $this->moneda,
            'total'           => $this->total,
            'totales'         => $this->datos['totales'] ?? null,
            'referencia'      => $this->referencia,
            'modo'            => $this->emisor()->modo(),
            'estado'          => $this->estado,
            'aceptado'        => $this->fueAceptado(),
            'sunat'           => [
                'codigo'        => $this->sunat_codigo,
                'descripcion'   => $this->sunat_descripcion,
                'observaciones' => $this->sunat_observaciones ?? [],
                'ticket'        => $this->ticket,
            ],
            'hash'            => $this->hash,
            'nombre_archivo'  => $this->nombre_archivo,
            'envio'           => [
                'intentos'        => $this->intentos,
                'proximo_intento' => $this->proximo_intento_at?->toIso8601String(),
                'ultimo_error'    => $this->ultimo_error,
                'enviado_at'      => $this->enviado_at?->toIso8601String(),
                'respondido_at'   => $this->respondido_at?->toIso8601String(),
            ],
            'baja'            => $this->when($this->esComprobante(), fn () => $this->baja ? [
                'id' => $this->baja->uuid, 'tipo' => $this->baja->tipo, 'numero' => $this->baja->numeroCompleto(),
                'estado' => $this->baja->estado, 'ticket' => $this->baja->ticket,
                'codigo' => $this->baja->sunat_codigo, 'descripcion' => $this->baja->sunat_descripcion ?? $this->baja->ultimo_error,
            ] : null),
            'enlaces'         => [
                'self' => route('api.v1.comprobantes.show', $this->uuid),
                'xml'  => $this->xml_path ? route('api.v1.comprobantes.xml', $this->uuid) : null,
                'cdr'  => $this->cdr_path ? route('api.v1.comprobantes.cdr', $this->uuid) : null,
            ],
            'creado_at'       => $this->created_at?->toIso8601String(),
            'actualizado_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
