@php
    $tipoRuc = collect($tiposDocumento)->firstWhere('codigo', '6') ?? collect($tiposDocumento)->first();
@endphp

<dialog id="modal-proveedor" aria-labelledby="titulo-nuevo-proveedor" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl p-0 text-ink shadow-xl backdrop:bg-slate-900/40">
    <form id="form-proveedor" method="POST" action="{{ route('proveedores.store') }}" class="p-5" novalidate>
        @csrf

        <h2 id="titulo-nuevo-proveedor" class="text-base font-semibold">Nuevo proveedor</h2>
        <p class="mt-1 text-xs text-slate-500">Alta rápida: podrás completar el resto de datos después, en Proveedores.</p>

        <div id="np-errores" hidden role="alert" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700"></div>

        <div class="mt-4 grid grid-cols-[8rem_minmax(0,1fr)] gap-3">
            <div>
                <label for="np_tipo" class="field-label">Documento</label>
                <div class="relative">
                    <select id="np_tipo" name="tipo_documento_id" class="field appearance-none pr-8">
                        @foreach ($tiposDocumento as $t)
                            <option value="{{ $t->id }}" @selected($tipoRuc && $t->id === $tipoRuc->id)>{{ $t->codigo === '6' ? 'RUC' : ($t->codigo === '1' ? 'DNI' : $t->descripcion) }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute right-2.5 top-3 text-slate-400" />
                </div>
            </div>
            <div>
                <label for="np_numero" class="field-label">Número</label>
                <input id="np_numero" name="numero_documento" type="text" maxlength="20" inputmode="numeric" autocomplete="off" class="field">
            </div>
        </div>

        <div class="mt-3">
            <label for="np_razon" class="field-label">Razón social o nombre</label>
            <input id="np_razon" name="razon_social" type="text" maxlength="255" autocomplete="off" class="field">
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <label for="np_telefono" class="field-label">Teléfono</label>
                <input id="np_telefono" name="telefono" type="text" maxlength="20" autocomplete="off" class="field">
            </div>
            <div>
                <label for="np_email" class="field-label">Correo</label>
                <input id="np_email" name="email" type="email" maxlength="255" autocomplete="off" class="field">
            </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" data-cerrar-proveedor class="btn btn-secondary">Cancelar</button>
            <button type="submit" id="np-guardar" class="btn btn-primary disabled:opacity-60">Guardar proveedor</button>
        </div>
    </form>
</dialog>
