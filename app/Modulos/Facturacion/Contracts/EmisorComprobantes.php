<?php

namespace App\Modulos\Facturacion\Contracts;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Facturacion\Models\SistemaExterno;
use App\Modulos\Facturacion\Sunat\RespuestaSunat;
use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Contrato interno de emisión electrónica. El ERP, las suscripciones y la API v1 emiten por aquí;
 * la implementación (hoy MotorFacturacion: Greenter + SUNAT directo) puede cambiarse por un OSE/PSE.
 *
 * $datos: tipo, serie, numero, fecha_emision, moneda, igv_porcentaje, precios_incluyen_igv,
 *         cliente{tipo_doc, numero, nombre, direccion?, email?}, items[], forma_pago?, observaciones?,
 *         afectado{tipo, serie, numero}? y motivo{codigo, descripcion}? (notas), referencia?, webhook_url?
 */
interface EmisorComprobantes
{
    /**
     * Guarda (o reemplaza, si aún no fue aceptado) el comprobante y firma su XML. No lo envía.
     *
     * @return array{0: Documento, 1: bool}  [documento, true si se creó o reemplazó]
     *
     * @throws ValidationException  datos que SUNAT rechazaría (montos, cliente, emisor sin configurar)
     */
    public function registrar(Empresa $empresa, array $datos, ?Model $origen = null, ?SistemaExterno $sistema = null): array;

    /** Envía según la configuración: en cola (no bloquea) o en el momento */
    public function programarEnvio(Documento $doc): Documento;

    /** Envía a SUNAT ahora y aplica la respuesta. $avisar: webhook al sistema externo si cambia el estado */
    public function enviar(Documento $doc, bool $avisar = true): RespuestaSunat;

    /**
     * Comunicación de baja (facturas) o resumen diario (boletas) de un comprobante aceptado.
     *
     * @throws ValidationException  no aceptado, fuera de plazo o con una baja en curso
     */
    public function anular(Documento $doc, string $motivo): Documento;

    /** Recupera el CDR (producción) por si la respuesta del envío se perdió */
    public function consultarCdr(Documento $doc): RespuestaSunat;

    /** Contenido del XML firmado o del CDR (zip); null si aún no existe */
    public function archivo(Documento $doc, string $cual): ?string;

    /**
     * El timer: reenvía pendientes, consulta tickets y entrega webhooks.
     *
     * @return array{enviados: int, aceptados: int, sin_conexion: bool, tickets: int, webhooks: int}
     */
    public function procesar(): array;
}
