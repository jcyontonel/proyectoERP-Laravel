<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Http\Requests\StoreFacturaRequest;
use App\Http\Requests\UpdateFacturaRequest;
use Illuminate\Http\JsonResponse;

class FacturaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Factura::with(['cliente', 'correlativo', 'detalles'])->get());
    }

    public function store(StoreFacturaRequest $request): JsonResponse
    {
        $factura = Factura::create($request->validated());
        return response()->json($factura, 201);
    }

    public function show(Factura $factura): JsonResponse
    {
        return response()->json($factura->load(['cliente', 'correlativo', 'detalles']));
    }

    public function update(UpdateFacturaRequest $request, Factura $factura): JsonResponse
    {
        $factura->update($request->validated());
        return response()->json($factura);
    }

    public function destroy(Factura $factura): JsonResponse
    {
        $factura->delete();
        return response()->json(null, 204);
    }
}
