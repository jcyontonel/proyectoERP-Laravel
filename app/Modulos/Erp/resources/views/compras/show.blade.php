{{--
    Variable: $compra (Compra) con: empresa, almacen, usuario, proveedor.tipoDocumento,
              detalles.producto/lote/tipoUnidad, pagos.usuario
--}}
@extends('layouts.app')

@section('title', 'Compra ' . $compra->numero_completo)

@section('content')
    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('inicio') }}" class="hover:text-brand-600">Inicio</a>
        <x-icon name="chevron-right" class="size-3" />
        <a href="{{ route('compras.index') }}" class="hover:text-brand-600">Compras</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">{{ $compra->numero_completo }}</span>
    </nav>

    @include('compras.partials.show.encabezado')

    @foreach (['status' => 'success', 'error' => 'danger'] as $clave => $tono)
        @if (session($clave))
            <x-alert :tone="$tono" class="mt-4">{{ session($clave) }}</x-alert>
        @endif
    @endforeach

    @if ($compra->esAnulada())
        <x-alert tone="danger" class="mt-4">Esta compra está <strong>anulada</strong>: el stock que ingresó fue devuelto, el kardex tiene el movimiento de reversa y sus pagos quedaron anulados.</x-alert>
    @endif

    {{-- DOM = móvil: productos → resumen y pagos → datos. En lg: [productos / datos] + columna derecha fija --}}
    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] xl:grid-cols-[minmax(0,1fr)_24rem]">
        @include('compras.partials.show.productos', ['class' => 'lg:col-start-1 lg:row-start-1'])

        <div class="space-y-5 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start lg:sticky lg:top-24">
            @include('compras.partials.show.resumen')
            @include('compras.partials.show.pagos')
        </div>

        @include('compras.partials.show.datos', ['class' => 'lg:col-start-1 lg:row-start-2'])
    </div>

    @if ($compra->esAnulable())
        @include('compras.partials.dialogo-anular')
    @endif
    @if ($compra->puedeRegistrarPago())
        @include('compras.partials.show.dialogo-pago')
    @endif
@endsection
