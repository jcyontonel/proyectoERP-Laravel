<?php

namespace App\Modulos\Plataforma\Http\Controllers\Api;

use App\Modulos\Plataforma\Http\Resources\EmpresaResource;
use App\Modulos\Plataforma\Http\Resources\PagoSuscripcionResource;
use App\Modulos\Plataforma\Http\Resources\SuscripcionResource;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Suscripciones\Models\Plan;
use App\Modulos\Suscripciones\Models\Suscripcion;
use App\Modulos\Nucleo\Services\Documentos\ApiPeruService;
use App\Modulos\Erp\Services\EmpresaService;
use App\Modulos\Suscripciones\Services\SuscripcionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Empresas clientes del ERP: listado con su plan, ficha con consumo y alta completa */
class EmpresaController extends AdminController
{
    /** Se resuelve en cada uso: el servicio es por petición (el controlador puede reutilizarse) */
    private function suscripciones(): SuscripcionService
    {
        return app(SuscripcionService::class);
    }

    public function index(Request $request)
    {
        $f = $request->validate([
            'q'       => ['nullable', 'string', 'max:100'],
            'estado'  => ['nullable', Rule::in(['prueba', 'activa', 'vencida', 'suspendida', 'sin_plan', 'inactiva'])],
            'plan_id' => ['nullable', 'integer'],
        ]);
        $hoy = today()->toDateString();
        $finGracia = today()->subDays((int) config('suscripciones.dias_gracia'))->toDateString();
        $vigente = fn (Builder $q) => $q->where('estado', '!=', Suscripcion::CANCELADA);

        $empresas = Empresa::query()
            ->with(['suscripcion.plan'])
            ->withCount('usuarios')
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where(fn ($w) => $w->whereLike('ruc', "%{$f['q']}%")
                ->orWhereLike('razon_social', "%{$f['q']}%")->orWhereLike('nombre_comercial', "%{$f['q']}%")))
            ->when(filled($f['plan_id'] ?? null), fn ($q) => $q->whereHas('suscripciones', fn ($s) => $vigente($s)->where('plan_id', $f['plan_id'])))
            ->when($f['estado'] ?? null, fn ($q, $estado) => match ($estado) {
                'sin_plan'   => $q->whereDoesntHave('suscripciones', $vigente),
                'inactiva'   => $q->where('activo', false),
                'prueba'     => $q->whereHas('suscripciones', fn ($s) => $s->where('estado', Suscripcion::PRUEBA)->whereDate('vence_el', '>=', $hoy)),
                'activa'     => $q->whereHas('suscripciones', fn ($s) => $s->where('estado', Suscripcion::ACTIVA)->whereDate('vence_el', '>=', $hoy)),
                'vencida'    => $q->whereHas('suscripciones', fn ($s) => $s->whereIn('estado', [Suscripcion::PRUEBA, Suscripcion::ACTIVA])
                    ->whereDate('vence_el', '<', $hoy)->whereDate('vence_el', '>=', $finGracia)),
                'suspendida' => $q->whereHas('suscripciones', fn ($s) => $s->where(fn ($w) => $w->where('estado', Suscripcion::SUSPENDIDA)
                    ->orWhere(fn ($x) => $x->whereIn('estado', [Suscripcion::PRUEBA, Suscripcion::ACTIVA])->whereDate('vence_el', '<', $finGracia)))),
            })
            ->orderBy('razon_social')
            ->paginate(25)
            ->withQueryString();

