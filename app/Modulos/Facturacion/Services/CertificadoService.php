<?php

namespace App\Modulos\Facturacion\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Certificado digital de la empresa (.pfx / .p12) para firmar los comprobantes electrónicos.
 * Se valida al subirlo: la contraseña abre el archivo, contiene la clave privada y está vigente.
 */
class CertificadoService
{
    /**
     * @return array{titular: string, ruc: ?string, emisor: string, desde: Carbon, hasta: Carbon, vigente: bool, dias: int}
     *
     * @throws ValidationException
     */
    public function leer(string $contenido, string $clave): array
    {
        $certs = [];
        while (openssl_error_string() !== false) {
            // limpia errores previos de OpenSSL
        }

        if (! openssl_pkcs12_read($contenido, $certs, $clave)) {
            $error = (string) openssl_error_string();
            throw ValidationException::withMessages(['certificado' => str_contains(strtolower($error), 'unsupported')
                ? 'El certificado usa un cifrado antiguo que este servidor no abre. Conviértelo con: openssl pkcs12 -legacy -in cert.pfx -nodes -out tmp.pem && openssl pkcs12 -export -in tmp.pem -out nuevo.pfx'
                : 'No se pudo abrir el certificado: la contraseña es incorrecta o el archivo no es un .pfx/.p12 válido.']);
        }

        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw ValidationException::withMessages(['certificado' => 'El archivo no contiene la clave privada: exporta el certificado incluyendo la clave privada.']);
        }

        $info = openssl_x509_parse($certs['cert']) ?: [];
        $sujeto = $info['subject'] ?? [];
        $desde = Carbon::createFromTimestamp($info['validFrom_time_t'] ?? 0);
        $hasta = Carbon::createFromTimestamp($info['validTo_time_t'] ?? 0);

        // El RUC suele venir en serialNumber, en el CN o en la OU
        $texto = implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $sujeto));
        preg_match('/\b(10|15|17|20)\d{9}\b/', $texto, $ruc);

        return [
            'titular' => (string) ($sujeto['CN'] ?? $sujeto['O'] ?? 'Sin nombre'),
            'ruc'     => $ruc[0] ?? null,
            'emisor'  => (string) ($info['issuer']['O'] ?? $info['issuer']['CN'] ?? '—'),
            'desde'   => $desde,
            'hasta'   => $hasta,
            'vigente' => now()->between($desde, $hasta),
            'dias'    => (int) now()->diffInDays($hasta, false),
        ];
    }
}
