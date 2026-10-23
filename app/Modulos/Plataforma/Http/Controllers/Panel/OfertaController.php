<?php

namespace App\Modulos\Plataforma\Http\Controllers\Panel;

use App\Modulos\Nucleo\Http\Controllers\Controller;

use App\Modulos\Plataforma\Support\ApiAdmin;
use Illuminate\Http\Request;

/** Ofertas: descuentos por porcentaje o monto, con vigencia, cupón y máximo de usos */
class OfertaController extends Controller
{
    public function __construct(private ApiAdmin $erp)
    {
    }

    public function index()
    {
        return view('plataforma::ofertas.index', ['ofertas' => $this->erp->get('ofertas')['data']]);
    }

    public function create()
    {
        return view('plataforma::ofertas.form', [
            'oferta' => ['tipo' => 'porcentaje', 'desde' => now()->toDateString(), 'solo_primer_pago' => true, 'activo' => true],
            'planes' => $this->erp->catalogos()['planes'],
        ]);
    }

    public function store(Request $request)
    {
        $o = $this->erp->post('ofertas', $this->datos($request))['data'];

        return redirect()->route('plataforma.ofertas.index')->with('status', "Oferta «{$o['nombre']}» creada.");
    }

    public function edit(int $oferta)
    {
        return view('plataforma::ofertas.form', ['oferta' => $this->erp->get("ofertas/{$oferta}")['data'], 'planes' => $this->erp->catalogos()['planes']]);
    }

    public function update(Request $request, int $oferta)
    {
        $o = $this->erp->put("ofertas/{$oferta}", $this->datos($request))['data'];

        return redirect()->route('plataforma.ofertas.index')->with('status', "Oferta «{$o['nombre']}» actualizada.");
    }

    public function destroy(int $oferta)
    {
        $this->erp->delete("ofertas/{$oferta}");

        return redirect()->route('plataforma.ofertas.index')->with('status', 'Oferta eliminada.');
    }

    private function datos(Request $request): array
    {
        return $request->only(['nombre', 'codigo', 'plan_id', 'periodicidad', 'tipo', 'valor', 'desde', 'hasta', 'max_usos']) + [
            'solo_primer_pago' => $request->boolean('solo_primer_pago'),
            'activo'           => $request->boolean('activo'),
        ];
    }
}
