<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Correlativo;
use App\Http\Requests\StoreCorrelativoRequest;
use App\Http\Requests\UpdateCorrelativoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrelativoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Correlativo::all());
    }

    public function store(StoreCorrelativoRequest $request): JsonResponse
    {
        $correlativo = Correlativo::create($request->validate());
        return response()->json($correlativo, 201);
    }

    public function update(UpdateCorrelativoRequest $request, Correlativo $correlativo): JsonResponse
    {
        $correlativo->update($request->validated());
        return response()->json($correlativo);
    }

    public function destroy(Correlativo $correlativo): JsonResponse
    {
        $correlativo->delete();
        return response()->json(null, 204);
    }
}
