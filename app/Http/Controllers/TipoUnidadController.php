<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TipoUnidad;
use App\Http\Requests\StoreTipoUnidadRequest;
use App\Http\Requests\UpdateTipoUnidadRequest;
use Illuminate\Http\JsonResponse;


class TipoUnidadController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TipoUnidad::all());
    }

    public function store(StoreTipoUnidadRequest $request): JsonResponse
    {
        $unidad = TipoUnidad::create($request->validated());
        return response()->json($unidad, 201);
    }

    public function show(TipoUnidad $tipoUnidad): JsonResponse
    {
        return response()->json($tipoUnidad);
    }

    public function update(UpdateTipoUnidadRequest $request, TipoUnidad $tipoUnidad): JsonResponse
    {
        $tipoUnidad->update($request->validated());
        return response()->json($tipoUnidad);
    }

    public function destroy(TipoUnidad $tipoUnidad): JsonResponse
    {
        $tipoUnidad->delete();
        return response()->json(null, 204);
    }
}
