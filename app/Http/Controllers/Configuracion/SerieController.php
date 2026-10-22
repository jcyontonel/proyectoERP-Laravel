<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Correlativo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Series y correlativos de la empresa. `numero` guarda el último emitido, así que:
 *  - una serie sin uso (número 0) se puede cambiar o eliminar;
 *  - con documentos emitidos solo se cambia la descripción, el estado o se adelanta el número
 *    (nunca se retrocede: chocaría con comprobantes ya emitidos).
 */
class SerieController extends Controller
{
    use ResuelveEmpresa;

    public function index(Request $request)
    {
        $series = Correlativo::query()->deEmpresa($this->empresaId($request))
            ->orderBy('tipo')->orderBy('serie')->get()
            ->groupBy('modulo');

        return view('configuracion.series.index', [
            'series'  => $series,
            'modulos' => Correlativo::MODULOS,
            'tipos'   => Correlativo::TIPOS,
        ]);
    }

    public function create(Request $request)
    {
        $modulo = array_key_exists($request->query('modulo'), Correlativo::TIPOS) ? $request->query('modulo') : 'venta';

        return view('configuracion.series.form', [
            'serie' => new Correlativo(['modulo' => $modulo, 'tipo' => array_key_first(Correlativo::TIPOS[$modulo]), 'numero' => 0, 'activo' => true]),
            'tipos' => Correlativo::TIPOS, 'modulos' => Correlativo::MODULOS,
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $d = $this->validar($request, $empresaId);

        $serie = Correlativo::create($d + ['empresa_id' => $empresaId]);

        return redirect()->route('series.index')->with('status', "Serie {$serie->serie} ({$serie->destipo}) creada. El próximo número será {$serie->siguiente()}.");
    }

    public function edit(Correlativo $serie)
    {
        return view('configuracion.series.form', ['serie' => $serie, 'tipos' => Correlativo::TIPOS, 'modulos' => Correlativo::MODULOS]);
    }

    public function update(Request $request, Correlativo $serie)
    {
        DB::transaction(function () use ($request, $serie) {
            $actual = Correlativo::query()->lockForUpdate()->findOrFail($serie->id);   // una venta podría estar emitiendo
            $d = $this->validar($request, $actual->empresa_id, $actual);

            if (! $actual->sinUso()) {
                $d = collect($d)->only(['destipo', 'numero', 'activo'])->all();   // modulo, tipo y serie quedan fijos
            }
            $actual->update($d);
        });

        return redirect()->route('series.index')->with('status', "Serie {$serie->serie} actualizada.");
    }

    /** Solo series que nunca emitieron: con historial se desactivan */
    public function destroy(Correlativo $serie)
    {
        if (! $serie->sinUso()) {
            return back()->with('error', "La serie {$serie->serie} ya emitió documentos: desactívala en lugar de eliminarla.");
        }

        $serie->forceDelete();   // libera la combinación empresa + tipo + serie del índice único

        return redirect()->route('series.index')->with('status', "Serie {$serie->serie} eliminada.");
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function validar(Request $request, int $empresaId, ?Correlativo $actual = null): array
    {
        $request->merge(['serie' => strtoupper(trim((string) $request->input('serie', $actual?->serie)))]);
        $usada = $actual && ! $actual->sinUso();

        $modulo = $usada ? $actual->modulo : $request->input('modulo');
        $tipo = $usada ? $actual->tipo : $request->input('tipo');
        $regla = Correlativo::TIPOS[$modulo][$tipo] ?? null;

        $v = validator($request->all(), [
            'modulo'  => [$usada ? 'nullable' : 'required', Rule::in(array_keys(Correlativo::TIPOS))],
            'tipo'    => [$usada ? 'nullable' : 'required', Rule::in(array_keys(Correlativo::TIPOS[$modulo] ?? []))],
            'serie'   => ['required', 'string', 'max:10', Rule::unique('correlativos')->ignore($actual?->id)
                ->where(fn ($q) => $q->where('empresa_id', $empresaId)->where('tipo', $tipo))],
            'destipo' => ['required', 'string', 'max:30'],
            'numero'  => ['nullable', 'integer', 'min:' . (int) ($actual?->numero ?? 0), 'max:99999999'],
            'activo'  => ['boolean'],
        ], [
            'serie.unique' => 'Esa serie ya existe para este tipo de comprobante.',
            'numero.min'   => 'El número no puede retroceder: el último emitido es :min.',
            'tipo.in'      => 'El tipo no corresponde al módulo elegido.',
        ], ['destipo' => 'descripción', 'numero' => 'último número emitido']);

        $v->after(function (Validator $v) use ($request, $regla, $usada) {
            if (! $usada && $regla && ! preg_match($regla[1], $request->input('serie'))) {
                $v->errors()->add('serie', "Formato inválido para {$regla[0]}: por ejemplo {$regla[2]}.");
            }
        });

        $d = $v->validate();
        $d['activo'] = $request->boolean('activo');
        $d['numero'] = (int) ($d['numero'] ?? $actual?->numero ?? 0);

        return $d;
    }
}
