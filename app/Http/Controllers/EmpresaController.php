<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Http\Requests\StoreEmpresaRequest;
use App\Http\Requests\UpdateEmpresaRequest;
use Illuminate\Http\JsonResponse;

class EmpresaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Empresa::all());
    }

    public function store(StoreEmpresaRequest $request): JsonResponse
    {
        $empresa = Empresa::create($request->validated());
        return response()->json($empresa, 201);
    }

    public function show(Empresa $empresa): JsonResponse
    {
        return response()->json($empresa);
    }

    public function update(UpdateEmpresaRequest $request, Empresa $empresa): JsonResponse
    {
        $empresa->update($request->validated());
        return response()->json($empresa);
    }

    public function destroy(Empresa $empresa): JsonResponse
    {
        $empresa->delete();
        return response()->json(null, 204);
    }
}
