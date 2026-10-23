<?php

namespace App\Modulos\Plataforma\Http\Controllers\Api;

use App\Modulos\Plataforma\Http\Resources\EmpresaResource;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Plataforma\Models\PagoSuscripcion;
use App\Modulos\Plataforma\Models\Plan;
use App\Modulos\Plataforma\Models\Suscripcion;
use Spatie\Permission\Models\Role;

/** Panel del proyecto admin: empresas por estado, ingresos, MRR y vencimientos próximos; y catálogos */
class ResumenController extends AdminController
{
    public function __invoke()
    {
        $vigentes = Suscripcion::query()->vigentes()->with('plan', 'empresa')->get();
        $porEstado = $vigentes->countBy(fn (Suscripcion $s) => $s->estadoEfectivo());

        // Ingreso recurrente mensual: precio del periodo / meses, de las que pagan (activas o en gracia)
        $mrr = $vigentes->filter(fn ($s) => in_array($s->estadoEfectivo(), [Suscripcion::ACTIVA, Suscripcion::VENCIDA], true))
            ->sum(fn ($s) => $s->precio / max($s->meses(), 1));

        $mes = [now()->startOfMonth(), now()->endOfMonth()];

        return response()->json(['data' => [
            'empresas' => [
                'total'      => Empresa::count(),
                'sin_plan'   => Empresa::query()->whereDoesntHave('suscripciones', fn ($q) => $q->vigentes())->count(),
                'prueba'     => $porEstado[Suscripcion::PRUEBA] ?? 0,
                'activa'     => $porEstado[Suscripcion::ACTIVA] ?? 0,
                'vencida'    => $porEstado[Suscripcion::VENCIDA] ?? 0,
                'suspendida' => $porEstado[Suscripcion::SUSPENDIDA] ?? 0,
            ],
            'mrr'            => round($mrr, 2),
            'cobrado_mes'    => round((float) PagoSuscripcion::query()->where('estado', PagoSuscripcion::PAGADO)->whereBetween('fecha_pago', $mes)->sum('monto'), 2),
            'pendiente'      => [
                'cantidad' => PagoSuscripcion::query()->where('estado', PagoSuscripcion::PENDIENTE)->count(),
                'total'    => round((float) PagoSuscripcion::query()->where('estado', PagoSuscripcion::PENDIENTE)->sum('monto'), 2),
            ],
            'por_plan'       => $vigentes->groupBy(fn ($s) => $s->plan->nombre)->map->count(),
            'vencen_pronto'  => $vigentes->filter(fn ($s) => $s->estadoEfectivo() !== Suscripcion::SUSPENDIDA && $s->diasRestantes() <= (int) config('suscripciones.dias_aviso'))
                ->sortBy('vence_el')->take(15)->values()
                ->map(fn ($s) => ['empresa' => new EmpresaResource($s->empresa), 'plan' => $s->plan->nombre, 'vence_el' => $s->vence_el->toDateString(),
                    'dias' => $s->diasRestantes(), 'estado' => $s->estadoEfectivo()]),
        ]]);
    }

    /** Valores para los formularios del admin (así no los repite) */
    public function catalogos()
    {
        return response()->json(['data' => [
            'periodicidades' => collect(config('suscripciones.periodicidades'))->map(fn ($p, $k) => ['clave' => $k, 'etiqueta' => $p[0], 'meses' => $p[1]])->values(),
            'metodos_pago'   => config('suscripciones.metodos_pago'),
            'roles'          => Role::query()->whereNull('empresa_id')->orderBy('name')->pluck('name'),
            'planes'         => Plan::query()->orderBy('orden')->get(['id', 'codigo', 'nombre', 'activo']),
            'limites'        => collect(Plan::LIMITES)->map(fn ($l) => ['columna' => $l[0], 'etiqueta' => $l[1]]),
            'modulos'        => collect(config('suscripciones.modulos'))->map(fn ($m, $clave) => ['clave' => $clave, 'nombre' => $m[0], 'descripcion' => $m[1]])->values(),
            'dias_gracia'    => (int) config('suscripciones.dias_gracia'),
            'dias_aviso'     => (int) config('suscripciones.dias_aviso'),
        ]]);
    }
}
