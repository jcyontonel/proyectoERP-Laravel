<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InicioController extends Controller
{
    use ResuelveEmpresa;

    /** Panel de inicio: los bloques visibles dependen de los permisos del usuario (ver DashboardService) */
    public function __invoke(Request $request, DashboardService $dashboard)
    {
        $f = $request->validate([
            'fecha'  => ['nullable', 'date', 'before_or_equal:today'],
            'semana' => ['nullable', 'in:actual,anterior'],
        ]);

        $fecha = isset($f['fecha']) ? Carbon::parse($f['fecha']) : now();

        return view('inicio.index', $dashboard->para(
            $request->user(),
            $this->empresaId($request),
            $fecha,
            $f['semana'] ?? 'actual',
        ));
    }
}
