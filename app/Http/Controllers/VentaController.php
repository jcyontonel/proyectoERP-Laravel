<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\Producto;
use App\Models\Correlativo;
use App\Http\Requests\StoreVentaRequest;
use App\Http\Requests\IndexVentaRequest;
use Illuminate\Http\JsonResponse;


class VentaController extends Controller
{
    public function index(IndexVentaRequest $request)
    {
         $query = Venta::with([
                        'cliente:id,nombre',
                        'user:id,name,apellido'
                    ])->whereHas('empresa', function ($q) {
                        $q->whereIn('id', auth()->user()->empresas->pluck('id'));
                    });

        // Filtro de fecha (solo un día, dentro de los últimos 7)
        $fecha = $request->filled('fecha') 
            ? Carbon::parse($request->fecha)->startOfDay() 
            : now()->startOfDay();

        // Asegurar que esté dentro de los últimos 7 días
        if ($fecha->lt(now()->subDays(7))) {
            $fecha = now()->subDays(7)->startOfDay();
        } elseif ($fecha->gt(now())) {
            $fecha = now()->startOfDay();
        }

        $query->whereBetween('fecha_emision', [$fecha, $fecha->copy()->endOfDay()]);

        // Filtro de estado (por defecto “Registrada”)
        $estado = $request->estado ?? 'Registrada';
        $query->where('estado', $estado);

        $ventas = $query
                    ->orderBy('fecha_emision', 'desc')
                    ->get(['id', 'cliente_id', 'user_id', 'fecha_emision', 'total']);

        return view('ventas.index', compact('ventas'));
    }

    public function create()
    {
        $empresa = auth()->user()
            ->empresas()
            ->with(['correlativos' => function ($query) {
                $query->select('id', 'empresa_id', 'serie')
                        ->limit(1); 
            }])
            ->select('empresas.id', 'empresas.razon_social')
            ->first();

        // Verificar que el usuario tenga una empresa asociada
        if (!$empresa)
           return redirect()->route('dashboard');

        $clientes = Cliente::get(['clientes.id', 'clientes.nombre', 'clientes.numero_documento']);
        $productos = Producto::where('empresa_id', $empresa->id)
                            ->get(['id', 'nombre', 'codigo', 'precio_unitario', 'stock']);

        return view('ventas.create', compact('empresa','clientes', 'productos'));
    }

    public function store(StoreVentaRequest $request)
    {
        $data = $request->validated();
        $productos = $request->input('productos', []);
        $user = auth()->user();
        $empresa_id = $request->input('empresa_id');
        $serie = $request->input('serie');
        $cliente = null;

        // Verificar que la empresa pertenece al usuario
        if (!$user->empresas->pluck('id')->contains($empresa_id)) {
            return redirect()->back()->with('error', 'La empresa seleccionada no pertenece al usuario.');
        }
        
        // Verificar que el cliente pertenece a la empresa
        if(isset($data['cliente_id'])) {
            $cliente = Cliente::whereHas('empresas', function ($query) use ($empresa_id) {
                $query->where('empresa_id', $empresa_id);
            })->where('id', $data['cliente_id'])->first();
            if (!$cliente) {
                // Asociar el cliente con la empresa si no existe
                $cliente = Cliente::find($data['cliente_id']);
                $cliente->empresas()->attach($empresa_id);
            }
        }

        // Verificar que el correlativo pertenece a la empresa
        $correlativo = Correlativo::where('empresa_id', $empresa_id)
            ->where('serie', $serie)
            ->first();
        if (!$correlativo) {
            return redirect()->back()->with('error', 'La serie seleccionada no pertenece a la empresa.');
        }

        // Usar el número actual y luego incrementarlo
        $nuevoNumero = $correlativo->numero + 1;

        // Guardar la venta principal
        $venta = Venta::create([
            'cliente_id' => $cliente->id ?? null,
            'empresa_id' => $empresa_id,
            'user_id' => $user->id,
            'serie' => $serie,
            'numero' => $nuevoNumero,
            'fecha_emision' => $data['fecha_emision'] ?? now(),
            'tipo' => $correlativo->tipo,
            'estado' => 'registrada',
            'subtotal' => $data['subtotal'] ?? 0,
            'total_impuestos' => $data['total_impuestos'] ?? 0,
            'total' => $data['total'],
            'metodo_pago' => $data['metodo_pago'] ?? 'efectivo',
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        // Actualizar el número en el correlativo
        $correlativo->numero = $nuevoNumero;
        $correlativo->save();
        // Guardar los detalles de la venta
        foreach ($productos as $detalle) {
            $venta->detalles()->create([
                'producto_id'     => $detalle['id'] ?? null,
                'tipo_unidad_id'  => $detalle['tipo_unidad_id'] ?? null,
                'descripcion'     => $detalle['descripcion'] ?? 'Producto sin descripción',
                'cantidad'        => $detalle['cantidad'],
                'precio_unitario' => $detalle['precio'],
                'subtotal'        => $detalle['subtotal'],
                'total_impuestos' => $detalle['total_impuestos'] ?? 0,
                'total'           => $detalle['subtotal'] + ($productos['total_impuestos'] ?? 0),
            ]);
        }

        return redirect()->route('ventas.show', $venta->id);
        //return view('ventas.resultado', [
        //    'venta' => $venta->load('cliente', 'empresa', 'detalles.producto')
        //]);
    }

    public function show(Venta $venta) {
        return view('ventas.show', [
            'venta' => $venta->load('cliente', 'empresa', 'user', 'detalles')
        ]);
    }

    public function update(UpdateVentaRequest $request, Venta $Venta): JsonResponse
    {
        $Venta->update($request->validated());
        return response()->json($Venta);
    }

    public function destroy(Venta $Venta): JsonResponse
    {
        $Venta->delete();
        return response()->json(null, 204);
    }
}
