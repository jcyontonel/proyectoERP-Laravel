<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\JsonResponse;


class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::with('roles')->get());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        $user->syncRoles($request->input('roles', []));
        return response()->json($user->load('roles'), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());
        $user->syncRoles($request->input('roles', []));
        return response()->json($user->load('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(null, 204);
    }
}
