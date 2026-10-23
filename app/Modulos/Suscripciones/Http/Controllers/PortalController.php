<?php

namespace App\Modulos\Suscripciones\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Nucleo\Support\EmpresaActual;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Suscripciones\Models\ProductoPlataforma;
use App\Modulos\Suscripciones\Services\SuscripcionService;
use Illuminate\Http\Request;

/**
 * Portal de suscripciones (contexto "suscripciones": /suscripciones, en producción subs.ricopet.com):
 * las suscripciones de TODAS las empresas que el usuario administra (ERP y Página web), con su titular,
 * vigencia y últimos pagos. «Mi suscripción» del ERP muestra solo la empresa actual.
 */
class PortalController extends Controller
{
    public function __invoke(Request $request, SuscripcionService $suscripciones)
    {
        $user = $request->user();

        return view('suscripciones.portal', [
            'empresas' => $this->administradas($user)->map(fn ($e) => (object) [
                'empresa' => $e,
                'actual'  => $e->id === EmpresaActual::id($user),
                'erp'     => $suscripciones->vigente($e->id, ProductoPlataforma::ERP),
                'web'     => $suscripciones->vigente($e->id, ProductoPlataforma::WEB),
                'pagos'   => PagoSuscripcion::query()->where('empresa_id', $e->id)->with('suscripcion.plan.producto', 'comprobante')
                    ->latest('id')->limit(5)->get(),
            ])->values(),
            'periodicidades' => config('suscripciones.periodicidades'),
        ]);
    }

    /** Empresas del usuario donde tiene el permiso de gestionarlas (los roles van por empresa) */
    private function administradas($user)
    {
        $equipo = getPermissionsTeamId();
        $empresas = EmpresaActual::disponibles($user)->filter(function ($e) use ($user) {
            setPermissionsTeamId($e->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $user->can('gestionar-empresas');
        });
        setPermissionsTeamId($equipo);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $empresas;
    }
}
