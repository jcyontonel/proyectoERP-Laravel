<?php

namespace App\Modulos\Plataforma\Http\Middleware;

use App\Modulos\Plataforma\Services\SuscripcionService;
use App\Modulos\Nucleo\Support\EmpresaActual;
use App\Modulos\Plataforma\Support\Modulos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Funcionalidades: una ruta cuya funcionalidad no da ninguna suscripción vigente de la empresa no se puede abrir
 * (config suscripciones.funcionalidades / productos; con ERP y WEB valen las de los dos).
 *
 * Suscripción suspendida o cancelada = modo solo lectura.
 *  - Se bloquea toda escritura (POST/PUT/PATCH/DELETE), los formularios de alta y edición (*.create, *.edit)
 *    y las consultas que registran datos (clientes.documento).
 *  - Se permite consultar, exportar, ver "Mi suscripción", el perfil propio, cambiar de empresa,
 *    cerrar sesión y cerrar un turno de caja ya abierto (para cuadrar el efectivo).
 * Durante los días de gracia (vencida) se opera normalmente, con aviso.
 */
class VerificarSuscripcion
{
    /** Rutas permitidas aun suspendida (Plataforma es la administración global: no depende de la empresa actual) */
    private const PERMITIDAS = [
        'logout', 'login', 'login.post', 'empresa.cambiar', 'perfil.*', 'config.suscripcion',
        'caja.cierre', 'caja.cerrar', 'plataforma.*',
    ];

    /** Consultas GET que además escriben */
    private const GET_QUE_ESCRIBEN = ['clientes.documento'];

    public function __construct(private SuscripcionService $suscripciones)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $empresaId = $user ? EmpresaActual::id($user) : null;

        // Funcionalidad que ningún plan incluye: no se entra ni por el menú ni por un enlace directo
        $modulo = $empresaId ? Modulos::deRuta($request->route()?->getName()) : null;
        if ($modulo && ! Modulos::incluido($modulo, $empresaId)) {
            $planes = $this->suscripciones->vigentes($empresaId)->map(fn ($s) => $s->plan?->nombre)->filter()->join(' y ');
            $mensaje = '«' . Modulos::nombre($modulo) . '» no está incluido en tu plan' . ($planes ? " {$planes}" : '') . '. Mejora tu plan para usarlo.';

            return $this->rechazar($request, $user, $mensaje);
        }

        if (! $empresaId || ! $this->restringida($request)) {
            return $next($request);
        }

        // Solo lectura si TODAS las suscripciones que dan esta funcionalidad están suspendidas o canceladas
        $s = Modulos::bloqueante($modulo, $empresaId);
        if (! $s) {
            return $next($request);
        }

        $mensaje = ($s->estadoEfectivo() === 'cancelada'
            ? 'La suscripción de la empresa fue cancelada.'
            : 'La suscripción de la empresa está suspendida' . ($s->motivo_suspension ? " ({$s->motivo_suspension})" : ' por falta de pago') . '.')
            . ' Puedes consultar tu información, pero no registrar ni modificar nada hasta regularizarla.';

        return $this->rechazar($request, $user, $mensaje);
    }

    /**
     * Página (GET) que se bloquea con la suscripción suspendida: formularios de alta y edición y
     * consultas que registran datos. El menú no muestra los ítems que llevan a ellas.
     */
    public static function bloqueadaSuspendida(string $ruta): bool
    {
        if (Str::is(self::PERMITIDAS, $ruta)) {
            return false;
        }

        return Str::endsWith($ruta, ['.create', '.edit']) || in_array($ruta, self::GET_QUE_ESCRIBEN, true);
    }

    private function rechazar(Request $request, $user, string $mensaje): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje], 403);
        }

        // Una página bloqueada no tiene "atrás" útil: se va a Mi suscripción (o al inicio)
        $destino = $request->isMethod('GET')
            ? ($user->can('gestionar-empresas') ? route('config.suscripcion') : route('inicio'))
            : url()->previous(route('inicio'));

        return redirect()->to($destino)->with('error', $mensaje);
    }

    private function restringida(Request $request): bool
    {
        $nombre = (string) $request->route()?->getName();

        if ($nombre !== '' && Str::is(self::PERMITIDAS, $nombre)) {
            return false;
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return true;
        }

        return self::bloqueadaSuspendida($nombre);
    }
}
