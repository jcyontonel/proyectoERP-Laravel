{{--
    Variables (CompraController@create):
      $empresa, $proveedores [id,nombre,doc,tipo_doc,contacto,telefono,email], $almacenes,
      $productos [id,codigo,nombre,costo,es_servicio,stocks[almacen_id=>stock]],
      $tiposDocumento, $tiposComprobante, $monedas, $metodosPago, $igv, $preciosIncluyenIgv, $controlarStock
--}}
@extends('layouts.app')

@section('title', 'Nueva compra')

@section('shortcuts')
    <span>F2 Buscar proveedor · F3 Buscar producto · Ctrl + Enter Registrar</span>
@endsection

@section('content')
    @php
        $jsProveedores = collect($proveedores)->values();
        $jsProductos   = collect($productos)->map(fn ($p) => [
            'id'          => data_get($p, 'id'),
            'nombre'      => (string) data_get($p, 'nombre', ''),
            'codigo'      => (string) data_get($p, 'codigo', ''),
            'costo'       => (float) data_get($p, 'costo', 0),
            'precio'      => (float) data_get($p, 'precio', 0),
            'unidad'      => data_get($p, 'unidad'),
            'lotes'       => (bool) data_get($p, 'lotes', false),
            'ultima'      => data_get($p, 'ultima'),
            'es_servicio' => (bool) data_get($p, 'es_servicio', false),
            'stocks'      => (object) data_get($p, 'stocks', []),
        ])->values();
    @endphp

    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('compras.index') }}" class="hover:text-brand-600">Compras</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">Nueva compra</span>
    </nav>

    <div class="mt-4">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Nueva compra</h1>
        <p class="mt-1.5 text-sm text-slate-500">Registra lo que compraste a tu proveedor: ingresará stock al almacén y quedará en el kardex.</p>
    </div>

    @if (collect($almacenes)->isEmpty())
        <x-alert tone="warning" class="mt-4">Esta empresa no tiene almacenes activos. Crea uno antes de registrar compras.</x-alert>
    @endif

    <div id="form-errors" role="alert" aria-live="assertive" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" {{ ($errors->any() || session('error')) ? '' : 'hidden' }}>
        @if (session('error'))
            <p>{{ session('error') }}</p>
        @endif
        @if ($errors->any())
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- DOM = móvil: proveedor → productos → comprobante → resumen. En lg se reubican. --}}
    <form id="compra-form" method="POST" action="{{ route('compras.store') }}" novalidate
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_21.5rem]">
        @csrf

        @include('compras.partials.proveedor',   ['class' => 'lg:col-start-1 lg:row-start-1'])
        @include('compras.partials.productos',   ['class' => 'lg:col-span-2 lg:col-start-1 lg:row-start-2'])
        @include('compras.partials.comprobante', ['class' => 'lg:col-start-2 lg:row-start-1'])
        @include('compras.partials.resumen')

        {{-- Productos como inputs ocultos (los regenera el JS en cada cambio) --}}
        <div id="productos-hidden" class="hidden"></div>
    </form>

    {{-- El alta rápida de proveedor es otro formulario: va FUERA del form principal --}}
    @include('compras.partials.modal-proveedor')
    @include('compras.partials.scripts')
@endsection
