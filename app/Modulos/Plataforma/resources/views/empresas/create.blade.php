{{-- EmpresaController@create: $planes (activos con precios), $catalogos (metodos_pago, periodicidades, dias_gracia) --}}
@extends('plataforma::layouts.app')

@section('title', 'Nueva empresa')

@php $modo = old('modo', 'prueba'); @endphp

@section('content')
    <x-plataforma::page-header title="Nueva empresa" :migas="['Empresas' => route('plataforma.empresas.index'), 'Nueva' => null]"
        subtitle="Se crea en el ERP lista para usar: almacén principal, caja, series F001/B001/T001, su administrador y el plan." />

    <x-plataforma::flash />

    <form method="POST" action="{{ route('plataforma.empresas.store') }}" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]" id="form-empresa">
        @csrf
        <div class="space-y-5">
            <x-plataforma::card title="Datos de la empresa" icon="building">
                <div class="grid gap-4 sm:grid-cols-[14rem_minmax(0,1fr)]">
                    <div>
                        <label for="ruc" class="field-label">RUC *</label>
                        <div class="flex gap-2">
                            <input id="ruc" name="ruc" value="{{ old('ruc') }}" required maxlength="11" inputmode="numeric" autocomplete="off" @class(['field', 'border-red-400' => $errors->has('ruc')])>
                            <button type="button" data-consultar-ruc class="btn btn-secondary shrink-0" title="Completar con SUNAT" aria-label="Consultar SUNAT"><x-plataforma::icon name="search" class="text-brand-600" /></button>
                        </div>
                        @error('ruc')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                        <p data-ruc-estado hidden class="mt-1.5 text-[11px]"></p>
                    </div>
                    <x-plataforma::input name="razon_social" label="Razón social *" required maxlength="255" />
                    <x-plataforma::input name="nombre_comercial" label="Nombre comercial" maxlength="255" />
                    <x-plataforma::input name="direccion" label="Dirección fiscal" maxlength="255" />
                    <x-plataforma::input name="telefono" label="Teléfono" maxlength="30" />
                    <x-plataforma::input name="email" label="Correo de la empresa" type="email" maxlength="255" />
                </div>
            </x-plataforma::card>

            <x-plataforma::card title="Administrador de la empresa" icon="user" subtitle="Podrá entrar al ERP de inmediato. Si el correo ya tiene cuenta, solo se le da acceso.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-plataforma::input name="admin[name]" label="Nombres *" :value="old('admin.name')" required maxlength="100" />
                    <x-plataforma::input name="admin[apellido]" label="Apellidos" :value="old('admin.apellido')" maxlength="100" />
                    <x-plataforma::input name="admin[email]" label="Correo (usuario) *" type="email" :value="old('admin.email')" required maxlength="255" autocomplete="off" />
                    <x-plataforma::input name="admin[telefono]" label="Teléfono" :value="old('admin.telefono')" maxlength="30" />
                    <div class="sm:col-span-2">
                        <x-plataforma::input name="admin[password]" label="Contraseña inicial" type="text" :value="old('admin.password', \Illuminate\Support\Str::password(10, symbols: false))" maxlength="100" autocomplete="off"
                            hint="Mínimo 8 caracteres. Compártela con el cliente: podrá cambiarla en «Mi perfil»." />
                    </div>
                </div>
                @foreach (['admin.name', 'admin.email', 'admin.password'] as $campo)
                    @error($campo)<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                @endforeach
            </x-plataforma::card>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-6 lg:self-start">
            <x-plataforma::card title="Plan" icon="layers">
                <div class="space-y-2" role="radiogroup" aria-label="Plan">
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 p-3 has-checked:border-brand-500 has-checked:bg-brand-50">
                        <input type="radio" name="plan_id" value="" class="accent-brand-600" @checked(old('plan_id') === '' || old('plan_id') === null && $planes->isEmpty())>
                        <span class="text-sm">Sin plan por ahora <span class="block text-[11px] text-slate-500">Sin límites hasta que le asignes uno</span></span>
                    </label>
                    @foreach ($planes as $p)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-checked:border-brand-500 has-checked:bg-brand-50">
                            <input type="radio" name="plan_id" value="{{ $p['id'] }}" class="mt-0.5 accent-brand-600" data-precios='@json(collect($p['precios'])->where('activo', true)->pluck('precio', 'periodicidad'))' data-prueba="{{ $p['dias_prueba'] }}"
                                @checked((string) old('plan_id', $planes->first()['id'] ?? '') === (string) $p['id'])>
                            <span class="text-sm">
                                <span class="font-semibold">{{ $p['nombre'] }}</span>
                                <span class="block text-[11px] text-slate-500">
                                    {{ $p['max_comprobantes_mes'] ? number_format($p['max_comprobantes_mes']) . ' comprobantes/mes' : 'Comprobantes ilimitados' }} ·
                                    {{ $p['max_productos'] ? number_format($p['max_productos']) . ' productos' : 'productos ilimitados' }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div data-solo-plan class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                    <x-plataforma::select name="periodicidad" label="Periodicidad">
                        @foreach (config('plataforma.periodicidades') as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('periodicidad', 'mensual') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </x-plataforma::select>
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm">Precio: <strong data-precio>—</strong></p>

                    <fieldset class="space-y-1.5 text-sm">
                        <legend class="field-label">Inicio</legend>
                        <label class="flex items-center gap-2"><input type="radio" name="modo" value="prueba" class="accent-brand-600" @checked($modo === 'prueba')> Periodo de prueba <span class="text-xs text-slate-500" data-dias-prueba></span></label>
                        <label class="flex items-center gap-2"><input type="radio" name="modo" value="pagado" class="accent-brand-600" @checked($modo === 'pagado')> Ya pagó el primer periodo</label>
                        <label class="flex items-center gap-2"><input type="radio" name="modo" value="pendiente" class="accent-brand-600" @checked($modo === 'pendiente')> Queda pendiente de pago ({{ $catalogos['dias_gracia'] }} días de gracia)</label>
                    </fieldset>
                    <input type="hidden" name="prueba" value="{{ $modo === 'prueba' ? 1 : 0 }}">
                    <input type="hidden" name="pagado" value="{{ $modo === 'pagado' ? 1 : 0 }}">

                    <div data-solo-pagado class="grid grid-cols-2 gap-3">
                        <x-plataforma::select name="metodo" label="Método">
                            @foreach ($catalogos['metodos_pago'] as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('metodo') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </x-plataforma::select>
                        <x-plataforma::input name="referencia" label="N.º de operación" maxlength="100" />
                        <div class="col-span-2">
                            <x-plataforma::select name="comprobante" label="Comprobante del pago (se envía a SUNAT)">
                                <option value="factura" @selected(old('comprobante', 'factura') === 'factura')>Factura al RUC de la empresa</option>
                                <option value="boleta" @selected(old('comprobante') === 'boleta')>Boleta</option>
                                <option value="ninguno" @selected(old('comprobante') === 'ninguno')>Sin comprobante</option>
                            </x-plataforma::select>
                        </div>
                    </div>
                    <x-plataforma::input name="oferta" label="Código de oferta (opcional)" maxlength="30" class="uppercase" />
                    @error('oferta')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('periodicidad')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </x-plataforma::card>

            <button type="submit" class="btn btn-primary w-full"><x-plataforma::icon name="check" /> Crear empresa</button>
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('form-empresa');
            const nf = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

            const pintar = () => {
                const plan = form.querySelector('input[name="plan_id"]:checked');
                const conPlan = plan && plan.value !== '';
                form.querySelector('[data-solo-plan]').hidden = !conPlan;
                const modo = form.querySelector('input[name="modo"]:checked')?.value;
                form.prueba.value = modo === 'prueba' ? 1 : 0;
                form.pagado.value = modo === 'pagado' ? 1 : 0;
                form.querySelector('[data-solo-pagado]').hidden = modo !== 'pagado';
                if (!conPlan) return;
                const precios = JSON.parse(plan.dataset.precios || '{}');
                const precio = precios[form.periodicidad.value];
                form.querySelector('[data-precio]').textContent = precio !== undefined ? nf.format(precio) : 'no disponible en esta periodicidad';
                form.querySelector('[data-dias-prueba]').textContent = plan.dataset.prueba > 0 ? `(${plan.dataset.prueba} días)` : '(este plan no tiene prueba)';
            };
            form.addEventListener('change', pintar);
            pintar();

            // RUC → datos de SUNAT (la consulta la hace el ERP)
            const estado = form.querySelector('[data-ruc-estado]');
            const aviso = (t, error = false) => { estado.textContent = t; estado.className = 'mt-1.5 text-[11px] ' + (error ? 'text-red-600' : 'text-emerald-700'); estado.hidden = false; };
            form.querySelector('[data-consultar-ruc]').addEventListener('click', async () => {
                const ruc = form.ruc.value.replace(/\D/g, '');
                if (ruc.length !== 11) return aviso('El RUC debe tener 11 dígitos.', true);
                aviso('Consultando SUNAT…');
                try {
                    const r = await fetch(@json(url('empresas/consulta-ruc')) + '/' + ruc, { headers: { Accept: 'application/json' } });
                    const j = await r.json();
                    if (!r.ok) return aviso(j.message || 'No se pudo consultar.', true);
                    form.razon_social.value = j.data.nombre || form.razon_social.value;
                    if (j.data.direccion) form.direccion.value = j.data.direccion;
                    aviso('Completado desde SUNAT' + (j.data.estado ? ` · ${j.data.estado}` : '') + (j.data.condicion ? ` · ${j.data.condicion}` : ''));
                } catch { aviso('No se pudo conectar.', true); }
            });
        })();
    </script>
@endpush
