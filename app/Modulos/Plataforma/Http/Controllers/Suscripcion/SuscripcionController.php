<?php

namespace App\Modulos\Plataforma\Http\Controllers\Suscripcion;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Plataforma\Models\PagoSuscripcion;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Plataforma\Models\ProductoPlataforma;
use App\Modulos\Plataforma\Services\SuscripcionService;
use Illuminate\Http\Request;

/** Mi suscripción: plan del ERP, vigencia, consumo de los límites, pagos, página web y planes disponibles (solo lectura) */
class SuscripcionController extends Controller
{
    use ResuelveEmpresa;

    public function __invoke(Request $request, SuscripcionService $suscripciones)
    {
        $empresaId = $this->empresaId($request);

        return view('plataforma::suscripciones.mi-suscripcion', [
            'resumen' => $suscripciones->resumen($empresaId),
            'pagos'   => PagoSuscripcion::query()->where('empresa_id', $empresaId)->with('suscripcion.plan', 'comprobante')->latest('id')->limit(24)->get(),
            'planes'  => $this->planes(ProductoPlataforma::ERP),
            'web'     => $suscripciones->vigente($empresaId, ProductoPlataforma::WEB),
            'planesWeb' => $this->planes(ProductoPlataforma::WEB),
            'periodicidades' => config('suscripciones.periodicidades'),
            'metodos' => config('suscripciones.metodos_pago'),
        ]);
    }

    private function planes(string $producto)
    {
        return Plan::query()->activo()->whereRelation('producto', 'codigo', $producto)
            ->with(['precios' => fn ($q) => $q->where('activo', true)])->orderBy('orden')->get();
    }
}
