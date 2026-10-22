<div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $compra->numero_completo }}</h1>
            <x-estado-compra :estado="$compra->estado" />
            <x-estado-pago :estado="$compra->estadoPago()" />
        </div>
        <p class="mt-1.5 text-sm text-slate-500">
            {{ $compra->tipo_etiqueta }} de <strong class="font-medium text-ink">{{ $compra->proveedor?->nombre ?? '—' }}</strong>
            · emitida el {{ $compra->fecha_emision->translatedFormat('d M Y') }}
            @if ($compra->usuario) · registrada por {{ $compra->usuario->name }} @endif
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if ($compra->puedeRegistrarPago())
            <button type="button" data-abrir-pago class="btn btn-secondary">
                <x-icon name="wallet" class="text-brand-600" /> Registrar pago
            </button>
        @endif

        @if ($compra->esAnulable())
            <button type="button" data-anular="{{ route('compras.anular', $compra) }}" data-numero="{{ $compra->numero_completo }}"
                class="btn btn-secondary text-red-600 hover:bg-red-50">
                <x-icon name="x-circle" /> Anular
            </button>
        @endif

        <a href="{{ route('compras.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva compra</a>
    </div>
</div>
