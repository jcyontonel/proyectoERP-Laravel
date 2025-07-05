<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Impuesto;
use App\Http\Requests\StoreImpuestoRequest;
use App\Http\Requests\UpdateImpuestoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpuestoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Impuesto::all());
    }

    public function store(StoreImpuestoRequest $request): JsonResponse
    {
        $impuesto = Impuesto::create($request->validate());
        return response()->json($impuesto, 201);
    }

    public function update(UpdateImpuestoRequest $request, Impuesto $impuesto): JsonResponse
    {
        $impuesto->update($request->validate());
        return response()->json($impuesto);
    }

    public function destroy(Impuesto $impuesto): JsonResponse
    {
        $impuesto->delete();
        return response()->json(null, 204);
    }
}
