<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\EmpresaRequest;
use App\Models\Empresa;
use App\Models\Moneda;
use App\Support\EmpresaActual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Datos de la empresa con la que se trabaja. El logo se sube aparte (no depende de que el resto del
 * formulario sea válido), se guarda en el disco privado y se sirve con una ruta autenticada
 * (no necesita storage:link: funciona igual en hosting y Supabase).
 */
class EmpresaController extends Controller
{
    use ResuelveEmpresa;

    public function edit(Request $request)
    {
        $empresa = $this->empresa($request);

        return view('configuracion.empresa', [
            'empresa' => $empresa,
            'monedas' => Moneda::query()->orderBy('codigo')->get(),
            'resumen' => [
                'usuarios'   => $empresa->usuarios()->count(),
                'almacenes'  => $empresa->almacenes()->count(),
                'series'     => $empresa->correlativos()->count(),
                'desde'      => $empresa->created_at,
            ],
        ]);
    }

    public function update(EmpresaRequest $request)
    {
        $empresa = $this->empresa($request);
        $d = $request->validated();
        $d['precios_incluyen_igv'] = $request->boolean('precios_incluyen_igv');
        $d['controlar_stock'] = $request->boolean('controlar_stock');

        $empresa->update($d);
        EmpresaActual::olvidar();   // el layout muestra el nombre de la empresa

        return redirect()->route('config.empresa')->with('status', 'Datos de la empresa actualizados.');
    }

    /** Sube o reemplaza el logo */
    public function subirLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif,svg', 'max:2048'],
        ], [
            'logo.required' => 'Elige una imagen.',
            'logo.uploaded' => 'La imagen no se pudo subir: revisa que pese menos de 2 MB.',
            'logo.mimes'    => 'El logo debe ser una imagen PNG, JPG, WEBP, GIF o SVG.',
            'logo.max'      => 'El logo no debe pesar más de 2 MB.',
        ]);

        $empresa = $this->empresa($request);
        $archivo = $request->file('logo');
        $ruta = $archivo->storeAs("empresas/{$empresa->id}", 'logo-' . now()->format('YmdHis') . '.' . ($archivo->guessExtension() ?: $archivo->getClientOriginalExtension()), 'local');

        if (! $ruta) {
            throw ValidationException::withMessages(['logo' => 'No se pudo guardar el archivo: revisa los permisos de la carpeta storage/app.']);
        }

        $anterior = $empresa->logo_path;
        $empresa->update(['logo_path' => $ruta]);
        if ($anterior && $anterior !== $ruta) {
            Storage::disk('local')->delete($anterior);
        }

        return redirect()->route('config.empresa')->with('status', 'Logo actualizado. Ya aparece en el menú y en los tickets.');
    }

    public function quitarLogo(Request $request)
    {
        $empresa = $this->empresa($request);
        if ($empresa->logo_path) {
            Storage::disk('local')->delete($empresa->logo_path);
            $empresa->update(['logo_path' => null]);
        }

        return redirect()->route('config.empresa')->with('status', 'Logo eliminado.');
    }

    /** Logo de la empresa actual (para el layout, el ticket y la ficha) */
    public function logo(Request $request)
    {
        $empresa = $this->empresa($request);
        abort_unless($empresa->logo_path && Storage::disk('local')->exists($empresa->logo_path), 404);

        return response()->file(Storage::disk('local')->path($empresa->logo_path), [
            'Cache-Control' => 'private, max-age=86400',
            // Un SVG abierto directamente no puede ejecutar scripts
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function empresa(Request $request): Empresa
    {
        return Empresa::findOrFail($this->empresaId($request));
    }
}
