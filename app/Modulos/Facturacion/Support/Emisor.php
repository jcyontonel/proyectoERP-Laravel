<?php

namespace App\Modulos\Facturacion\Support;

use App\Modulos\Nucleo\Models\Empresa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * La empresa vista como emisor electrónico: datos fiscales, modo (beta / producción), usuario SOL
 * y certificado. Todo sale de la empresa (Configuración → SUNAT); no hay una tabla aparte.
 */
final class Emisor
{
    public const BETA = 'beta';
    public const PRODUCCION = 'produccion';

    /** @var array{pem: string, vence: Carbon}|false|null */
    private array|false|null $certificado = null;

    public function __construct(public readonly Empresa $empresa)
    {
    }

    public function ruc(): string
    {
        return (string) $this->empresa->ruc;
    }

    public function modo(): string
    {
        return $this->empresa->sunat_modo === self::PRODUCCION ? self::PRODUCCION : self::BETA;
    }

    public function enProduccion(): bool
    {
        return $this->modo() === self::PRODUCCION;
    }

    public function solUsuario(): ?string
    {
        return $this->empresa->sunat_usuario_sol;
    }

    public function solClave(): ?string
    {
        return $this->empresa->sunat_clave_sol;
    }

    /** PEM (clave privada + certificado) del .pfx de la empresa; null si no tiene o no se puede abrir */
    public function certificadoPem(): ?string
    {
        return $this->leerCertificado()['pem'] ?? null;
    }

    public function certificadoVence(): ?Carbon
    {
        return $this->leerCertificado()['vence'] ?? null;
    }

    /** Qué falta para emitir en producción (vacío = listo) */
    public function faltantesProduccion(): array
    {
        $vence = $this->certificadoVence();

        return array_values(array_filter([
            blank($this->solUsuario()) || blank($this->solClave()) ? 'usuario y clave SOL' : null,
            ! $this->certificadoPem() ? 'certificado digital' : null,
            $vence && $vence->lt(today()) ? 'certificado vigente (venció el ' . $vence->format('d/m/Y') . ')' : null,
            blank($this->empresa->direccion) || blank($this->empresa->ubigeo) ? 'dirección y ubigeo de la empresa' : null,
        ]));
    }

    private function leerCertificado(): array|false
    {
        if ($this->certificado !== null) {
            return $this->certificado;
        }

        $ruta = $this->empresa->certificado_path;
        if (! $ruta || ! Storage::disk('local')->exists($ruta)
            || ! openssl_pkcs12_read(Storage::disk('local')->get($ruta), $partes, (string) $this->empresa->certificado_clave)) {
            return $this->certificado = false;
        }

        $info = openssl_x509_parse($partes['cert']) ?: [];

        return $this->certificado = [
            'pem'   => $partes['pkey'] . $partes['cert'] . implode('', $partes['extracerts'] ?? []),
            'vence' => Carbon::createFromTimestamp($info['validTo_time_t'] ?? 0),
        ];
    }
}
