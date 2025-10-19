{{-- resources/views/productos/_form.blade.php --}}

@if ($errors->any())
    <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-5 rounded-2xl mb-8 shadow-sm">
        <h3 class="text-2xl font-bold mb-3">⚠️ Se encontraron algunos errores:</h3>
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Empresa (solo lectura) --}}
<input type="hidden" name="empresa_id" value="{{ $empresa->id }}">

{{-- Categoría --}}
<div class="space-y-2">
    <label for="categoria_id" class="block text-xl font-semibold text-gray-800">Categoría</label>
    <select name="categoria_id" id="categoria_id"
            class="w-full p-4 border-2 rounded-2xl text-lg focus:ring-4 focus:ring-blue-400 focus:outline-none
                   @error('categoria_id') border-red-500 @else border-gray-300 @enderror">
        <option value="">-- Selecciona una categoría --</option>
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria->id }}"
                {{ old('categoria_id', $producto->categoria_id ?? '') == $categoria->id ? 'selected' : '' }}>
                {{ $categoria->nombre }}
            </option>
        @endforeach
    </select>
    @error('categoria_id')
        <p class="text-red-600 text-lg mt-1">⚠️ {{ $message }}</p>
    @enderror
</div>

{{-- Tipo de Unidad --}}
<div class="space-y-2">
    <label for="tipo_unidad_id" class="block text-xl font-semibold text-gray-800">Tipo de Unidad</label>
    <select name="tipo_unidad_id" id="tipo_unidad_id"
            class="w-full p-4 border-2 rounded-2xl text-lg focus:ring-4 focus:ring-blue-400 focus:outline-none
                   @error('tipo_unidad_id') border-red-500 @else border-gray-300 @enderror">
        <option value="">-- Selecciona tipo de unidad --</option>
        @foreach ($tiposUnidad as $tipo)
            <option value="{{ $tipo->id }}"
                {{ old('tipo_unidad_id', $producto->tipo_unidad_id ?? '') == $tipo->id ? 'selected' : '' }}>
                {{ $tipo->codigo }}
            </option>
        @endforeach
    </select>
    @error('tipo_unidad_id')
        <p class="text-red-600 text-lg mt-1">⚠️ {{ $message }}</p>
    @enderror
</div>

{{-- Código y Nombre --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-2">
        <label for="codigo" class="block text-xl font-semibold text-gray-800">Código</label>
        <input type="text" id="codigo" name="codigo"
               value="{{ old('codigo', $producto->codigo ?? '') }}"
               class="w-full p-4 border-2 rounded-2xl text-lg focus:ring-4 focus:ring-blue-400 focus:outline-none
                      @error('codigo') border-red-500 @else border-gray-300 @enderror"
               placeholder="Ej. PROD001">
        @error('codigo')
            <p class="text-red-600 text-lg mt-1">⚠️ {{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="nombre" class="block text-xl font-semibold text-gray-800">Nombre</label>
        <input type="text" id="nombre" name="nombre"
               value="{{ old('nombre', $producto->nombre ?? '') }}"
               required
               class="w-full p-4 border-2 rounded-2xl text-lg focus:ring-4 focus:ring-blue-400 focus:outline-none
                      @error('nombre') border-red-500 @else border-gray-300 @enderror"
               placeholder="Nombre del producto">
        @error('nombre')
            <p class="text-red-600 text-lg mt-1">⚠️ {{ $message }}</p>
        @enderror
    </div>
</div>

{{-- Descripción --}}
<div class="space-y-2">
    <label for="descripcion" class="block text-xl font-semibold text-gray-800">Descripción</label>
    <textarea id="descripcion" name="descripcion" rows="3"
              class="w-full p-4 border-2 rounded-2xl text-lg focus:ring-4 focus:ring-blue-400 focus:outline-none resize-none
                     @error('descripcion') border-red-500 @else border-gray-300 @enderror"
              placeholder="Agrega una descripción opcional...">{{ old('descripcion', $producto->descripcion ?? '') }}</textarea>
    @error('descripcion')
        <p class="text-red-600 text-lg mt-1">⚠️ {{ $message }}</p>
    @enderror
</div>

{{-- Precio y Stock --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-2">
        <label for="precio_unitario" class="block text-xl font-semibold text-gray-800">Precio Unitario (S/)</label>
        <input type="number" id="precio_unitario" name="precio_unitario" step="0.01" min="0"
               value="{{ old('precio_unitario', $producto->precio_unitario ?? '') }}"
               required
               class="w-full p-4 border-2 rounded-2xl text-lg focus:ring-4 focus:ring-blue-400 focus:outline-none
                      @error('precio_unitario') border-red-500 @else border-gray-300 @enderror">
        @error('precio_unitario')
            <p class="text-red-600 text-lg mt-1">⚠️ {{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="stock" class="block text-xl font-semibold text-gray-800">Stock</label>
        <input type="number" id="stock" name="stock" step="0.01" min="0"
               value="{{ old('stock', $producto->stock ?? '') }}"
               class="w-full p-4 border-2 rounded-2xl text-lg focus:ring-4 focus:ring-blue-400 focus:outline-none
                      @error('stock') border-red-500 @else border-gray-300 @enderror">
        @error('stock')
            <p class="text-red-600 text-lg mt-1">⚠️ {{ $message }}</p>
        @enderror
    </div>
</div>

{{-- Es servicio y activo --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
    <div class="flex items-center gap-3">
        <input type="checkbox" id="es_servicio" name="es_servicio" value="1"
               {{ old('es_servicio', $producto->es_servicio ?? false) ? 'checked' : '' }}
               class="w-6 h-6 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
        <label for="es_servicio" class="text-xl font-semibold">¿Es un servicio?</label>
    </div>

    <div class="flex items-center gap-3">
        <input type="checkbox" id="activo" name="activo" value="1"
               {{ old('activo', $producto->activo ?? true) ? 'checked' : '' }}
               class="w-6 h-6 text-green-600 border-gray-300 rounded focus:ring-green-500">
        <label for="activo" class="text-xl font-semibold">Activo</label>
    </div>
</div>
