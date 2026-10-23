<?php

namespace App\Modulos\Web\Http\Controllers;

use App\Modulos\Nucleo\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Modulos\Nucleo\Http\Controllers\Controller;
use App\Modulos\Nucleo\Models\Empresa;
use App\Modulos\Web\Models\Tienda;
use App\Modulos\Web\Services\TiendaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Página web → Mi tienda: dirección, textos, contacto, entrega y pagos (Culqi propio de la empresa) */
class TiendaController extends Controller
{
    use ResuelveEmpresa;

    public function edit(Request $request, TiendaService $tiendas)
    {
        $tienda = Tienda::deEmpresa(Empresa::findOrFail($this->empresaId($request)));

        return view('web.tienda', [
            'tienda'    => $tienda,
            'modalidad' => $tiendas->modalidad($tienda->empresa_id),
            'culqi'     => $tienda->culqi(),
            'publicados' => $tiendas->publicados($tienda->empresa_id)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $tienda = Tienda::deEmpresa(Empresa::findOrFail($this->empresaId($request)));
        $d = $request->validate([
            'slug'                => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::notIn(Tienda::RESERVADOS), Rule::unique('tiendas', 'slug')->ignore($tienda->id)],
            'publicada'           => ['boolean'],
            'titulo'              => ['nullable', 'string', 'max:120'],
            'lema'                => ['nullable', 'string', 'max:160'],
            'descripcion'         => ['nullable', 'string', 'max:2000'],
            'whatsapp'            => ['nullable', 'string', 'regex:/^\d{9,15}$/'],
            'correo'              => ['nullable', 'email', 'max:150'],
            'color'               => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'mostrar_stock'       => ['boolean'],
            'recojo_en_tienda'    => ['boolean'],
            'direccion_recojo'    => ['nullable', 'string', 'max:255'],
            'pedido_minimo'       => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'metodos_pago'        => ['nullable', 'array'],
            'metodos_pago.*'      => [Rule::in(array_keys(Tienda::METODOS_PAGO))],
            'instrucciones_pago'  => ['nullable', 'string', 'max:1000'],
            'culqi_modo'          => ['required', Rule::in(['prueba', 'produccion'])],
            'culqi_llave_publica' => ['nullable', 'string', 'max:120', 'regex:/^pk_(test|live)_[A-Za-z0-9]+$/'],
            'culqi_llave_secreta' => ['nullable', 'string', 'max:120', 'regex:/^sk_(test|live)_[A-Za-z0-9]+$/'],
            'quitar_culqi'        => ['boolean'],
        ], [
            'slug.regex'                => 'La dirección solo lleva minúsculas, números y guiones (ej. mi-tienda).',
            'slug.unique'               => 'Esa dirección ya la usa otra tienda.',
            'slug.not_in'               => 'Esa dirección está reservada.',
            'whatsapp.regex'            => 'Escribe el WhatsApp con el código de país y sin espacios (ej. 51987654321).',
            'culqi_llave_publica.regex' => 'La llave pública de Culqi empieza con pk_test_ o pk_live_.',
            'culqi_llave_secreta.regex' => 'La llave secreta de Culqi empieza con sk_test_ o sk_live_.',
        ]);

        // Las llaves deben corresponder al modo (prueba: _test_, producción: _live_)
        $prefijo = $d['culqi_modo'] === 'produccion' ? '_live_' : '_test_';
        foreach (['culqi_llave_publica', 'culqi_llave_secreta'] as $campo) {
            if (filled($d[$campo] ?? null) && ! str_contains($d[$campo], $prefijo)) {
                return back()->withInput($request->except('culqi_llave_secreta'))->withErrors([$campo => $d['culqi_modo'] === 'produccion'
                    ? 'En producción usa las llaves pk_live_/sk_live_ de tu cuenta Culqi.' : 'En modo prueba usa las llaves pk_test_/sk_test_.']);
            }
        }

        $secreta = $d['culqi_llave_secreta'] ?? null;
        unset($d['culqi_llave_secreta'], $d['quitar_culqi']);
        $d['metodos_pago'] = array_values(array_intersect(array_keys(Tienda::METODOS_PAGO), $d['metodos_pago'] ?? []));
        $d['pedido_minimo'] = (float) ($d['pedido_minimo'] ?? 0);
        $tienda->fill($d);
        if ($request->boolean('quitar_culqi')) {
            $tienda->culqi_llave_publica = null;
            $tienda->culqi_llave_secreta = null;
        } elseif (filled($secreta)) {
            $tienda->culqi_llave_secreta = $secreta;   // se guarda cifrada y nunca vuelve al navegador
        }
        $tienda->save();

        return redirect()->route('web.tienda.edit')->with('status', $tienda->publicada
            ? 'Tienda guardada y publicada en ' . $tienda->urlPublica()
            : 'Tienda guardada. Aún no está publicada: actívala cuando esté lista.');
    }

    /** Abre la página pública (si aún no está publicada, avisa) */
    public function ver(Request $request, TiendaService $tiendas)
    {
        $tienda = Tienda::deEmpresa(Empresa::findOrFail($this->empresaId($request)));
        if (! $tiendas->disponible($tienda->slug)) {
            return redirect()->route('web.tienda.edit')->with('error', 'Tu tienda aún no está publicada (actívala en Mi tienda) o tu plan web no está al día.');
        }

        return redirect()->away($tienda->urlPublica());
    }
}
