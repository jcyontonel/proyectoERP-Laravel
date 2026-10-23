<?php

namespace App\Modulos\Plataforma\Support;

use App\Modulos\Plataforma\Http\Middleware\TokenAdministracion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Las pantallas de Plataforma usan la API de administración (/api/admin) DENTRO de la aplicación, sin HTTP:
 * así la lógica vive en un solo lugar (la API, que sigue disponible para clientes externos con su token) y el
 * panel trabaja con las mismas respuestas que tenía el proyecto admin separado (antes: ErpApi por HTTP).
 *  - La petición interna lleva una marca que solo el servidor puede poner (TokenAdministracion la acepta).
 *  - 422 → ValidationException (los formularios muestran los errores); 404 → 404.
 */
class ApiAdmin
{
    public function get(string $ruta, array $query = []): array
    {
        return $this->datos($this->llamar('GET', $ruta, $query));
    }

    public function post(string $ruta, array $datos = []): array
    {
        return $this->datos($this->llamar('POST', $ruta, $datos));
    }

    public function put(string $ruta, array $datos = []): array
    {
        return $this->datos($this->llamar('PUT', $ruta, $datos));
    }

    public function delete(string $ruta): array
    {
        return $this->datos($this->llamar('DELETE', $ruta));
    }

    /** Respuesta sin procesar (HTML del comprobante impreso, XML, CDR zip) para reenviarla al navegador */
    public function archivo(string $ruta): RespuestaInterna
    {
        return new RespuestaInterna($this->llamar('GET', $ruta, [], '*/*'));
    }

    /** Catálogos (periodicidades, métodos de pago, roles, planes), una vez por petición */
    public function catalogos(): array
    {
        return once(fn () => $this->get('catalogos')['data']);
    }

    /** Respuesta paginada (data + meta) → paginador de Laravel con los filtros actuales */
    public function paginar(array $respuesta): LengthAwarePaginator
    {
        $meta = $respuesta['meta'] ?? [];

        return (new LengthAwarePaginator($respuesta['data'] ?? [], $meta['total'] ?? 0, $meta['per_page'] ?? 25, $meta['current_page'] ?? 1))
            ->withPath(request()->url())->withQueryString();
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function llamar(string $metodo, string $ruta, array $datos = [], string $accept = 'application/json'): Response
    {
        $original = app('request');
        $uri = $this->base() . ltrim($ruta, '/');
        $sinCuerpo = $metodo === 'GET';

        // Sin cookies ni referer: la petición interna no toca la sesión del usuario.
        // SCRIPT_NAME/SCRIPT_FILENAME de la petición real: si la app vive en una subcarpeta
        // (localhost/proyectoERP-Laravel/public) el router debe ver /api/admin/…, no la ruta completa.
        $interna = Request::create($uri, $metodo, $sinCuerpo ? $datos : [], [], [], [
            'HTTP_ACCEPT' => $accept, 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $original->ip(),
            'SCRIPT_NAME' => $original->server->get('SCRIPT_NAME', ''), 'SCRIPT_FILENAME' => $original->server->get('SCRIPT_FILENAME', ''),
        ], $sinCuerpo ? null : json_encode($datos));
        $interna->headers->set('X-Admin-Usuario', (string) auth()->user()?->name);   // queda en el pago (registrado_por)
        $interna->attributes->set(TokenAdministracion::INTERNA, true);

        try {
            // Los controladores de la API reciben esta petición (el router no la registra en el contenedor)
            app()->instance('request', $interna);
            Facade::clearResolvedInstance('request');
            $respuesta = app('router')->dispatch($interna);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 422) {
                throw ValidationException::withMessages(['general' => [$e->getMessage() ?: 'Los datos no son válidos.']]);
            }
            throw $e;
        } finally {
            app()->instance('request', $original);
            Facade::clearResolvedInstance('request');
        }

        if ($respuesta->getStatusCode() === 404) {
            abort(404, $this->json($respuesta)['message'] ?? 'No encontrado.');
        }
        if ($respuesta->getStatusCode() === 422) {
            $j = $this->json($respuesta);
            throw ValidationException::withMessages($j['errors'] ?? ['general' => [$j['message'] ?? 'Los datos no son válidos.']]);
        }
        if ($respuesta->getStatusCode() >= 400) {
            abort($respuesta->getStatusCode(), $this->json($respuesta)['message'] ?? 'Error de la API de administración.');
        }

        return $respuesta;
    }

    private function datos(Response $r): array
    {
        return $r->getStatusCode() === 204 ? [] : $this->json($r);
    }

    private function json(Response $r): array
    {
        return json_decode((string) $r->getContent(), true) ?: [];
    }

    /** URL base de la API de administración según el contexto "api" (dominio o prefijo, config/modulos.php) */
    private function base(): string
    {
        $c = config('modulos.contextos.api', []);
        $raiz = filled($c['dominio'] ?? null) ? request()->getScheme() . '://' . $c['dominio'] : url('/');

        return rtrim($raiz, '/') . '/' . trim((string) ($c['prefijo'] ?? 'api'), '/') . '/admin/';
    }
}
