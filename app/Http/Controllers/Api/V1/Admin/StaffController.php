<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $staff = User::role(['SuperAdmin', 'OperationsManager', 'FinanceUser'])
            ->with('roles', 'permissions')
            ->get();

        return response()->json(['data' => UserResource::collection($staff)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:8',
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        $user->assignRole($data['role']);

        if (! empty($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return response()->json(['data' => new UserResource($user->load('roles', 'permissions'))], 201);
    }

    public function updatePermissions(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user->syncPermissions($data['permissions']);

        return response()->json(['data' => new UserResource($user->load('roles', 'permissions'))]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => Permission::all()->map(fn ($p) => [
                'key' => $p->name,
                'label' => str_replace('-', ' ', ucwords($p->name, '-')),
            ]),
            'roles' => Role::all()->pluck('name'),
        ]);
    }
}
