<?php

namespace App\Modulos\Suscripciones\Http\Controllers\Configuracion;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Suscripciones\Models\PagoSuscripcion;
use App\Modulos\Suscripciones\Models\Plan;
use App\Modulos\Suscripciones\Services\SuscripcionService;
use Illuminate\Http\Request;

/** Mi suscripción: plan, vigencia, consumo de los límites, pagos y planes disponibles (solo lectura) */
class SuscripcionController extends Controller
{
    use ResuelveEmpresa;

    public function __invoke(Request $request, SuscripcionService $suscripciones)
    {
        $empresaId = $this->empresaId($request);

        return view('configuracion.suscripcion', [
            'resumen' => $suscripciones->resumen($empresaId),
            'pagos'   => PagoSuscripcion::query()->where('empresa_id', $empresaId)->with('suscripcion.plan', 'comprobante')->latest('id')->limit(24)->get(),
            'planes'  => Plan::query()->activo()->with(['precios' => fn ($q) => $q->where('activo', true)])->orderBy('orden')->get(),
            'periodicidades' => config('suscripciones.periodicidades'),
            'metodos' => config('suscripciones.metodos_pago'),
        ]);
    }
}
