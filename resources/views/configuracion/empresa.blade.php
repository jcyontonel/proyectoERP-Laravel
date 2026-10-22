{{-- EmpresaController@edit: $empresa, $monedas, $resumen [usuarios, almacenes, series, desde] --}}
@extends('layouts.app')

@section('title', 'Datos de la empresa')

@section('content')
    <x-page-header title="Datos de la empresa" :migas="['Configuración' => null, 'Datos de la empresa' => null]"
        subtitle="Esta información aparece en tus comprobantes, tickets y reportes." />

    <x-flash />

    <form method="POST" action="{{ route('config.empresa.update') }}" enctype="multipart/form-data" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]" id="form-empresa">
        @csrf
        @method('PUT')

        <div class="space-y-5">
            <x-card title="Identificación" icon="building" subtitle="Datos registrados en SUNAT.">
                <div class="grid gap-4 sm:grid-cols-[14rem_minmax(0,1fr)]">
                    <div>
                        <label for="ruc" class="field-label">RUC *</label>
                        <div class="flex gap-2">
                            <input id="ruc" name="ruc" value="{{ old('ruc', $empresa->ruc) }}" required maxlength="11" inputmode="numeric" autocomplete="off"
                                @class(['field', 'border-red-400' => $errors->has('ruc')])>
                            <button type="button" data-consultar-ruc class="btn btn-secondary shrink-0" title="Completar con los datos de SUNAT" aria-label="Consultar SUNAT">
                                <x-icon name="search" class="text-brand-600" />
                            </button>
                        </div>
                        @error('ruc')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p data-ruc-estado hidden class="mt-1.5 text-[11px]"></p>
                    </div>
                    <x-input name="razon_social" label="Razón social *" :value="$empresa->razon_social" required maxlength="255" />
                    <div class="sm:col-span-2">
                        <x-input name="nombre_comercial" label="Nombre comercial" :value="$empresa->nombre_comercial" maxlength="255"
                            hint="Es el nombre que se destaca en el ticket y en el menú. Si lo dejas vacío se usa la razón social." />
                    </div>
                </div>
            </x-card>

            <x-card title="Dirección fiscal y contacto" icon="map-pin">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><x-input name="direccion" label="Dirección" :value="$empresa->direccion" maxlength="255" /></div>
                    <x-input name="departamento" label="Departamento" :value="$empresa->departamento" maxlength="100" />
                    <x-input name="provincia" label="Provincia" :value="$empresa->provincia" maxlength="100" />
                    <x-input name="distrito" label="Distrito" :value="$empresa->distrito" maxlength="100" />
                    <x-input name="ubigeo" label="Ubigeo (INEI)" :value="$empresa->ubigeo" maxlength="6" inputmode="numeric" placeholder="150101" hint="6 dígitos. Lo exige la facturación electrónica." />
                    <x-input name="telefono" label="Teléfono" :value="$empresa->telefono" maxlength="30" type="tel" />
                    <x-input name="email" label="Correo" :value="$empresa->email" maxlength="255" type="email" />
                </div>
            </x-card>

            <x-card title="Configuración comercial" icon="percent" subtitle="Afecta los cálculos de las ventas y compras nuevas; lo ya emitido no cambia.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="moneda" label="Moneda principal">
                        @foreach ($monedas as $m)
                            <option value="{{ $m->codigo }}" @selected(old('moneda', $empresa->moneda) === $m->codigo)>{{ $m->codigo }} · {{ $m->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input name="igv" label="IGV (%)" type="number" step="0.01" min="0" max="50" :value="(float) $empresa->igv" required />
                </div>
                <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                    <x-toggle name="precios_incluyen_igv" label="Los precios de venta incluyen IGV" :checked="$empresa->precios_incluyen_igv"
                        hint="Actívalo si el precio de tus productos es el precio final al público." />
                    <x-toggle name="controlar_stock" label="Controlar stock" :checked="$empresa->controlar_stock"
                        hint="Impide vender más de lo que hay en el almacén." />
                </div>
            </x-card>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            {{-- El logo se guarda al elegirlo, con su propio formulario (form-logo, fuera de este) --}}
            <x-card title="Logo" icon="store" id="logo">
                <div class="grid place-items-center rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                    @if ($empresa->logo_path)
                        <img src="{{ route('config.empresa.logo') }}?v={{ $empresa->updated_at?->timestamp }}" alt="Logo de la empresa" class="max-h-28 max-w-full object-contain">
                    @else
                        <p class="text-xs text-slate-500">Sin logo</p>
                    @endif
                </div>
                @error('logo')
                    <p role="alert" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</p>
                @enderror
                <label class="btn btn-secondary mt-3 w-full cursor-pointer" data-logo-boton>
                    <x-icon name="upload-cloud" class="text-brand-600" /> <span data-logo-texto>{{ $empresa->logo_path ? 'Cambiar logo' : 'Subir logo' }}</span>
                    <input type="file" name="logo" form="form-logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="sr-only" data-logo-input>
                </label>
                <p class="mt-2 text-[11px] text-slate-500">PNG, JPG, WEBP, GIF o SVG de hasta 2 MB. Se guarda al elegirlo y se muestra en el menú y en el ticket.</p>
                @if ($empresa->logo_path)
                    <button type="submit" form="form-logo-quitar" class="mt-2 text-xs font-semibold text-red-500 hover:text-red-600">Quitar logo</button>
                @endif
            </x-card>

            <x-card title="Resumen" icon="info">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Usuarios</dt><dd class="font-medium"><a href="{{ route('usuarios.index') }}" class="text-brand-600">{{ $resumen['usuarios'] }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Almacenes</dt><dd class="font-medium"><a href="{{ route('almacenes.index') }}" class="text-brand-600">{{ $resumen['almacenes'] }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Series</dt><dd class="font-medium">{{ $resumen['series'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Registrada</dt><dd class="font-medium">{{ $resumen['desde']?->translatedFormat('d M Y') ?? '—' }}</dd></div>
                </dl>
            </x-card>

            <button type="submit" class="btn btn-primary w-full"><x-icon name="save" /> Guardar cambios</button>
        </aside>
    </form>

    <form id="form-logo" method="POST" action="{{ route('config.empresa.logo.subir') }}" enctype="multipart/form-data" hidden>@csrf</form>
    <form id="form-logo-quitar" method="POST" action="{{ route('config.empresa.logo.quitar') }}" hidden>@csrf @method('DELETE')</form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('form-empresa');
            const estado = form.querySelector('[data-ruc-estado]');
            const aviso = (texto, error = false) => {
                estado.textContent = texto;
                estado.className = 'mt-1.5 text-[11px] ' + (error ? 'text-red-600' : 'text-emerald-700');
                estado.hidden = false;
            };

            // RUC → razón social y dirección (la consulta la hace el servidor: el token nunca llega aquí)
            form.querySelector('[data-consultar-ruc]').addEventListener('click', async () => {
                const ruc = form.ruc.value.replace(/\D/g, '');
                if (ruc.length !== 11) return aviso('El RUC debe tener 11 dígitos.', true);
                aviso('Consultando SUNAT…');
                try {
                    const r = await fetch(@json(url('clientes/consulta')) + '/' + ruc, { headers: { Accept: 'application/json' } });
                    const datos = await r.json();
                    if (!r.ok) return aviso(datos.errors?.numero_documento?.[0] || datos.message || 'No se pudo consultar.', true);
                    if (datos.nombre) form.razon_social.value = datos.nombre;
                    if (datos.direccion) form.direccion.value = datos.direccion;
                    aviso('Datos completados desde SUNAT' + (datos.estado ? ` · ${datos.estado}` : '') + (datos.condicion ? ` · ${datos.condicion}` : '') + '. Revisa y guarda.');
                } catch {
                    aviso('No se pudo conectar con el servicio de consulta.', true);
                }
            });

            // Logo: se envía en cuanto se elige (con aviso si excede el tamaño, antes de subirlo)
            const logo = document.querySelector('[data-logo-input]');
            logo.addEventListener('change', () => {
                const archivo = logo.files[0];
                if (!archivo) return;
                if (archivo.size > 2 * 1024 * 1024) {
                    alert('El logo pesa ' + (archivo.size / 1048576).toFixed(1) + ' MB: el máximo es 2 MB. Redúcelo e inténtalo de nuevo.');
                    logo.value = '';
                    return;
                }
                document.querySelector('[data-logo-texto]').textContent = 'Subiendo…';
                document.querySelector('[data-logo-boton]').classList.add('pointer-events-none', 'opacity-60');
                document.getElementById('form-logo').submit();
            });
        })();
    </script>
@endpush
