{{--
    Crear y editar cliente (ClienteController@create / @edit)
      $cliente         Cliente (nuevo o existente)
      $tiposDocumento  catálogo SUNAT 06 (codigo, abreviatura, descripcion)
--}}
@extends('layouts.app')

@php
    $editando = $cliente->exists;
    $titulo   = $editando ? 'Editar cliente' : 'Nuevo cliente';
@endphp

@section('title', $titulo)

@section('content')
    <x-page-header :title="$titulo"
        :migas="array_filter(['Clientes' => route('clientes.index'), ($editando ? $cliente->nombre_completo : '') => $editando ? route('clientes.show', $cliente) : null, $titulo => null], fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH)"
        subtitle="El documento identifica al cliente dentro de tu empresa. Sus datos solo los ve tu empresa." />

    <x-flash />


    <form method="POST" action="{{ $editando ? route('clientes.update', $cliente) : route('clientes.store') }}" data-form-cliente
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="space-y-5">
            <x-card title="Identificación" icon="file-text" subtitle="Para facturas el cliente debe tener RUC (11 dígitos).">
                <div class="grid gap-4 sm:grid-cols-[14rem_minmax(0,1fr)]">
                    <x-select name="tipo_documento_id" label="Tipo de documento *" required data-tipo-doc>
                        <option value="">Selecciona…</option>
                        @foreach ($tiposDocumento as $t)
                            <option value="{{ $t->id }}" data-codigo="{{ $t->codigo }}" @selected((string) old('tipo_documento_id', $cliente->tipo_documento_id) === (string) $t->id)>{{ $t->abreviatura }} · {{ $t->descripcion }}</option>
                        @endforeach
                    </x-select>
                    <div>
                        <x-input name="numero_documento" label="Número de documento *" :value="$cliente->numero_documento" required maxlength="15" autocomplete="off" data-num-doc
                            hint="DNI: 8 dígitos · RUC: 11 dígitos · CE/Pasaporte: 5 a 12 caracteres." />
                        <button type="button" data-consultar hidden class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700">
                            <x-icon name="search" class="size-3.5" /> <span data-consultar-texto>Autocompletar con RENIEC</span>
                        </button>
                        <p data-consulta-estado hidden role="status" aria-live="polite" class="mt-2 rounded-lg px-3 py-2 text-xs"></p>
                    </div>
                </div>
            </x-card>

            <x-card title="Datos del cliente" icon="user">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div data-campo-nombre>
                        <x-input name="nombre" label="Nombres o razón social *" :value="$cliente->nombre" required maxlength="255" />
                    </div>
                    <div data-campo-apellido>
                        <x-input name="apellido" label="Apellidos" :value="$cliente->apellido" maxlength="255" />
                    </div>
                    <x-input name="telefono" label="Teléfono / celular" type="tel" :value="$cliente->telefono" maxlength="30" placeholder="9XX XXX XXX" />
                    <x-input name="correo" label="Correo electrónico" type="email" :value="$cliente->correo" maxlength="255" placeholder="cliente@correo.com" hint="Para enviarle sus comprobantes electrónicos." />
                    <div class="sm:col-span-2">
                        <x-input name="direccion" label="Dirección" :value="$cliente->direccion" maxlength="255" hint="Obligatoria en facturas a empresas." />
                    </div>
                </div>
            </x-card>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Opciones" icon="sliders">
                <x-toggle name="activo" label="Activo" :checked="$cliente->activo" hint="Los inactivos no aparecen al buscar en Nueva venta." />
            </x-card>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <button type="submit" class="btn btn-primary w-full"><x-icon name="save" /> {{ $editando ? 'Guardar cambios' : 'Registrar cliente' }}</button>
                <a href="{{ $editando ? route('clientes.show', $cliente) : route('clientes.index') }}" class="btn btn-secondary mt-2 w-full">Cancelar</a>
                <p class="mt-3 flex items-start gap-2 text-[11px] text-slate-500">
                    <x-icon name="sparkles" class="mt-px size-3.5 shrink-0" />
                    Con DNI o RUC puedes autocompletar el nombre y la dirección desde RENIEC / SUNAT.
                </p>
            </div>
        </aside>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            // Con RUC el "apellido" no aplica: se oculta y el nombre pasa a ser la razón social
            const form = document.querySelector('[data-form-cliente]');
            const tipo = form.querySelector('[data-tipo-doc]');
            const apellido = form.querySelector('[data-campo-apellido]');
            const etiqueta = form.querySelector('[data-campo-nombre] label');
            const num = form.querySelector('[data-num-doc]');

            const ajustar = () => {
                const codigo = tipo.selectedOptions[0]?.dataset.codigo;
                const esRuc = codigo === '6';
                apellido.classList.toggle('hidden', esRuc);
                etiqueta.textContent = esRuc ? 'Razón social *' : 'Nombres *';
                num.maxLength = { '1': 8, '6': 11 }[codigo] ?? 15;
                num.inputMode = ['1', '6'].includes(codigo) ? 'numeric' : 'text';
            };
            tipo.addEventListener('change', ajustar);
            ajustar();

            // Autocompletar con RENIEC (DNI) / SUNAT (RUC): Laravel consulta APIPeru, el token no llega al navegador
            const URL_CONSULTA = @json(route('clientes.consulta', ['numero' => '__NUM__']));
            const boton = form.querySelector('[data-consultar]');
            const estado = form.querySelector('[data-consulta-estado]');
            const campo = (n) => form.querySelector(`[name="${n}"]`);
            const tonos = { ok: 'bg-emerald-50 text-emerald-700', error: 'bg-red-50 text-red-700', cargando: 'bg-brand-50 text-brand-700' };
            const mostrar = (tono, texto) => { estado.hidden = !texto; estado.className = `mt-2 rounded-lg px-3 py-2 text-xs ${tonos[tono] ?? ''}`; estado.textContent = texto; };

            const pintarBoton = () => {
                const codigo = tipo.selectedOptions[0]?.dataset.codigo;
                const valido = (codigo === '1' && /^\d{8}$/.test(num.value)) || (codigo === '6' && /^\d{11}$/.test(num.value));
                boton.hidden = !valido;
                form.querySelector('[data-consultar-texto]').textContent = codigo === '6' ? 'Autocompletar con SUNAT' : 'Autocompletar con RENIEC';
            };
            [num, tipo].forEach((el) => el.addEventListener('input', pintarBoton));
            tipo.addEventListener('change', pintarBoton);
            pintarBoton();

            boton.addEventListener('click', async () => {
                boton.disabled = true;
                mostrar('cargando', 'Consultando…');
                try {
                    const res = await fetch(URL_CONSULTA.replace('__NUM__', num.value), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        mostrar('error', res.status === 429 ? 'Hiciste muchas consultas seguidas: espera un momento.' : (data.errors ? Object.values(data.errors).flat()[0] : 'No se pudo consultar el documento.'));
                        return;
                    }
                    campo('nombre').value = data.nombre ?? '';
                    if (campo('apellido')) campo('apellido').value = data.apellido ?? '';
                    if (data.direccion) campo('direccion').value = data.direccion;
                    const extra = [data.estado, data.condicion].filter(Boolean).join(' · ');
                    mostrar('ok', data.origen === 'empresa'
                        ? 'Este documento ya es cliente de tu empresa.'
                        : `Datos obtenidos de ${num.value.length === 8 ? 'RENIEC' : 'SUNAT'}${extra ? ' · ' + extra : ''}.`);
                } catch {
                    mostrar('error', 'Sin conexión con el servidor.');
                } finally {
                    boton.disabled = false;
                }
            });
        })();
    </script>
@endpush
