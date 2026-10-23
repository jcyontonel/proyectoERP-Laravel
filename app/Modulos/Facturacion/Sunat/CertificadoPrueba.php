<?php

namespace App\Modulos\Facturacion\Sunat;

use Illuminate\Support\Facades\Storage;

/** Certificado autofirmado que acepta SUNAT beta. Se genera una vez y se guarda en storage/app/private */
class CertificadoPrueba
{
    private const RUTA = 'certificados/prueba-beta.pem';

    /** Generar la clave RSA es lento: se reutiliza en el mismo proceso (colas, pruebas) */
    private static ?string $enMemoria = null;

    public function pem(): string
    {
        if (Storage::disk('local')->exists(self::RUTA)) {
            return Storage::disk('local')->get(self::RUTA);
        }
        if (self::$enMemoria) {
            Storage::disk('local')->put(self::RUTA, self::$enMemoria);

            return self::$enMemoria;
        }

        $cfg = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + $this->configOpenssl();
        $clave = openssl_pkey_new($cfg);
        $csr = openssl_csr_new(['C' => 'PE', 'O' => 'CERTIFICADO DE PRUEBA', 'CN' => 'FACTURADOR BETA'], $clave, $cfg);
        $x509 = openssl_csr_sign($csr, null, $clave, 3650, $cfg);
        openssl_x509_export($x509, $certPem);
        openssl_pkey_export($clave, $clavePem, null, $cfg);

        Storage::disk('local')->put(self::RUTA, self::$enMemoria = $clavePem . $certPem);

        return self::$enMemoria;
    }

    /** En Windows (XAMPP) OpenSSL necesita la ruta de openssl.cnf para generar claves */
    private function configOpenssl(): array
    {
        foreach ([getenv('OPENSSL_CONF') ?: null, dirname(PHP_BINARY) . '/extras/openssl/openssl.cnf', 'C:/xampp/php/extras/openssl/openssl.cnf'] as $cnf) {
            if ($cnf && is_file($cnf)) {
                return ['config' => $cnf];
            }
        }

        return [];
    }
}
