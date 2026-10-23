<?php

namespace App\Modulos\Facturacion\Sunat;

use App\Modulos\Facturacion\Models\Documento;
use App\Modulos\Nucleo\Models\Empresa;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Sale\Charge;
use Greenter\Model\Sale\Cuota;
use Greenter\Model\Sale\Document;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\FormaPagos\FormaPagoCredito;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\Note;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Summary\Summary;
use Greenter\Model\Summary\SummaryDetail;
use Greenter\Model\Voided\Voided;
use Greenter\Model\Voided\VoidedDetail;
use Luecano\NumeroALetras\NumeroALetras;

/** Arma el modelo de Greenter (UBL 2.1) a partir del documento guardado */
class ConstructorDocumento
{
    private const MONEDAS = ['PEN' => 'SOLES', 'USD' => 'DÓLARES AMERICANOS'];

    public function construir(Documento $doc): DocumentInterface
    {
        $doc->loadMissing('empresa', 'afectado');

        return match ($doc->tipo) {
            Documento::FACTURA, Documento::BOLETA => $this->comprobante(new Invoice(), $doc),
            Documento::NOTA_CREDITO, Documento::NOTA_DEBITO => $this->comprobante(new Note(), $doc),
            Documento::BAJA => $this->baja($doc),
            Documento::RESUMEN => $this->resumen($doc),
        };
    }

    public function empresa(Empresa $e): Company
    {
        return (new Company())
            ->setRuc($e->ruc)
            ->setRazonSocial($e->razon_social)
            ->setNombreComercial($e->nombre_comercial ?: $e->razon_social)
            ->setEmail($e->email)
            ->setTelephone($e->telefono)
            ->setAddress((new Address())
                ->setUbigueo($e->ubigeo ?: '150101')
                ->setDepartamento($e->departamento ?: '-')
                ->setProvincia($e->provincia ?: '-')
                ->setDistrito($e->distrito ?: '-')
                ->setUrbanizacion('-')
                ->setDireccion($e->direccion ?: '-')
                ->setCodigoPais('PE')
                ->setCodLocal('0000'));   // código de establecimiento anexo (SUNAT): el domicilio fiscal
    }

    private function comprobante(Invoice|Note $c, Documento $doc): Invoice|Note
    {
        $d = $doc->datos;
        $t = $d['totales'];

        $c->setUblVersion('2.1')
            ->setTipoDoc($doc->tipo)
            ->setSerie($doc->serie)
            ->setCorrelativo((string) $doc->numero)
            ->setFechaEmision($this->fecha($doc->fecha_emision))
            ->setTipoMoneda($doc->moneda)
            ->setCompany($this->empresa($doc->empresa))
            ->setClient((new Client())
                ->setTipoDoc($d['cliente']['tipo_doc'])
                ->setNumDoc($d['cliente']['numero'])
                ->setRznSocial($d['cliente']['nombre'])
                ->setEmail($d['cliente']['email'] ?? null)
                ->setAddress(filled($d['cliente']['direccion'] ?? null) ? (new Address())->setDireccion($d['cliente']['direccion']) : null))
            ->setMtoOperGravadas($t['gravadas'])
            ->setMtoOperExoneradas($t['exoneradas'])
            ->setMtoOperInafectas($t['inafectas'])
            ->setMtoOperExportacion($t['exportacion'])
            ->setMtoIGV($t['igv'])
            ->setTotalImpuestos($t['igv'])
            ->setValorVenta($t['valor_venta'])
            ->setSubTotal($t['total'])
            ->setMtoImpVenta($t['total'])
            ->setDetails(array_map(fn ($l) => $this->linea($l, (float) $d['igv_porcentaje']), $d['items']))
            ->setLegends([(new Legend())->setCode('1000')->setValue(
                (new NumeroALetras())->toInvoice($t['total'], 2, self::MONEDAS[$doc->moneda] ?? $doc->moneda))]);

        if ($c instanceof Invoice) {
            $c->setTipoOperacion($t['exportacion'] > 0 ? '0200' : '0101');
            $this->formaPago($c, $d);
            if (filled($d['observaciones'] ?? null)) {
                $c->setObservacion($d['observaciones']);
            }
        } else {
            $c->setCodMotivo($d['motivo']['codigo'])
                ->setDesMotivo($d['motivo']['descripcion'])
                ->setTipDocAfectado($d['afectado']['tipo'])
                ->setNumDocfectado($d['afectado']['serie'] . '-' . $d['afectado']['numero']);
            if (($d['forma_pago']['tipo'] ?? null) === 'credito') {   // NC motivo 13: ajuste de cuotas
                $this->formaPago($c, $d);
            }
        }

        return $c;
    }

