{{-- PlanController@create / edit: $plan (array; nuevo = sin id) --}}
@extends('plataforma::layouts.app')

@php
    $editando = isset($plan['id']);
    $precios = collect($plan['precios'] ?? [])->keyBy('periodicidad');
@endphp

@section('title', $editando ? 'Editar ' . $plan['nombre'] : 'Nuevo plan')

@section('content')
    <x-plataforma::page-header :title="$editando ? 'Editar plan ' . $plan['nombre'] : 'Nuevo plan'" :migas="['Planes' => route('plataforma.planes.index'), ($editando ? $plan['nombre'] : 'Nuevo') => null]" />

    <x-plataforma::flash />

    <form method="POST" action="{{ $editando ? route('plataforma.planes.update', $plan['id']) : route('plataforma.planes.store') }}" class="mt-6 grid max-w-5xl gap-5 lg:grid-cols-2">
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-plataforma::card title="Plan" icon="layers">
            <div class="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <div class="sm:col-span-2">
                    @if ($editando)
                        <input type="hidden" name="producto" value="{{ $plan['producto'] }}">
                        <p class="field-label">Sistema</p>
                        <p class="text-sm font-semibold">{{ $plan['producto_nombre'] ?? $plan['producto'] }}</p>
                    @else
                        <x-plataforma::select name="producto" label="Sistema *" id="plan-producto">
                            @foreach ($productos as $pr)
                                <option value="{{ $pr['codigo'] }}" @selected(old('producto', $plan['producto']) === $pr['codigo'])>{{ $pr['nombre'] }}</option>
                            @endforeach
                        </x-plataforma::select>
                    @endif
                </div>
                <x-plataforma::input name="codigo" label="Código *" :value="$plan['codigo'] ?? ''" required maxlength="30" class="font-mono" placeholder="premium" />
                <x-plataforma::input name="nombre" label="Nombre *" :value="$plan['nombre'] ?? ''" required maxlength="60" />
                <div class="sm:col-span-2"><x-plataforma::input name="descripcion" label="Descripción" :value="$plan['descripcion'] ?? ''" maxlength="255" /></div>
                <x-plataforma::input name="dias_prueba" label="Días de prueba" type="number" min="0" max="90" :value="$plan['dias_prueba'] ?? 0" />
                <x-plataforma::input name="orden" label="Orden" type="number" min="0" :value="$plan['orden'] ?? 0" />
            </div>
            <div class="mt-5 border-t border-slate-100 pt-4">
                <x-plataforma::toggle name="activo" label="Plan activo" :checked="$plan['activo'] ?? true" hint="Desactivado: no se puede asignar a empresas (las que ya lo tienen lo conservan)." />
            </div>
        </x-plataforma::card>

        <div class="space-y-5">
            <x-plataforma::card title="Límites" icon="sliders" subtitle="Deja vacío para ilimitado.">
                <div class="grid grid-cols-2 gap-4">
                    <x-plataforma::input name="max_comprobantes_mes" label="Comprobantes al mes" type="number" min="0" :value="$plan['max_comprobantes_mes'] ?? ''" placeholder="Ilimitado" />
                    <x-plataforma::input name="max_productos" label="Productos" type="number" min="0" :value="$plan['max_productos'] ?? ''" placeholder="Ilimitado" />
                    <x-plataforma::input name="max_usuarios" label="Usuarios" type="number" min="1" :value="$plan['max_usuarios'] ?? ''" placeholder="Ilimitado" />
                    <x-plataforma::input name="max_almacenes" label="Almacenes" type="number" min="1" :value="$plan['max_almacenes'] ?? ''" placeholder="Ilimitado" />
                    <x-plataforma::input name="max_empresas" label="Empresas por suscripción" type="number" min="1" :value="$plan['max_empresas'] ?? 1" placeholder="Ilimitado" />
                </div>
            </x-plataforma::card>

            <x-plataforma::card title="Precios" icon="coins" subtitle="Deja vacío si el plan no se ofrece en esa periodicidad.">
                <div class="grid grid-cols-3 gap-4">
                    @foreach (config('plataforma.periodicidades') as $clave => $etiqueta)
                        @php $pr = $precios[$clave] ?? null; @endphp
                        <x-plataforma::input name="precios[{{ $clave }}]" id="precio-{{ $clave }}" :label="$etiqueta . ' (S/)'" type="number" step="0.01" min="0"
                            :value="old('precios.' . $clave, $pr && $pr['activo'] ? $pr['precio'] : '')" />
                    @endforeach
                </div>
                @error('precios')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-plataforma::card>

            {{-- Funcionalidades: las base del sistema van en todos sus planes; las opcionales las elige cada plan.
                 Las no incluidas no aparecen en el menú ni se pueden abrir. Solo se ve el grupo del sistema elegido. --}}
            <x-plataforma::card title="Funcionalidades" icon="list-checks" subtitle="Las de la base van en todos los planes del sistema; marca las opcionales que incluye este plan.">
                @php $actual = old('producto', $plan['producto'] ?? 'erp'); @endphp
                <input type="hidden" name="modulos_enviados" value="1">
                @foreach ($productos as $pr)
                    @php $marcados = old('modulos.' . $pr['codigo'], $actual === $pr['codigo'] ? ($plan['modulos'] ?? []) : []); @endphp
                    <div data-funcionalidades="{{ $pr['codigo'] }}" @class(['hidden' => $actual !== $pr['codigo']])>
                        <p class="text-xs font-medium text-slate-500">Base de {{ $pr['nombre'] }} (siempre incluidas)</p>
                        <p class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($pr['base'] as $b)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ $b['nombre'] }}</span>@endforeach
                        </p>
                        <p class="mt-4 text-xs font-medium text-slate-500">Opcionales</p>
                        <div class="mt-1 grid gap-2 sm:grid-cols-2">
                            @foreach ($pr['opcionales'] as $m)
                                <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-2.5 text-sm hover:bg-slate-50">
                                    <input type="checkbox" name="modulos[{{ $pr['codigo'] }}][]" value="{{ $m['clave'] }}" @checked(in_array($m['clave'], $marcados, true)) @disabled($actual !== $pr['codigo']) class="mt-0.5 accent-brand-600">
                                    <span><span class="font-medium">{{ $m['nombre'] }}</span><span class="block text-[11px] text-slate-500">{{ $m['descripcion'] }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @error('modulos')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-plataforma::card>

            @push('scripts')
                <script>
                    // Al cambiar de sistema solo se ven (y envían) sus funcionalidades opcionales
                    document.getElementById('plan-producto')?.addEventListener('change', (e) => {
                        document.querySelectorAll('[data-funcionalidades]').forEach((g) => {
                            const visible = g.dataset.funcionalidades === e.target.value;
                            g.classList.toggle('hidden', ! visible);
                            g.querySelectorAll('input').forEach((i) => { i.disabled = ! visible; });
                        });
                    });
                </script>
            @endpush

            <div class="flex justify-end gap-2">
                <a href="{{ route('plataforma.planes.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><x-plataforma::icon name="save" /> Guardar plan</button>
            </div>
        </div>
    </form>
@endsection
