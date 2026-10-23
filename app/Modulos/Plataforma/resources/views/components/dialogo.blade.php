{{--
    Diálogo con formulario. Se abre con <button data-abrir="{{ $id }}">.
    <x-plataforma::dialogo id="pago" titulo="Registrar pago" :action="route(...)" boton="Registrar">…campos…</x-plataforma::dialogo>
    Si el servidor devuelve errores, se reabre solo este diálogo (campo oculto _dialogo).
--}}
@props(['id', 'titulo', 'action', 'method' => 'POST', 'boton' => 'Guardar', 'tono' => 'primary', 'descripcion' => null])

@php $abrir = old('_dialogo') === $id && $errors->any(); @endphp

<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-titulo" @if ($abrir) data-abrir-al-cargar @endif
    class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ $action }}" class="p-5">
        @csrf
        @if (strtoupper($method) !== 'POST') @method($method) @endif
        <input type="hidden" name="_dialogo" value="{{ $id }}">

        <h2 id="{{ $id }}-titulo" class="text-base font-semibold">{{ $titulo }}</h2>
        @if ($descripcion)<p class="mt-1 text-xs text-slate-500">{{ $descripcion }}</p>@endif

        @if ($abrir)
            <div role="alert" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <div class="mt-4 space-y-3">{{ $slot }}</div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-cerrar class="btn btn-secondary">Cancelar</button>
            <button type="submit" @class(['btn', 'btn-primary' => $tono === 'primary', 'btn-danger' => $tono === 'danger'])>{{ $boton }}</button>
        </div>
    </form>
</dialog>
