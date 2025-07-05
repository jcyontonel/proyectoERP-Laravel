<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DetalleFactura;
use App\Http\Requests\StoreDetalleFacturaRequest;
use App\Http\Requests\UpdateDetalleFacturaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DetalleFacturaController extends Controller
{public function index(): JsonResponse
    {
        return response()->json(DetalleFactura::with(['factura', 'producto', 'impuesto'])->get());
    }

    public function store(StoreDetalleFacturaRequest $request): JsonResponse
    {
        $detalle = DetalleFactura::create($request->validated());
        return response()->json($detalle, 201);
    }

    public function destroy(DetalleFactura $detalle): JsonResponse
    {
        $detalle->delete();
        return response()->json(null, 204);
    }
}
