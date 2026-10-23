{{--
    SunatController@edit
      $empresa      Empresa (las claves nunca se muestran)
      $certificado  null | ['error' => …] | [titular, ruc, emisor, desde, hasta, vigente, dias]
      $requisitos   [[ok, texto, url]] para emitir en producción
--}}
@extends('layouts.app')

@section('title', 'Conexión SUNAT')

@section('content')
    <x-page-header title="Conexión SUNAT" :migas="['Configuración' => null, 'Conexión SUNAT' => null]"
        subtitle="Credenciales para emitir comprobantes electrónicos: usuario SOL secundario y certificado digital de la empresa.">
        <x-slot:badge>
            @if ($empresa->sunat_modo === 'produccion')
                <x-badge tone="success" dot>Producción</x-badge>
            @else
                <x-badge tone="warning" dot>Beta (pruebas)</x-badge>
            @endif
        </x-slot:badge>
    </x-page-header>

    <x-flash />

    <form method="POST" action="{{ route('config.sunat.update') }}" enctype="multipart/form-data" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @method('PUT')

        <div class="space-y-5">
            <x-card title="Ambiente" icon="upload-cloud">
                <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Ambiente">
                    @foreach (['beta' => ['Beta (pruebas)', 'Para probar: SUNAT acepta los comprobantes pero no tienen validez tributaria.'], 'produccion' => ['Producción', 'Comprobantes reales. Requiere todo lo de la lista de la derecha.']] as $valor => [$titulo, $texto])
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 has-checked:border-brand-500 has-checked:bg-brand-50">
                            <input type="radio" name="sunat_modo" value="{{ $valor }}" class="mt-0.5 accent-brand-600" @checked(old('sunat_modo', $empresa->sunat_modo) === $valor)>
                            <span><span class="block text-sm font-medium">{{ $titulo }}</span><span class="text-[11px] text-slate-500">{{ $texto }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('sunat_modo')<p role="alert" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</p>@enderror
            </x-card>

            <x-card title="Clave SOL" icon="lock" subtitle="Usa un usuario secundario creado en SUNAT Operaciones en Línea solo para facturación electrónica.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="sunat_usuario_sol" class="field-label">Usuario SOL</label>
                        <div class="flex items-center rounded-lg border border-slate-200 bg-slate-50 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                            <span class="px-3 font-mono text-xs text-slate-500">{{ $empresa->ruc }}</span>
                            <input id="sunat_usuario_sol" name="sunat_usuario_sol" value="{{ old('sunat_usuario_sol', $empresa->sunat_usuario_sol) }}" maxlength="50" autocomplete="off"
                                class="h-10 min-w-0 flex-1 rounded-r-lg border-0 bg-white px-3 font-mono text-sm uppercase focus:outline-none" placeholder="FACTURA1">
                        </div>
                        @error('sunat_usuario_sol')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <x-input name="sunat_clave_sol" label="Clave SOL" type="password" autocomplete="new-password" value=""
                        :hint="$empresa->sunat_clave_sol ? 'Guardada. Déjala vacía para mantenerla.' : 'Aún no registrada.'" />
                </div>
            </x-card>

            <x-card title="Certificado digital" icon="shield" subtitle="Archivo .pfx o .p12 con la clave privada, emitido para el RUC de la empresa.">
                @if ($certificado && empty($certificado['error']))
                    <dl class="mb-4 grid gap-3 rounded-lg bg-slate-50 p-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Titular</dt><dd class="font-medium">{{ $certificado['titular'] }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Emitido por</dt><dd class="font-medium">{{ $certificado['emisor'] }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Vigencia</dt><dd class="font-medium">{{ $certificado['desde']->format('d/m/Y') }} – {{ $certificado['hasta']->format('d/m/Y') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Estado</dt><dd>
                            @if (! $certificado['vigente'])
                                <x-badge tone="danger">Vencido</x-badge>
                            @elseif ($certificado['dias'] <= 30)
                                <x-badge tone="warning">Vence en {{ $certificado['dias'] }} días</x-badge>
                            @else
                                <x-badge tone="success">Vigente</x-badge>
                            @endif
                        </dd></div>
                    </dl>
                @elseif ($certificado)
                    <x-alert tone="danger" class="mb-4">{{ $certificado['error'] }}</x-alert>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="certificado" class="field-label">{{ $certificado ? 'Reemplazar certificado' : 'Archivo del certificado' }}</label>
                        <input id="certificado" name="certificado" type="file" accept=".pfx,.p12" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
                        @error('certificado')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <x-input name="certificado_clave" label="Contraseña del certificado" type="password" autocomplete="new-password" value="" hint="Se pide solo al subir un certificado." />
                </div>
                @if ($empresa->certificado_path)
                    <div class="mt-4 border-t border-slate-100 pt-4"><x-toggle name="quitar_certificado" label="Quitar el certificado actual" :checked="false" /></div>
                @endif
            </x-card>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start">
            <x-card title="Para emitir en producción" icon="list-checks">
                <ul class="space-y-2.5 text-sm">
                    @foreach ($requisitos as $r)
                        <li class="flex items-start gap-2">
                            <x-icon :name="$r['ok'] ? 'check-circle' : 'x-circle'" @class(['mt-0.5 size-4 shrink-0', 'text-emerald-600' => $r['ok'], 'text-slate-300' => ! $r['ok']]) />
                            <span @class(['text-slate-500' => ! $r['ok']])>
                                {{ ucfirst($r['texto']) }}
                                @if (! $r['ok'] && $r['url'])<a href="{{ $r['url'] }}" class="ml-1 text-xs font-semibold text-brand-600">Completar</a>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-[11px] leading-snug text-slate-500">Las claves se guardan cifradas y nunca se muestran. El envío de comprobantes se activa con el módulo de facturación electrónica.</p>
            </x-card>
            <button type="submit" class="btn btn-primary w-full"><x-icon name="save" /> Guardar</button>
        </aside>
    </form>
@endsection