    private function linea(array $l, float $igvPct): SaleDetail
    {
        $gravado = $l['afectacion'] === Calculadora::GRAVADO;
        $det = (new SaleDetail())
            ->setCodProducto($l['codigo'])
            ->setUnidad($l['unidad'])
            ->setCantidad($l['cantidad'])
            ->setDescripcion($l['descripcion'])
            ->setMtoValorUnitario($l['valor_unitario'])
            ->setMtoBaseIgv($l['valor_venta'])
            ->setPorcentajeIgv($gravado ? $igvPct : 0)
            ->setIgv($l['igv'])
            ->setTipAfeIgv($l['afectacion'])
            ->setTotalImpuestos($l['igv'])
            ->setMtoValorVenta($l['valor_venta'])
            ->setMtoPrecioUnitario($l['precio_unitario']);

        if ($l['descuento'] > 0) {
            $det->setDescuentos([(new Charge())
                ->setCodTipo('00')   // descuento que afecta la base imponible
                ->setMontoBase($l['valor_bruto'])
                ->setFactor(round($l['descuento'] / $l['valor_bruto'], 5))
                ->setMonto($l['descuento'])]);
        }

        return $det;
    }

    private function formaPago(Invoice|Note $c, array $d): void
    {
        $fp = $d['forma_pago'] ?? ['tipo' => 'contado'];
        if ($fp['tipo'] !== 'credito') {
            $c->setFormaPago(new FormaPagoContado());

            return;
        }

        $cuotas = $fp['cuotas'];
        $c->setFormaPago(new FormaPagoCredito(round(array_sum(array_column($cuotas, 'monto')), 2), $c->getTipoMoneda()))
            ->setCuotas(array_map(fn ($q) => (new Cuota())
                ->setMoneda($c->getTipoMoneda())
                ->setMonto((float) $q['monto'])
                ->setFechaPago($this->fecha($q['fecha'])), $cuotas));
        if ($c instanceof Invoice) {
            $c->setFecVencimiento($this->fecha(end($cuotas)['fecha']));
        }
    }

    /** RA: comunicación de baja de una factura (o nota de factura) */
    private function baja(Documento $doc): Voided
    {
        $a = $doc->afectado;

        return (new Voided())
            ->setCorrelativo((string) $doc->numero)
            ->setFecGeneracion($this->fecha($a->fecha_emision))
            ->setFecComunicacion($this->fecha($doc->fecha_emision))
            ->setCompany($this->empresa($doc->empresa))
            ->setDetails([(new VoidedDetail())
                ->setTipoDoc($a->tipo)
                ->setSerie($a->serie)
                ->setCorrelativo((string) $a->numero)
                ->setDesMotivoBaja($doc->datos['motivo'])]);
    }

    /** RC: resumen diario con estado 3 (anulación) de una boleta o nota de boleta */
    private function resumen(Documento $doc): Summary
    {
        $a = $doc->afectado;
        $t = $a->datos['totales'];
        $det = (new SummaryDetail())
            ->setTipoDoc($a->tipo)
            ->setSerieNro($a->numeroCompleto())
            ->setEstado('3')
            ->setClienteTipo($a->cliente_tipo_doc)
            ->setClienteNro($a->cliente_num_doc)
            ->setTotal($t['total'])
            ->setMtoOperGravadas($t['gravadas'])
            ->setMtoOperExoneradas($t['exoneradas'])
            ->setMtoOperInafectas($t['inafectas'])
            ->setMtoOperExportacion($t['exportacion'])
            ->setMtoIGV($t['igv'])
            ->setPorcentajeIgv((float) ($a->datos['igv_porcentaje'] ?? config('facturacion.igv')));   // sin la tasa SUNAT rechaza (2992)
        if (! in_array($a->tipo, [Documento::FACTURA, Documento::BOLETA], true)) {
            $det->setDocReferencia((new Document())->setTipoDoc($a->datos['afectado']['tipo'])->setNroDoc($a->datos['afectado']['serie'] . '-' . $a->datos['afectado']['numero']));
        }

        return (new Summary())
            ->setCorrelativo((string) $doc->numero)
            ->setFecGeneracion($this->fecha($a->fecha_emision))
            ->setFecResumen($this->fecha($doc->fecha_emision))
            ->setMoneda($a->moneda)
            ->setCompany($this->empresa($doc->empresa))
            ->setDetails([$det]);
    }

    private function fecha(mixed $f): DateTime
    {
        return new DateTime(is_string($f) ? $f : $f->format('Y-m-d H:i:s'), new DateTimeZone('America/Lima'));
    }
}
