<?php

namespace App\Modulos\Nucleo\Services\Documentos;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Consulta de DNI (RENIEC) y RUC (SUNAT) en https://apiperu.dev.
 * El token vive en .env (APIPERU_TOKEN) y nunca llega al navegador.
 * Las respuestas exitosas se guardan en caché para no gastar la cuota del plan.
 */
class ApiPeruService
{
    /**
     * @return array{tipo_documento_codigo: string, numero: string, nombre: string, apellido: ?string,
     *               direccion: ?string, estado: ?string, condicion: ?string}|null  null = no encontrado
     *
     * @throws RuntimeException si el servicio no está configurado o no responde
     */
    public function consultar(string $numero): ?array
    {
        $numero = preg_replace('/\D/', '', $numero);
        $tipo = match (strlen($numero)) {
            8       => 'dni',
            11      => 'ruc',
            default => throw new RuntimeException('El documento debe tener 8 (DNI) u 11 (RUC) dígitos.'),
        };

        $clave = "apiperu:{$tipo}:{$numero}";
        if (Cache::has($clave)) {
            return Cache::get($clave);
        }

        $datos = $this->solicitar($tipo, $numero);

        if ($datos) {
            Cache::put($clave, $datos, now()->addDays((int) config('services.apiperu.cache_dias', 30)));
        }

        return $datos;
    }

    private function solicitar(string $tipo, string $numero): ?array
    {
        $token = config('services.apiperu.token');
        if (blank($token)) {
            throw new RuntimeException('La consulta a RENIEC/SUNAT no está configurada (falta APIPERU_TOKEN en .env).');
        }

        // Ruta relativa al proyecto o absoluta; sin valor se usan los certificados de PHP
        $ca = config('services.apiperu.ca_bundle');
        $opciones = $ca ? ['verify' => str_starts_with($ca, '/') || preg_match('/^[A-Za-z]:/', $ca) ? $ca : base_path($ca)] : [];

        try {
            $respuesta = Http::baseUrl(rtrim((string) config('services.apiperu.url'), '/'))
                ->withOptions($opciones)
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.apiperu.timeout', 8))
                ->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->post("/{$tipo}", [$tipo => $numero]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo conectar con el servicio de RENIEC/SUNAT. Intenta de nuevo.', previous: $e);
        }

        if (in_array($respuesta->status(), [401, 403], true)) {
            Log::warning('APIPeru rechazó el token', ['status' => $respuesta->status()]);
            throw new RuntimeException('El servicio de RENIEC/SUNAT rechazó el token configurado.');
        }
        if ($respuesta->status() === 429) {
            throw new RuntimeException('Se alcanzó el límite de consultas del plan de RENIEC/SUNAT. Intenta más tarde.');
        }
        if ($respuesta->serverError()) {
            throw new RuntimeException('El servicio de RENIEC/SUNAT no está disponible en este momento.');
        }

        $data = $respuesta->json('data');
        if (! $respuesta->successful() || ! $respuesta->json('success', true) || empty($data)) {
            return null;   // documento no encontrado
        }

        return $tipo === 'dni' ? $this->dni($numero, $data) : $this->ruc($numero, $data);
    }

    private function dni(string $numero, array $d): array
    {
        $apellidos = trim(($d['apellido_paterno'] ?? '') . ' ' . ($d['apellido_materno'] ?? ''));
        $nombres   = trim((string) ($d['nombres'] ?? ''));

        return [
            'tipo_documento_codigo' => '1',
            'numero'    => (string) ($d['numero'] ?? $numero),
            'nombre'    => $this->titulo($nombres !== '' ? $nombres : (string) ($d['nombre_completo'] ?? '')),
            'apellido'  => $apellidos !== '' ? $this->titulo($apellidos) : null,
            'direccion' => $this->texto($d['direccion_completa'] ?? $d['direccion'] ?? null),
            'estado'    => null,
            'condicion' => null,
        ];
    }

    private function ruc(string $numero, array $d): array
    {
        return [
            'tipo_documento_codigo' => '6',
            'numero'    => (string) ($d['ruc'] ?? $numero),
            'nombre'    => trim((string) ($d['nombre_o_razon_social'] ?? $d['razon_social'] ?? $d['nombre'] ?? '')),
            'apellido'  => null,
            'direccion' => $this->texto($d['direccion_completa'] ?? $d['direccion'] ?? null),
            'estado'    => $d['estado'] ?? null,     // ACTIVO, BAJA DE OFICIO…
            'condicion' => $d['condicion'] ?? null,  // HABIDO, NO HABIDO…
        ];
    }

    /** "VALERIA MENDOZA" → "Valeria Mendoza" */
    private function titulo(string $texto): string
    {
        return mb_convert_case(mb_strtolower(trim($texto)), MB_CASE_TITLE, 'UTF-8');
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor, " -\t\n");

        return $valor !== '' ? $valor : null;
    }
}
