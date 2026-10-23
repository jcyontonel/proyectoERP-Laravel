{{--
    EmpresaController@show
      $ficha [empresa, estado, limites, suscripciones, pagos]   $e = empresa
      $usuarios, $roles, $planes (activos con precios), $catalogos
--}}
@extends('plataforma::layouts.app')

@php
    $s = $e['suscripcion'] ?? null;
    $nombre = $e['nombre_comercial'] ?: $e['razon_social'];
    $metodos = $catalogos['metodos_pago'];
    $fecha = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->format('d/m/Y') : '—';
    $pendiente = collect($ficha['pagos'])->firstWhere('estado', 'pendiente');
@endphp

@section('title', $nombre)

@section('content')
    <x-plataforma::page-header :title="$nombre" :migas="['Empresas' => route('plataforma.empresas.index'), $nombre => null]"
        :subtitle="'RUC ' . $e['ruc'] . ' · ' . $e['razon_social'] . ($e['activo'] ? '' : ' · desactivada')">
        <x-slot:badge><x-plataforma::estado :valor="$ficha['estado']" /></x-slot:badge>
        <x-slot:acciones>
            <a href="{{ route('plataforma.empresas.edit', $e['id']) }}" class="btn btn-secondary"><x-plataforma::icon name="pencil" class="text-brand-600" /> Editar datos</a>
        </x-slot:acciones>
    </x-plataforma::page-header>

    <x-plataforma::flash />

    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-5">
            {{-- Consumo --}}
            <x-plataforma::card title="Consumo del plan" icon="bar-chart" subtitle="Comprobantes por mes calendario; productos, usuarios y almacenes, totales.">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($ficha['limites'] as $recurso => $l)
                        @php
                            // Solo los comprobantes del mes se "agotan"; productos, usuarios y almacenes en su tope es normal
                            $alerta = $recurso === 'comprobantes';
                            $lleno = $alerta && $l['maximo'] && $l['usado'] >= $l['maximo'];
                            $avance = $l['maximo'] ? min($l['usado'] / $l['maximo'], 1) : 0;
                            $l['fraccion'] = $alerta ? $l['fraccion'] : 0;
                        @endphp
                        <div>
                            <div class="flex items-baseline justify-between text-sm">
                                <span>{{ ucfirst($l['etiqueta']) }}</span>
                                <span @class(['font-semibold', 'text-red-600' => $lleno])>{{ number_format($l['usado']) }} <span class="font-normal text-slate-500">/ {{ $l['maximo'] !== null ? number_format($l['maximo']) : '∞' }}</span></span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div @class(['h-full rounded-full', 'bg-red-500' => $lleno, 'bg-amber-500' => ! $lleno && $l['fraccion'] >= 0.8, 'bg-brand-500' => $l['fraccion'] < 0.8])
                                    style="width: {{ $l['maximo'] ? max($avance * 100, 2) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-plataforma::card>

            {{-- Usuarios --}}
            <x-plataforma::card title="Usuarios" icon="users" id="usuarios" subtitle="Accesos al ERP de esta empresa. El rol aplica solo en esta empresa.">
                <x-slot:aside><button type="button" data-abrir="dlg-usuario" class="btn btn-secondary !h-8 !px-2.5 text-xs"><x-plataforma::icon name="plus" class="text-brand-600" /> Agregar</button></x-slot:aside>
                <ul class="divide-y divide-slate-100">
                    @forelse ($usuarios as $u)
                        <li @class(['flex flex-wrap items-center gap-3 py-3', 'opacity-60' => ! $u['activo']])>
                            <div class="min-w-48 flex-1">
                                <p class="font-medium">{{ trim($u['name'] . ' ' . $u['apellido']) }} @unless ($u['activo'])<x-plataforma::badge>Inactivo</x-plataforma::badge>@endunless</p>
                                <p class="text-xs text-slate-500">{{ $u['email'] }} @if ($u['empresas'] > 1)· en {{ $u['empresas'] }} empresas @endif</p>
                            </div>
                            <form method="POST" action="{{ route('plataforma.empresas.usuarios.update', [$e['id'], $u['id']]) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <select name="rol" class="field !h-8 !w-40 text-xs" aria-label="Rol de {{ $u['email'] }}" onchange="this.form.requestSubmit()">
                                    @foreach ($roles as $rol)
                                        <option value="{{ $rol }}" @selected($u['rol'] === $rol)>{{ $rol }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <div class="flex items-center gap-1">
                                <form method="POST" action="{{ route('plataforma.empresas.usuarios.update', [$e['id'], $u['id']]) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="activo" value="{{ $u['activo'] ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-secondary !h-8 !px-2.5 text-xs">{{ $u['activo'] ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                                <button type="button" data-abrir="dlg-clave-{{ $u['id'] }}" class="btn btn-secondary !h-8 !px-2.5 text-xs" title="Restablecer contraseña"><x-plataforma::icon name="lock" /></button>
                                <form method="POST" action="{{ route('plataforma.empresas.usuarios.destroy', [$e['id'], $u['id']]) }}" data-confirmar="¿Quitar el acceso de {{ $u['email'] }} a esta empresa? Su cuenta se conserva.">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="grid size-8 place-items-center rounded-md text-red-500 hover:bg-red-50" title="Quitar acceso" aria-label="Quitar acceso"><x-plataforma::icon name="x-circle" /></button>
                                </form>
                            </div>
                            <x-plataforma::dialogo id="dlg-clave-{{ $u['id'] }}" titulo="Restablecer contraseña" :descripcion="$u['email']" :action="route('plataforma.empresas.usuarios.password', [$e['id'], $u['id']])" method="PUT" boton="Restablecer">
                                <x-plataforma::input name="password" id="clave-{{ $u['id'] }}" label="Nueva contraseña (mín. 8)" type="text" :value="\Illuminate\Support\Str::password(10, symbols: false)" required minlength="8" autocomplete="off" />
                            </x-plataforma::dialogo>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-slate-500">Sin usuarios.</li>
                    @endforelse
                </ul>
            </x-plataforma::card>

            {{-- Pagos --}}
            <x-plataforma::card title="Pagos de la suscripción" icon="receipt">
                @if (empty($ficha['pagos']))
                    <p class="text-sm text-slate-500">Aún no hay cobros.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs text-slate-500">
                                <tr><th class="py-2 pr-3">Periodo</th><th class="px-3 py-2">Plan</th><th class="px-3 py-2 text-right">Monto</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Pago</th><th class="px-3 py-2">Comprobante</th><th class="py-2 pl-3"></th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($ficha['pagos'] as $p)
                                    <tr>
                                        <td class="whitespace-nowrap py-2 pr-3">{{ $fecha($p['periodo_desde']) }} – {{ $fecha($p['periodo_hasta']) }}</td>
                                        <td class="px-3 py-2">{{ $p['plan'] }} <span class="text-xs text-slate-500">{{ config('plataforma.periodicidades')[$p['periodicidad']] ?? '' }}</span></td>
                                        <td class="px-3 py-2 text-right">
                                            <x-plataforma::money :value="$p['monto']" />
                                            @if ($p['descuento'] > 0)<p class="text-[11px] text-emerald-700">−<x-plataforma::money :value="$p['descuento']" /> {{ $p['oferta'] }}</p>@endif
                                        </td>
                                        <td class="px-3 py-2"><x-plataforma::estado tipo="pago" :valor="$p['estado']" /></td>
                                        <td class="px-3 py-2 text-xs text-slate-600">
                                            @if ($p['fecha_pago']){{ $fecha($p['fecha_pago']) }} · {{ $metodos[$p['metodo']] ?? $p['metodo'] }}@if ($p['referencia']) · {{ $p['referencia'] }}@endif
                                                @if ($p['registrado_por'])<span class="block text-slate-400">por {{ $p['registrado_por'] }}</span>@endif
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs">@include('plataforma::pagos.partials.comprobante')</td>
                                        <td class="py-2 pl-3 text-right">
                                            @if ($p['estado'] !== 'anulado')
                                                <form method="POST" action="{{ route('plataforma.pagos.anular', $p['id']) }}" data-confirmar="¿Anular este {{ $p['estado'] === 'pagado' ? 'pago? La vigencia retrocederá ese periodo' : 'cobro pendiente' }}?">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-red-500 hover:text-red-600">Anular</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-plataforma::card>

            {{-- Historial --}}
            @if (count($ficha['suscripciones']) > 1)
                <x-plataforma::card title="Historial de planes" icon="history">
                    <ul class="space-y-2 text-sm">
                        @foreach ($ficha['suscripciones'] as $h)
                            <li class="flex flex-wrap items-center justify-between gap-2">
                                <span>{{ $h['plan']['nombre'] }} · {{ config('plataforma.periodicidades')[$h['periodicidad']] ?? $h['periodicidad'] }} <span class="text-xs text-slate-500">desde {{ $fecha($h['inicio']) }}</span></span>
                                <x-plataforma::estado :valor="$h['estado_efectivo']" />
                            </li>
                        @endforeach
                    </ul>
                </x-plataforma::card>
            @endif
        </div>

        {{-- Suscripción actual y acciones --}}
        <aside class="space-y-5">
            <x-plataforma::card title="Suscripción" icon="sparkles">
                @if ($s)
                    <p class="text-2xl font-bold">{{ $s['plan']['nombre'] }}</p>
                    <p class="text-sm text-slate-500">{{ config('plataforma.periodicidades')[$s['periodicidad']] ?? $s['periodicidad'] }} · <x-plataforma::money :value="$s['precio']" /></p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-600">Inicio</dt><dd>{{ $fecha($s['inicio']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">{{ $s['estado_efectivo'] === 'prueba' ? 'Prueba hasta' : 'Pagado hasta' }}</dt><dd class="font-semibold">{{ $fecha($s['vence_el']) }}</dd></div>
                        @if ($s['oferta'])<div class="flex justify-between"><dt class="text-slate-600">Oferta</dt><dd>{{ $s['oferta']['nombre'] }}</dd></div>@endif
                        @if ($s['motivo_suspension'])<div class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">Suspendida: {{ $s['motivo_suspension'] }}</div>@endif
                    </dl>
                    <form method="POST" action="{{ route('plataforma.empresas.suscripcion.update', [$e['id'], $s['id']]) }}" class="mt-4 border-t border-slate-100 pt-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="notas" value="{{ $s['notas'] }}">
                        <input type="hidden" name="renovacion_automatica" value="{{ $s['renovacion_automatica'] ? 0 : 1 }}">
                        <button type="submit" class="flex w-full items-center justify-between text-left text-sm">
                            <span>Cobro de renovación automático <span class="block text-[11px] text-slate-500">Se genera {{ $catalogos['dias_aviso'] }} días antes del vencimiento</span></span>
                            <x-plataforma::badge :tone="$s['renovacion_automatica'] ? 'success' : 'neutral'">{{ $s['renovacion_automatica'] ? 'Sí' : 'No' }}</x-plataforma::badge>
                        </button>
                    </form>

                    <div class="mt-4 grid gap-2">
                        <button type="button" data-abrir="dlg-pago" class="btn btn-primary w-full"><x-plataforma::icon name="wallet" /> Registrar pago</button>
                        <button type="button" data-abrir="dlg-plan" class="btn btn-secondary w-full"><x-plataforma::icon name="layers" class="text-brand-600" /> Cambiar de plan</button>
                        @unless ($pendiente)
                            <form method="POST" action="{{ route('plataforma.empresas.suscripcion.cobro', [$e['id'], $s['id']]) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary w-full"><x-plataforma::icon name="receipt" class="text-brand-600" /> Generar cobro del siguiente periodo</button>
                            </form>
                        @endunless
                        @if ($s['estado'] === 'suspendida')
                            <form method="POST" action="{{ route('plataforma.empresas.suscripcion.reactivar', [$e['id'], $s['id']]) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary w-full"><x-plataforma::icon name="check-circle" class="text-emerald-600" /> Reactivar</button>
                            </form>
                        @else
                            <button type="button" data-abrir="dlg-suspender" class="btn btn-secondary w-full"><x-plataforma::icon name="lock" class="text-amber-600" /> Suspender</button>
                        @endif
                        <button type="button" data-abrir="dlg-cancelar" class="w-full text-center text-xs font-semibold text-red-500 hover:text-red-600">Cancelar suscripción</button>
                    </div>
                @else
                    <p class="text-sm text-slate-500">La empresa no tiene plan: por ahora no tiene límites.</p>
                    <button type="button" data-abrir="dlg-plan" class="btn btn-primary mt-4 w-full"><x-plataforma::icon name="layers" /> Asignar plan</button>
                @endif
            </x-plataforma::card>

            <x-plataforma::card title="Datos" icon="building">
                <dl class="space-y-2 text-sm">
                    <x-plataforma::dato label="Dirección">{{ $e['direccion'] ?: '—' }}</x-plataforma::dato>
                    <x-plataforma::dato label="Teléfono">{{ $e['telefono'] ?: '—' }}</x-plataforma::dato>
                    <x-plataforma::dato label="Correo">{{ $e['email'] ?: '—' }}</x-plataforma::dato>
                    <x-plataforma::dato label="SUNAT">{{ $e['sunat_modo'] === 'produccion' ? 'Producción' : 'Beta' }}</x-plataforma::dato>
                    <x-plataforma::dato label="Cliente desde">{{ $fecha($e['creada']) }}</x-plataforma::dato>
                </dl>
            </x-plataforma::card>
        </aside>
    </div>

    {{-- Diálogos --}}
    <x-plataforma::dialogo id="dlg-usuario" titulo="Agregar usuario" descripcion="Si el correo ya tiene cuenta en el ERP, solo se le da acceso a esta empresa." :action="route('plataforma.empresas.usuarios.store', $e['id'])" boton="Agregar">
        <div class="grid gap-3 sm:grid-cols-2">
            <x-plataforma::input name="name" label="Nombres *" required maxlength="100" />
            <x-plataforma::input name="apellido" label="Apellidos" maxlength="100" />
            <x-plataforma::input name="email" label="Correo *" type="email" required maxlength="255" />
            <x-plataforma::select name="rol" label="Rol">
                @foreach ($roles as $rol)<option value="{{ $rol }}" @selected(old('rol', 'Administrador') === $rol)>{{ $rol }}</option>@endforeach
            </x-plataforma::select>
            <div class="sm:col-span-2"><x-plataforma::input name="password" id="usuario-password" label="Contraseña (solo si es nuevo)" type="text" :value="\Illuminate\Support\Str::password(10, symbols: false)" maxlength="100" autocomplete="off" /></div>
        </div>
    </x-plataforma::dialogo>

    <x-plataforma::dialogo id="dlg-plan" :titulo="$s ? 'Cambiar de plan' : 'Asignar plan'" :descripcion="$s ? 'La suscripción actual se cancela y la nueva empieza hoy (los días pagados no se trasladan).' : null" :action="route('plataforma.empresas.suscripcion.store', $e['id'])" boton="Guardar">
        <x-plataforma::select name="plan_id" id="plan-plan" label="Plan">
            @foreach ($planes as $p)<option value="{{ $p['id'] }}" @selected((string) old('plan_id') === (string) $p['id'])>{{ $p['nombre'] }} — @foreach (collect($p['precios'])->where('activo', true) as $pr){{ config('plataforma.periodicidades')[$pr['periodicidad']] }} S/ {{ number_format($pr['precio'], 2) }}@if (! $loop->last) · @endif @endforeach</option>@endforeach
        </x-plataforma::select>
        <x-plataforma::select name="periodicidad" id="plan-periodicidad" label="Periodicidad">
            @foreach (config('plataforma.periodicidades') as $valor => $etiqueta)<option value="{{ $valor }}" @selected(old('periodicidad', 'mensual') === $valor)>{{ $etiqueta }}</option>@endforeach
        </x-plataforma::select>
        <x-plataforma::input name="oferta" id="plan-oferta" label="Código o ID de oferta (opcional)" maxlength="30" />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="prueba" value="1" class="accent-brand-600"> Con periodo de prueba (si el plan lo tiene)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="pagado" value="1" class="accent-brand-600" checked> Ya pagó el primer periodo</label>
        <div class="grid grid-cols-2 gap-3">
            <x-plataforma::select name="metodo" id="plan-metodo" label="Método">@foreach ($metodos as $v => $et)<option value="{{ $v }}">{{ $et }}</option>@endforeach</x-plataforma::select>
            <x-plataforma::input name="referencia" id="plan-referencia" label="N.º de operación" maxlength="100" />
        </div>
        <x-plataforma::select name="comprobante" id="plan-comprobante" label="Comprobante del pago (se envía a SUNAT)">
            <option value="factura">Factura al RUC {{ $e['ruc'] }}</option>
            <option value="boleta">Boleta</option>
            <option value="ninguno">Sin comprobante</option>
        </x-plataforma::select>
    </x-plataforma::dialogo>

    @if ($s)
        <x-plataforma::dialogo id="dlg-pago" titulo="Registrar pago"
            :descripcion="$pendiente ? 'Cobro pendiente: S/ ' . number_format($pendiente['monto'], 2) . ' (' . $fecha($pendiente['periodo_desde']) . ' – ' . $fecha($pendiente['periodo_hasta']) . ').' : 'Se registra el siguiente periodo al precio actual del plan.'"
            :action="route('plataforma.empresas.pagos.store', $e['id'])" boton="Registrar pago">
            @if ($pendiente)<input type="hidden" name="pago_id" value="{{ $pendiente['id'] }}">@endif
            <div class="grid grid-cols-2 gap-3">
                <x-plataforma::select name="metodo" label="Método">@foreach ($metodos as $v => $et)<option value="{{ $v }}" @selected(old('metodo') === $v)>{{ $et }}</option>@endforeach</x-plataforma::select>
                <x-plataforma::input name="fecha_pago" label="Fecha de pago" type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" />
            </div>
            <x-plataforma::input name="referencia" label="N.º de operación" maxlength="100" />
            <x-plataforma::select name="comprobante" label="Comprobante electrónico (se envía a SUNAT)">
                <option value="factura">Factura al RUC {{ $e['ruc'] }}</option>
                <option value="boleta">Boleta</option>
                <option value="ninguno">Sin comprobante</option>
            </x-plataforma::select>
            <x-plataforma::input name="notas" label="Notas" maxlength="1000" />
        </x-plataforma::dialogo>

        <x-plataforma::dialogo id="dlg-suspender" titulo="Suspender suscripción" descripcion="La empresa podrá consultar su información, pero no emitir comprobantes ni registrar productos o usuarios." :action="route('plataforma.empresas.suscripcion.suspender', [$e['id'], $s['id']])" boton="Suspender" tono="danger">
            <x-plataforma::input name="motivo" id="suspender-motivo" label="Motivo *" required maxlength="255" placeholder="Falta de pago, pedido del cliente…" />
        </x-plataforma::dialogo>

        <x-plataforma::dialogo id="dlg-cancelar" titulo="Cancelar suscripción" descripcion="Termina la suscripción y anula su cobro pendiente. Para reactivar a la empresa tendrás que asignarle un plan nuevo." :action="route('plataforma.empresas.suscripcion.cancelar', [$e['id'], $s['id']])" boton="Cancelar suscripción" tono="danger">
            <x-plataforma::input name="motivo" id="cancelar-motivo" label="Motivo" maxlength="255" />
        </x-plataforma::dialogo>
    @endif
@endsection
