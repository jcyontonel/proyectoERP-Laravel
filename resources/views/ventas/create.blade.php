{{--
    Variables que envía VentaController@create:
      $empresa       fila de empresas
      $correlativos  [['id','tipo','destipo','serie','numero','siguiente','requiere_ruc'], ...]
      $almacenes     filas de almacenes (id, nombre, es_principal)
      $clientes      id, nombre, numero_documento, email, telefono, tipo_documento_codigo, tipo_documento
      $productos     [['id','codigo','nombre','precio_unitario','es_servicio','stocks' => [almacen_id => stock]], ...]
      $metodosPago   ['efectivo' => 'Efectivo', ...]
    Opcionales: $fecha, $igv, $preciosIncluyenIgv, $controlarStock (por defecto salen de la tabla empresas)
      $venta   borrador que se está editando (con detalles cargados). Si existe, el formulario hace PUT a ventas.update
--}}
@extends('layouts.app')

@section('title', isset($venta) ? 'Editar borrador' : 'Nueva venta')

@section('shortcuts')
    <span>F2 Buscar cliente · F3 Buscar producto · Ctrl + Enter Registrar</span>
@endsection

@section('content')
    @php
        $venta              ??= null;
        $editando           = $venta !== null;
        $fecha              ??= now();
        $igv                ??= (float) data_get($empresa ?? null, 'igv', 18);
        $preciosIncluyenIgv ??= (bool) data_get($empresa ?? null, 'precios_incluyen_igv', true);
        $controlarStock     ??= (bool) data_get($empresa ?? null, 'controlar_stock', true);
        $correlativos       ??= [];
        $almacenes          ??= [];
        $clientes           ??= [];
        $productos          ??= [];
        $metodosPago        ??= config('ventas.metodos_pago');

        // ── Catálogos en formato ligero para el JS ──
        $jsClientes = collect($clientes)->map(function ($c) {
            $doc    = (string) data_get($c, 'numero_documento', '');
            $codigo = (string) data_get($c, 'tipo_documento_codigo', '');
            $tipo   = match (true) {
                $codigo === '1'     => 'DNI',
                $codigo === '6'     => 'RUC',
                $codigo === '4'     => 'CE',
                $codigo === '7'     => 'Pasaporte',
                strlen($doc) === 8  => 'DNI',
                strlen($doc) === 11 => 'RUC',
                default             => (string) data_get($c, 'tipo_documento', ''),
            };

            return [
                'id'       => data_get($c, 'id'),
                'nombre'   => (string) data_get($c, 'nombre', ''),
                'doc'      => $doc,
                'tipo_doc' => $tipo,
                'es_ruc'   => $codigo === '6' || strlen($doc) === 11,
                'email'    => data_get($c, 'email'),
                'telefono' => data_get($c, 'telefono'),
            ];
        })->values();

        $jsProductos = collect($productos)->map(fn ($p) => [
            'id'          => data_get($p, 'id'),
            'nombre'      => (string) data_get($p, 'nombre', ''),
            'codigo'      => (string) data_get($p, 'codigo', ''),
            'precio'      => (float) data_get($p, 'precio_unitario', 0),
            'es_servicio' => (bool) data_get($p, 'es_servicio', false),
            'stocks'      => (object) data_get($p, 'stocks', []),
            'unidad'      => data_get($p, 'unidad'),
            'decimal'     => (bool) data_get($p, 'decimal', false),
            // Stock extra que se obtiene partiendo el producto de origen (ej. kilos al abrir un saco)
            'fraccionable' => (object) data_get($p, 'fraccionable', []),
        ])->values();

        $jsCorrelativos = collect($correlativos)->values();

        $corrInicial = collect($correlativos)->firstWhere('id', old('correlativo_id'))
            ?? ($venta ? collect($correlativos)->firstWhere('tipo', $venta->tipo) : null)
            ?? collect($correlativos)->first();

        // Productos que se cargan al abrir: lo que el usuario envió (si hubo error) o las líneas del borrador
        $productosIniciales = old('productos', $venta
            ? $venta->detalles->map(fn ($d) => ['id' => $d->producto_id, 'cantidad' => (float) $d->cantidad])->all()
            : []);
    @endphp

    <nav aria-label="Ruta" class="flex items-center gap-2 text-xs text-slate-500">
        <a href="{{ route('ventas.index') }}" class="hover:text-brand-600">Ventas</a>
        <x-icon name="chevron-right" class="size-3" />
        <span class="text-ink">{{ $editando ? 'Editar borrador' : 'Nueva venta' }}</span>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-x-6 gap-y-1">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $editando ? 'Editar borrador' : 'Nueva venta' }}</h1>
                <span class="rounded-md bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">Borrador</span>
            </div>
            <p class="mt-1.5 text-sm text-slate-500">Completa los datos y registra tu venta.</p>
        </div>
        <p class="text-xs text-slate-500 sm:mt-1">
            @if ($editando) Borrador {{ $venta->numero_completo }} · se numerará al registrar · @endif
            N.º <span id="num-comprobante" class="font-medium text-ink">{{ $corrInicial ? $corrInicial['serie'] . '-' . $corrInicial['siguiente'] : 'Sin comprobante' }}</span>
            · {{ $fecha->translatedFormat('d M Y') }}
        </p>
    </div>

    @if (! $editando && old('cotizacion_id'))
        <x-alert tone="info" class="mt-4">
            Venta desde la cotización <a href="{{ route('cotizaciones.show', old('cotizacion_id')) }}" class="font-semibold underline">{{ old('cotizacion_ref', 'de origen') }}</a>:
            cliente, productos y descuento ya están cargados con los precios vigentes. Al registrarla, la cotización quedará como convertida.
        </x-alert>
    @endif

    @if (collect($correlativos)->isNotEmpty() && ! collect($correlativos)->whereIn('tipo', ['01', '03'])->count()
        && app(\App\Services\Facturacion\FacturacionElectronicaService::class)->activo())
        <x-alert tone="warning" class="mt-4">
            Solo tienes series de <strong>ticket</strong> activas: los tickets no se envían a SUNAT.
            Para emitir boletas o facturas electrónicas, activa las series B001 / F001 en
            <a href="{{ route('series.index') }}" class="font-semibold underline">Configuración → Series</a>.
        </x-alert>
    @endif

    @if (collect($correlativos)->isEmpty())
        <div role="alert" class="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-icon name="alert-triangle" class="mt-0.5" />
            Esta empresa no tiene comprobantes (correlativos) activos. Configúralos antes de registrar ventas.
        </div>
    @endif

    @if (($exigeCaja ?? false) && ! ($cajaAbierta ?? null))
        <x-alert tone="warning" class="mt-4">
            No tienes un turno de caja abierto: solo podrás guardar la venta como borrador.
            <a href="{{ route('caja.index') }}" class="font-semibold underline">Abrir caja</a>
        </x-alert>
    @elseif ($cajaAbierta ?? null)
        <p class="mt-3 flex items-center gap-2 text-xs text-slate-500">
            <span class="size-2 rounded-full bg-emerald-500"></span>
            Cobrando en {{ $cajaAbierta->caja?->nombre }} · turno desde las {{ $cajaAbierta->fecha_apertura->format('H:i') }}
        </p>
    @endif

    @foreach (['success', 'status'] as $flash)
        @if (session($flash))
            <div role="status" class="mt-4 flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700">
                <x-icon name="check-circle" /> {{ session($flash) }}
            </div>
        @endif
    @endforeach

    {{-- Errores del servidor y de validación en el navegador --}}
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

    {{-- Orden del DOM = móvil: cliente → productos → detalle → resumen. En lg se reubican. --}}
    <form id="venta-form" method="POST" action="{{ $editando ? route('ventas.update', $venta) : route('ventas.store') }}" novalidate
        class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_21.5rem]">
        @csrf
        @if ($editando) @method('PUT') @endif

        <input type="hidden" name="empresa_id" value="{{ data_get($empresa ?? null, 'id') }}">
        @if (! $editando && old('cotizacion_id'))
            {{-- Venta desde una cotización: al registrarla, la cotización queda convertida --}}
            <input type="hidden" name="cotizacion_id" value="{{ old('cotizacion_id') }}">
            <input type="hidden" name="cotizacion_ref" value="{{ old('cotizacion_ref') }}">
        @endif

        @include('ventas.partials.cliente',   ['class' => 'lg:col-start-1 lg:row-start-1'])
        @include('ventas.partials.productos', ['class' => 'lg:col-span-2 lg:col-start-1 lg:row-start-2'])
        @include('ventas.partials.detalle',   ['class' => 'lg:col-start-2 lg:row-start-1'])
        @include('ventas.partials.resumen')

        {{-- Productos como inputs ocultos (los regenera el JS en cada cambio) --}}
        <div id="productos-hidden" class="hidden"></div>
    </form>

    @include('ventas.partials.scripts')
@endsection
