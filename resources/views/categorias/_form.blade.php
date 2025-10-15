@csrf
<div class="space-y-6">

    {{-- Empresa --}}
    <input type="hidden" name="empresa_id" value="{{ $empresa->id }}">
   
    {{-- Nombre --}}
    <div>
        <label for="nombre" class="block font-semibold text-gray-700">Nombre</label>
        <input type="text" name="nombre" id="nombre"
               value="{{ old('nombre', $categoria->nombre ?? '') }}"
               class="w-full border border-gray-300 rounded-xl px-4 py-2 mt-1 focus:ring-2 focus:ring-blue-500 focus:outline-none">
        @error('nombre')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div><br>

    {{-- Descripción --}}
    <div>
        <label for="descripcion" class="block font-semibold text-gray-700">Descripción</label>
        <textarea name="descripcion" id="descripcion" rows="3"
                  class="w-full border border-gray-300 rounded-xl px-4 py-2 mt-1 focus:ring-2 focus:ring-blue-500 focus:outline-none">{{ old('descripcion', $categoria->descripcion ?? '') }}</textarea>
        @error('descripcion')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

</div>

{{-- Botones --}}
<div class="flex flex-col sm:flex-row justify-end gap-4 mt-10">
    <a href="{{ route('categorias.index') }}"
       class="flex-1 sm:flex-none text-center bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
        ← Volver
    </a>

    <button type="submit"
            class="flex-1 sm:flex-none bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl font-semibold transition-all shadow-md">
        💾 {{ isset($categoria) ? 'Actualizar' : 'Guardar' }}
    </button>
</div>
