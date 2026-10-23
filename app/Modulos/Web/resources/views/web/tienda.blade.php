{{-- Página web → Mi tienda (TiendaController@edit): $tienda, $modalidad (catalogo|tienda), $culqi (modo, publica, secreta), $publicados --}}
@extends('layouts.app')

@php
    use App\Modulos\Web\Models\Tienda;
    $esTienda = $modalidad === 'tienda';
    $metodos = old('metodos_pago', $tienda->metodos_pago ?? []);
@endphp

@section('title', 'Mi tienda')

@section('content')
    <x-page-header title="Mi tienda" :migas="['Página web' => null, 'Mi tienda' => null]"
        :subtitle="$esTienda ? 'Plan Tienda: tus clientes ven tus productos, arman su carrito y te hacen pedidos.' : 'Plan Catálogo: tus clientes ven tus productos y te escriben por WhatsApp.'">
        <x-slot:acciones>
            <a href="{{ route('web.productos.index') }}" class="btn btn-secondary"><x-icon name="package" class="text-brand-600" /> Productos publicados ({{ $publicados }})</a>
            @if ($tienda->publicada)
                <a href="{{ route('web.tienda.ver') }}" target="_blank" rel="noopener" class="btn btn-primary"><x-icon name="external-link" /> Ver mi tienda</a>
            @endif
        </x-slot:acciones>
    </x-page-header>

    <x-flash />

    @if (! $publicados)
        <x-alert tone="warning" class="mt-4">Aún no publicaste productos: elige cuáles se ven en la web en <a href="{{ route('web.productos.index') }}" class="font-semibold underline">Productos publicados</a>.</x-alert>
    @endif

    <form method="POST" action="{{ route('web.tienda.update') }}" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        @method('PUT')

        <div class="space-y-5">
            <x-card title="Tu página" icon="globe" subtitle="Lo que ven tus clientes al entrar.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input name="slug" label="Dirección *" :value="$tienda->slug" required maxlength="60" pattern="[a-z0-9]+(-[a-z0-9]+)*"
                            :hint="'Tu tienda: ' . route('tienda.publica', old('slug', $tienda->slug))" />
                    </div>
                    <x-input name="titulo" label="Nombre visible" :value="$tienda->titulo" maxlength="120" :placeholder="$tienda->empresa->nombre_comercial ?: $tienda->empresa->razon_social" />
                    <x-input name="lema" label="Frase corta" :value="$tienda->lema" maxlength="160" placeholder="Todo para tu mascota, con envío a domicilio" />
                    <div class="sm:col-span-2"><x-textarea name="descripcion" label="Presentación" :value="$tienda->descripcion" rows="3" maxlength="2000" /></div>
                    <x-input name="whatsapp" label="WhatsApp" :value="$tienda->whatsapp" maxlength="15" inputmode="numeric" placeholder="51987654321" hint="Con código de país, sin espacios." />
                    <x-input name="correo" label="Correo de contacto" type="email" :value="$tienda->correo" maxlength="150" :placeholder="$tienda->empresa->email" />
                    <div>
                        <label for="color" class="field-label">Color de tu marca</label>
                        <input id="color" name="color" type="color" value="{{ old('color', $tienda->color) }}" class="h-10 w-20 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                    </div>
                </div>
                <div class="mt-5 space-y-4 border-t border-slate-100 pt-4">
                    <x-toggle name="mostrar_stock" label="Mostrar cuántas unidades quedan" :checked="$tienda->mostrar_stock" hint="Si no, solo se indica «Agotado» cuando no hay stock." />
                </div>
            </x-card>

            @if ($esTienda)
                <x-card title="Entrega" icon="truck" subtitle="Las zonas de envío y su costo se configuran en Zonas de envío.">
                    <div class="space-y-4">
                        <x-toggle name="recojo_en_tienda" label="Recojo en tienda" :checked="$tienda->recojo_en_tienda" />
                        <x-input name="direccion_recojo" label="Dirección de recojo" :value="$tienda->direccion_recojo" maxlength="255" :placeholder="$tienda->empresa->direccion" />
                        <x-input name="pedido_minimo" label="Pedido mínimo (S/)" type="number" step="0.01" min="0" :value="$tienda->pedido_minimo" />
                    </div>
                    <p class="mt-3 text-xs"><a href="{{ route('web.zonas.index') }}" class="font-semibold text-brand-600 hover:underline">Configurar zonas de envío →</a></p>
                </x-card>

                <x-card title="Pagos" icon="credit-card">
                    <fieldset>
                        <legend class="field-label">Métodos que aceptas</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (Tienda::METODOS_PAGO as $clave => $nombre)
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="metodos_pago[]" value="{{ $clave }}" @checked(in_array($clave, $metodos, true)) class="rounded border-slate-300 text-brand-600">
                                    {{ $nombre }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="mt-4"><x-textarea name="instrucciones_pago" label="Instrucciones de pago" :value="$tienda->instrucciones_pago" rows="3" maxlength="1000"
                        hint="Se muestran al confirmar el pedido: cuentas bancarias, número de Yape…" /></div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <p class="text-sm font-semibold">Pago con tarjeta (Culqi)</p>
                        <p class="mt-0.5 text-xs text-slate-500">El dinero llega a tu propia cuenta Culqi. En modo prueba no se cobra dinero real.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <x-select name="culqi_modo" label="Modo">
                                <option value="prueba" @selected(old('culqi_modo', $tienda->culqi_modo) === 'prueba')>Prueba (pk_test / sk_test)</option>
                                <option value="produccion" @selected(old('culqi_modo', $tienda->culqi_modo) === 'produccion')>Producción (pk_live / sk_live)</option>
                            </x-select>
                            <x-input name="culqi_llave_publica" label="Llave pública" :value="$tienda->culqi_llave_publica" maxlength="120" placeholder="pk_test_…" autocomplete="off" />
                            <div class="sm:col-span-2">
                                <x-input name="culqi_llave_secreta" label="Llave secreta" type="password" maxlength="120" autocomplete="new-password"
                                    :placeholder="filled($tienda->getRawOriginal('culqi_llave_secreta')) ? '•••••••• (guardada; escribe otra para cambiarla)' : 'sk_test_…'" />
                            </div>
                        </div>
                        <p class="mt-2 text-xs {{ $culqi['publica'] ? 'text-emerald-700' : 'text-amber-700' }}">
                            @if ($culqi['publica'])
                                Pago con tarjeta disponible en modo {{ $culqi['modo'] }}{{ blank($tienda->culqi_llave_publica) ? ' (con las llaves de prueba de la plataforma)' : '' }}.
                            @else
                                Pago con tarjeta no disponible: faltan las llaves {{ $culqi['modo'] === 'produccion' ? 'de producción' : 'de prueba' }}.
                            @endif
                        </p>
                        @if (filled($tienda->culqi_llave_publica))
                            <x-toggle name="quitar_culqi" label="Quitar mis llaves de Culqi" class="mt-3" />
                        @endif
                    </div>
                </x-card>
            @else
                <input type="hidden" name="culqi_modo" value="{{ $tienda->culqi_modo }}">
            @endif
        </div>

        <aside class="space-y-5">
            <x-card title="Publicación" icon="eye">
                <x-toggle name="publicada" label="Tienda publicada" :checked="$tienda->publicada" hint="Mientras no esté publicada, nadie puede verla." />
                <button type="submit" class="btn btn-primary mt-5 w-full"><x-icon name="save" /> Guardar</button>
            </x-card>
            @unless ($esTienda)
                <x-alert tone="info">Con el plan <strong>Tienda</strong> tus clientes pueden comprar en línea: carrito, pedidos, pago con tarjeta y envíos. Consulta con soporte para cambiar de plan.</x-alert>
            @endunless
        </aside>
    </form>
@endsection