        return EmpresaResource::collection($empresas);
    }

    /** Ficha: datos, consumo del plan, historial de suscripciones y pagos */
    public function show(Empresa $empresa)
    {
        $resumen = $this->suscripciones()->resumen($empresa->id);

        return response()->json(['data' => [
            'empresa'       => new EmpresaResource($empresa->load('suscripcion.plan', 'suscripcion.oferta')->loadCount('usuarios')),
            'estado'        => $resumen['estado'],
            'limites'       => $resumen['limites'],
            'suscripciones' => SuscripcionResource::collection($empresa->suscripciones()->with('plan', 'oferta')->latest('id')->get()),
            'pagos'         => PagoSuscripcionResource::collection($empresa->hasMany(\App\Modulos\Suscripciones\Models\PagoSuscripcion::class)
                ->with('suscripcion.plan', 'oferta', 'comprobante', 'notaCredito')->latest('id')->limit(50)->get()),
        ]]);
    }

    /** Alta completa: empresa + configuración inicial + administrador + plan */
    public function store(Request $request, EmpresaService $empresas)
    {
        $d = $request->validate($this->reglas() + [
            'admin.name'     => ['required', 'string', 'max:100'],
            'admin.apellido' => ['nullable', 'string', 'max:100'],
            'admin.email'    => ['required', 'email', 'max:255'],
            'admin.telefono' => ['nullable', 'string', 'max:30'],
            'admin.password' => ['nullable', 'string', 'min:8', 'max:100'],
            'plan_id'        => ['nullable', 'integer', Rule::exists('planes', 'id')],
            'periodicidad'   => ['required_with:plan_id', Rule::in(array_keys(config('suscripciones.periodicidades')))],
            'prueba'         => ['boolean'],
            'pagado'         => ['boolean'],
            'oferta'         => ['nullable', 'string', 'max:30'],
            'metodo'         => ['nullable', Rule::in(array_keys(config('suscripciones.metodos_pago')))],
            'referencia'     => ['nullable', 'string', 'max:100'],
            'comprobante'    => ['nullable', Rule::in(['factura', 'boleta', 'ninguno'])],   // del primer pago
        ]);

        [$empresa, $admin, $existia] = DB::transaction(function () use ($d, $empresas, $request) {
            $resultado = $empresas->crear(collect($d)->only(array_keys($this->reglas()))->all(), $d['admin']);
            if (filled($d['plan_id'] ?? null)) {
                $this->suscripciones()->asignar($resultado[0], Plan::findOrFail($d['plan_id']), $d, $this->operador($request));
            }

            return $resultado;
        });

        return response()->json([
            'data'    => new EmpresaResource($empresa->load('suscripcion.plan')),
            'admin'   => ['id' => $admin->id, 'email' => $admin->email, 'existia' => $existia],
            'message' => "Empresa {$empresa->razon_social} creada." . ($existia ? " {$admin->email} ya tenía cuenta: se le dio acceso como Administrador." : ''),
        ], 201);
    }

    public function update(Request $request, Empresa $empresa)
    {
        $empresa->update($request->validate($this->reglas($empresa) + ['activo' => ['boolean']]));

        return new EmpresaResource($empresa->refresh()->load('suscripcion.plan'));
    }

    /** RUC → razón social y dirección (SUNAT vía APIPeru; el token queda en el ERP) */
    public function consultaRuc(string $ruc, ApiPeruService $apiPeru)
    {
        try {
            $datos = $apiPeru->consultar($ruc);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $datos ? response()->json(['data' => $datos]) : response()->json(['message' => "No se encontró el RUC {$ruc} en SUNAT."], 404);
    }

    private function reglas(?Empresa $empresa = null): array
    {
        return [
            'ruc'              => ['required', 'digits:11', 'regex:/^(10|15|17|20)\d{9}$/', Rule::unique('empresas', 'ruc')->ignore($empresa?->id)],
            'razon_social'     => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion'        => ['nullable', 'string', 'max:255'],
            'ubigeo'           => ['nullable', 'digits:6'],
            'departamento'     => ['nullable', 'string', 'max:100'],
            'provincia'        => ['nullable', 'string', 'max:100'],
            'distrito'         => ['nullable', 'string', 'max:100'],
            'telefono'         => ['nullable', 'string', 'max:30'],
            'email'            => ['nullable', 'email', 'max:255'],
        ];
    }
}
