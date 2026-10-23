<?php

namespace App\Modulos\Web\Services;

use App\Modulos\Web\Models\Tienda;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Cobro con tarjeta por Culqi: el navegador tokeniza la tarjeta con Culqi Checkout (llave pública de la tienda)
 * y aquí se crea el cargo con la llave secreta de la tienda. La tarjeta nunca pasa por el servidor.
 * Modo prueba (pk_test_/sk_test_) o producción (pk_live_/sk_live_) según la tienda.
 */
class CulqiService
{
    /**
     * @return string id del cargo en Culqi
     *
     * @throws ValidationException  tarjeta rechazada, llaves no configuradas o Culqi sin conexión
     */
    public function cobrar(Tienda $tienda, string $token, float $monto, string $correo, string $descripcion, array $metadatos = []): string
    {
        $llaves = $tienda->culqi();
        if (! $llaves['secreta']) {
            throw ValidationException::withMessages(['pago' => 'El pago con tarjeta no está disponible en esta tienda.']);
        }

        $ca = config('services.culqi.ca_bundle');
        try {
            $r = Http::baseUrl(rtrim(config('services.culqi.url'), '/'))
                ->withToken($llaves['secreta'])
                ->acceptJson()
                ->timeout((int) config('services.culqi.timeout'))
                ->when($ca, fn ($h) => $h->withOptions(['verify' => preg_match('/^([A-Za-z]:|\/)/', $ca) ? $ca : base_path($ca)]))
                ->post('/charges', [
                    'amount'        => (int) round($monto * 100),   // céntimos
                    'currency_code' => 'PEN',
                    'email'         => $correo,
                    'source_id'     => $token,
                    'description'   => mb_substr($descripcion, 0, 80),
                    'metadata'      => $metadatos,
                ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['pago' => 'No se pudo conectar con Culqi. Tu tarjeta no fue cobrada: intenta de nuevo.']);
        }

        if ($r->successful() && filled($r->json('id'))) {
            return (string) $r->json('id');
        }

        // Mensaje para el comprador (Culqi lo da en español); nunca se registra la tarjeta
        $mensaje = $r->json('user_message') ?: $r->json('merchant_message') ?: 'El pago no fue aceptado.';
        throw ValidationException::withMessages(['pago' => $mensaje]);
    }
}
