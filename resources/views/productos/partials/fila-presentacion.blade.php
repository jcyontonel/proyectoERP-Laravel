{{-- Una presentación: 1 [unidad] = [factor] [unidad base] · precio propio opcional. $i: índice, $pr: valores --}}
<div data-fila-presentacion class="grid grid-cols-[1fr_auto] items-end gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-[1.3fr_1fr_1fr_auto_auto]">
    <div class="col-span-2 sm:col-span-1">
        <label class="field-label">Presentación</label>
        <select name="conversiones[{{ $i }}][unidad_id]" required class="field">
            <option value="">Unidad…</option>
            @foreach ($tiposUnidad as $u)
                <option value="{{ $u->id }}" @selected((string) ($pr['unidad_id'] ?? '') === (string) $u->id)>{{ $u->descripcion }}</option>
            @endforeach
        </select>
        @error("conversiones.$i.unidad_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="field-label">Equivale a (unid. base)</label>
        <input type="number" name="conversiones[{{ $i }}][factor]" value="{{ $pr['factor'] ?? '' }}" step="0.0001" min="0.0001" required placeholder="15" class="field">
        @error("conversiones.$i.factor") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="field-label">Precio propio (S/)</label>
        <input type="number" name="conversiones[{{ $i }}][precio_venta]" value="{{ $pr['precio_venta'] ?? '' }}" step="0.01" min="0" placeholder="Automático" class="field">
    </div>
    <label class="flex h-10 items-center gap-2 text-xs text-slate-600">
        <input type="hidden" name="conversiones[{{ $i }}][activo]" value="0">
        <input type="checkbox" name="conversiones[{{ $i }}][activo]" value="1" @checked($pr['activo'] ?? true) class="size-4 rounded border-slate-300 text-brand-600">
        Activa
    </label>
    <button type="button" data-quitar-presentacion aria-label="Quitar presentación" class="grid size-10 place-items-center rounded-lg text-red-500 hover:bg-red-50"><x-icon name="trash" /></button>
</div>
