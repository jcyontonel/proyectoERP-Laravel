{{--
    Variable: $venta (Venta) con cargados: empresa, almacen, usuario, cliente.tipoDocumento, detalles.producto, detalles.tipoUnidad
--}}
@extends('layouts.app')

@section('title', 'Venta ' . $venta->numero_completo)

@section('content')
    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('inicio') }}" class="hover:text-brand-600">Inicio</a>
        <x-icon name="chevron-right" class="size-3" />
        <a href="{{ route('ventas.index') }}" class="hover:text-brand-600">Ventas</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">{{ $venta->numero_completo }}</span>
    </nav>

    @include('ventas.partials.show.encabezado')

    @foreach (['status' => 'success', 'error' => 'danger'] as $clave => $tono)
        @if (session($clave))
            <x-alert :tone="$tono" class="mt-4">{{ session($clave) }}</x-alert>
        @endif
    @endforeach

    @if ($venta->esAnulada())
        <x-alert tone="danger" class="mt-4">Este comprobante está <strong>anulado</strong>. El stock fue repuesto al almacén y el número queda registrado en la serie.</x-alert>
    @elseif ($venta->esBorrador())
        <x-alert tone="info" class="mt-4">Es un <strong>borrador</strong>: aún no tiene número definitivo ni afectó el stock. Edítalo para registrarlo.</x-alert>
    @endif

    {{-- DOM = móvil: productos → resumen → datos. En lg: [productos / datos] + resumen fijo a la derecha --}}
    <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] xl:grid-cols-[minmax(0,1fr)_24rem]">
        @include('ventas.partials.show.productos', ['class' => 'lg:col-start-1 lg:row-start-1'])
        @include('ventas.partials.show.resumen',   ['class' => 'lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start lg:sticky lg:top-24'])
        @include('ventas.partials.show.datos',     ['class' => 'lg:col-start-1 lg:row-start-2'])
    </div>

    @if ($venta->esAnulable())
        @include('ventas.partials.dialogo-anular')
    @endif
    @if ($venta->esCredito() && $venta->puedeRegistrarCobro())
        @can('registrar-pagos')
            @include('finanzas.partials.dialogo-pago', ['metodos' => config('ventas.metodos_pago'), 'metodoDefecto' => 'efectivo'])
        @endcan
    @endif
    {{-- Preferencias → imprimir al vender: el ticket se imprime desde un marco oculto --}}
    @if (session('imprimir_ticket'))
        <iframe src="{{ route('ventas.ticket', [$venta, 'auto' => 1]) }}" title="Ticket" aria-hidden="true" style="position:absolute;width:0;height:0;border:0"></iframe>
    @endif
@endsection
