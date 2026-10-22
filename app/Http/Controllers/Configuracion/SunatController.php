<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Concerns\ResuelveEmpresa;
use App\Http\Controllers\Controller;
use App\Models\Correlativo;
use App\Models\Empresa;
use App\Services\CertificadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Conexión con SUNAT: modo (beta / producción), usuario y clave SOL y certificado digital.
 * Las claves se guardan cifradas (casts "encrypted" de Empresa) y nunca se devuelven al navegador.
 */
class SunatController extends Controller
{
    use ResuelveEmpresa;

    public function __construct(private CertificadoService $certificados)
    {
    }

    public function edit(Request $request)
    {
        $empresa = $this->empresa($request);
        $certificado = $this->certificadoActual($empresa);

        return view('configuracion.sunat', [
            'empresa'     => $empresa,
            'certificado' => $certificado,
            'requisitos'  => $this->requisitos($empresa, $certificado),
        ]);
    }

    public function update(Request $request)
    {
        $empresa = $this->empresa($request);
        $d = $request->validate([
            'sunat_modo'        => ['required', 'in:beta,produccion'],
            'sunat_usuario_sol' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/'],
            'sunat_clave_sol'   => ['nullable', 'string', 'max:100'],
            'certificado'       => ['nullable', 'file', 'max:100'],
            'certificado_clave' => ['nullable', 'string', 'max:100', 'required_with:certificado'],
            'quitar_certificado' => ['boolean'],
        ], [
            'sunat_usuario_sol.regex'          => 'El usuario SOL solo lleva letras y números (sin el RUC delante).',
            'certificado_clave.required_with'  => 'Indica la contraseña del certificado.',
            'certificado.max'                  => 'El certificado no debe pesar más de 100 KB.',
        ]);

        $cambios = [
            'sunat_modo'        => $d['sunat_modo'],
            'sunat_usuario_sol' => filled($d['sunat_usuario_sol'] ?? null) ? strtoupper($d['sunat_usuario_sol']) : null,
        ];
        if (filled($d['sunat_clave_sol'] ?? null)) {
            $cambios['sunat_clave_sol'] = $d['sunat_clave_sol'];   // vacío = se mantiene la actual
        }

        // 1) Validar el certificado nuevo (sin guardar nada todavía)
        $info = null;
        if ($request->hasFile('certificado')) {
            $archivo = $request->file('certificado');
            if (! in_array(strtolower($archivo->getClientOriginalExtension()), ['pfx', 'p12'], true)) {
                throw ValidationException::withMessages(['certificado' => 'El certificado debe ser un archivo .pfx o .p12.']);
            }
            $info = $this->certificados->leer($archivo->get(), $d['certificado_clave']);
            if (! $info['vigente']) {
                throw ValidationException::withMessages(['certificado' => 'El certificado no está vigente (válido del ' . $info['desde']->format('d/m/Y') . ' al ' . $info['hasta']->format('d/m/Y') . ').']);
            }
            if ($info['ruc'] && $info['ruc'] !== $empresa->ruc) {
                throw ValidationException::withMessages(['certificado' => "El certificado es del RUC {$info['ruc']}, no del RUC de la empresa ({$empresa->ruc})."]);
            }
        }
        $quitar = ! $info && $request->boolean('quitar_certificado');

        // 2) Producción solo con todo listo: si no, SUNAT rechazaría cada comprobante
        $empresa->fill($cambios);
        if ($empresa->sunat_modo === 'produccion') {
            $cert = $info ?? ($quitar ? null : $this->certificadoActual($empresa));
            $faltan = collect($this->requisitos($empresa, $cert))->reject(fn ($r) => $r['ok'])->pluck('texto');
            if ($faltan->isNotEmpty()) {
                throw ValidationException::withMessages(['sunat_modo' => 'Para pasar a producción falta: ' . $faltan->join('; ') . '.']);
            }
        }

        // 3) Recién ahora se reemplazan los archivos
        if ($info) {
            $ruta = $request->file('certificado')->storeAs("empresas/{$empresa->id}", 'certificado-' . now()->format('YmdHis') . '.pfx', 'local');
            if (! $ruta) {
                throw ValidationException::withMessages(['certificado' => 'No se pudo guardar el archivo: revisa los permisos de storage/app.']);
            }
            $this->borrarCertificado($empresa);
            $empresa->certificado_path = $ruta;
            $empresa->certificado_clave = $d['certificado_clave'];
        } elseif ($quitar) {
            $this->borrarCertificado($empresa);
            $empresa->certificado_path = null;
            $empresa->certificado_clave = null;
        }

        $empresa->save();

        return redirect()->route('config.sunat')->with('status', 'Configuración de SUNAT guardada' . ($empresa->sunat_modo === 'produccion' ? ' (modo producción).' : ' (modo beta: los comprobantes no tienen validez tributaria).'));
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function empresa(Request $request): Empresa
    {
        return Empresa::findOrFail($this->empresaId($request));
    }

    private function certificadoActual(Empresa $empresa): ?array
    {
        if (! $empresa->certificado_path || ! Storage::disk('local')->exists($empresa->certificado_path)) {
            return null;
        }

        try {
            return $this->certificados->leer(Storage::disk('local')->get($empresa->certificado_path), (string) $empresa->certificado_clave);
        } catch (ValidationException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function borrarCertificado(Empresa $empresa): void
    {
        if ($empresa->certificado_path) {
            Storage::disk('local')->delete($empresa->certificado_path);
        }
    }

    /** Lo que SUNAT exige para emitir en producción */
    private function requisitos(Empresa $empresa, ?array $certificado): array
    {
        $series = Correlativo::query()->deEmpresa($empresa->id)->where('modulo', Correlativo::MODULO_VENTA)->activo()->pluck('tipo');

        return [
            ['ok' => filled($empresa->ubigeo) && filled($empresa->direccion), 'texto' => 'dirección fiscal y ubigeo', 'url' => route('config.empresa')],
            ['ok' => filled($empresa->sunat_usuario_sol) && filled($empresa->sunat_clave_sol), 'texto' => 'usuario y clave SOL', 'url' => null],
            ['ok' => $certificado && empty($certificado['error']) && $certificado['vigente'], 'texto' => 'certificado digital vigente', 'url' => null],
            ['ok' => $series->contains('01') || $series->contains('03'), 'texto' => 'una serie de factura o boleta activa', 'url' => route('series.index')],
        ];
    }
}
