<div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $venta->numero_completo }}</h1>
            <x-estado-venta :estado="$venta->estado" />
        </div>
        <p class="mt-1.5 text-sm text-slate-500">
            {{ $venta->tipo_etiqueta }} · emitida el {{ $venta->fecha_emision->translatedFormat('d M Y') }}
            a las {{ $venta->fecha_emision->format('h:i A') }}
            @if ($venta->usuario) · por {{ $venta->usuario->name }} @endif
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('ventas.ticket', $venta) }}" target="_blank" rel="noopener" class="btn btn-secondary">
            <x-icon name="printer" class="text-brand-600" /> Imprimir ticket
        </a>

        @if ($venta->esEditable())
            <a href="{{ route('ventas.edit', $venta) }}" class="btn btn-secondary">
                <x-icon name="pencil" class="text-brand-600" /> Editar borrador
            </a>
        @endif

        @if ($venta->admiteNotas())
            @can('emitir-notas')
                <a href="{{ route('ventas.notas.create', [$venta, 'tipo' => '07']) }}" class="btn btn-secondary"><x-icon name="file-minus" class="text-brand-600" /> Nota de crédito</a>
                <a href="{{ route('ventas.notas.create', [$venta, 'tipo' => '08']) }}" class="btn btn-secondary"><x-icon name="plus-circle" class="text-brand-600" /> Nota de débito</a>
            @endcan
        @endif

        @if ($venta->esAnulable())
            <button type="button" data-anular="{{ route('ventas.anular', $venta) }}" data-numero="{{ $venta->numero_completo }}"
                class="btn btn-secondary text-red-600 hover:bg-red-50">
                <x-icon name="x-circle" /> Anular
            </button>
        @endif

        <a href="{{ route('ventas.create') }}" class="btn btn-primary"><x-icon name="plus" /> Nueva venta</a>
    </div>
</div>
