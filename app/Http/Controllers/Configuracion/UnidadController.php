<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\TipoUnidad;
use App\Services\CatalogoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Unidades de medida (catálogo común a todas las empresas, código SUNAT del catálogo 03).
 * Cualquier empresa agrega unidades; modificar o eliminar solo si ninguna otra empresa la usa.
 */
class UnidadController extends Controller
{
    use ResuelveEmpresa;

    /** Códigos frecuentes del catálogo SUNAT 03 (se puede escribir cualquier otro) */
    public const SUNAT = [
        'NIU' => 'Unidad (bienes)', 'ZZ' => 'Unidad (servicios)', 'KGM' => 'Kilogramo', 'GRM' => 'Gramo', 'TNE' => 'Tonelada',
        'LTR' => 'Litro', 'MLT' => 'Mililitro', 'GLL' => 'Galón', 'MTR' => 'Metro', 'CMT' => 'Centímetro', 'MTK' => 'Metro cuadrado',
        'BX' => 'Caja', 'PK' => 'Paquete', 'BG' => 'Bolsa', 'SA' => 'Saco', 'CA' => 'Lata', 'BJ' => 'Balde', 'JR' => 'Frasco',
        'BO' => 'Botella', 'DZN' => 'Docena', 'PR' => 'Par', 'KT' => 'Kit', 'RO' => 'Rollo', 'EV' => 'Sobre', 'SET' => 'Juego',
        'HUR' => 'Hora', 'DAY' => 'Día', 'MON' => 'Mes',
    ];

    public function __construct(private CatalogoService $catalogo)
    {
    }

    public function index(Request $request)
    {
        $empresaId = $this->empresaId($request);
        $uso = $this->catalogo->uso('tipo_unidad_id');
        $q = trim((string) $request->query('q'));

        return view('configuracion.unidades.index', [
            'unidades'  => TipoUnidad::query()
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereLike('codigo', "%{$q}%")->orWhereLike('descripcion', "%{$q}%")->orWhereLike('codigo_sunat', "%{$q}%")))
                ->orderBy('descripcion')->get(),
            'usoPropio' => $uso->map(fn ($e) => (int) ($e[$empresaId] ?? 0)),
            'usoOtras'  => $uso->map(fn ($e) => $e->keys()->contains(fn ($id) => (int) $id !== $empresaId)),
            'enUso'     => $uso->keys()->map(fn ($k) => (int) $k)->all(),
            'sunat'     => self::SUNAT,
        ]);
    }

    public function create()
    {
        return view('configuracion.unidades.form', ['unidad' => new TipoUnidad, 'sunat' => self::SUNAT, 'bloqueado' => false]);
    }

    public function store(Request $request)
    {
        $unidad = TipoUnidad::create($this->validar($request));

        return redirect()->route('unidades.index')->with('status', "Unidad «{$unidad->descripcion}» registrada. Ya está disponible en los productos.");
    }

    public function edit(Request $request, TipoUnidad $unidad)
    {
        return view('configuracion.unidades.form', [
            'unidad'    => $unidad,
            'sunat'     => self::SUNAT,
            'bloqueado' => $this->catalogo->usadoPorOtras('tipo_unidad_id', $unidad->id, $this->empresaId($request)),
        ]);
    }

    public function update(Request $request, TipoUnidad $unidad)
    {
        abort_if($this->catalogo->usadoPorOtras('tipo_unidad_id', $unidad->id, $this->empresaId($request)), 403,
            'Otras empresas usan esta unidad: no se puede modificar desde aquí.');

        $unidad->update($this->validar($request, $unidad->id));

        return redirect()->route('unidades.index')->with('status', "Unidad «{$unidad->descripcion}» actualizada.");
    }

    public function destroy(TipoUnidad $unidad)
    {
        if ($this->catalogo->enUso('tipo_unidad_id', $unidad->id)) {
            return back()->with('error', "«{$unidad->descripcion}» está en uso por productos, conversiones o documentos.");
        }

        $unidad->delete();

        return redirect()->route('unidades.index')->with('status', "Unidad «{$unidad->descripcion}» eliminada.");
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        $request->merge([
            'codigo'       => strtoupper(trim((string) $request->input('codigo'))),
            'codigo_sunat' => strtoupper(trim((string) $request->input('codigo_sunat'))) ?: null,
        ]);

        return $request->validate([
            'codigo'       => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('tipo_unidades', 'codigo')->ignore($ignorar)],
            'codigo_sunat' => ['nullable', 'string', 'max:5', 'regex:/^[A-Z0-9]+$/'],
            'descripcion'  => ['required', 'string', 'max:255'],
        ], [
            'codigo.unique' => 'Ya existe una unidad con ese código.',
            'codigo.regex'  => 'Usa solo letras y números, sin espacios (ej. SCO15).',
        ], ['codigo_sunat' => 'código SUNAT', 'descripcion' => 'descripción']);
    }
}
