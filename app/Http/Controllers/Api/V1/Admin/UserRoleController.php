<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->validate(['q' => 'required|string|min:3'])['q'];
        $phone = PhoneNormalizer::normalize($query);
        $variants = PhoneNormalizer::variants($query);

        $users = User::query()
            ->with(['roles', 'merchant'])
            ->where(function ($q) use ($query, $phone, $variants) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
                if ($variants !== []) {
                    $q->orWhereIn('phone', $variants);
                }
            })
            ->limit(10)
            ->get();

        return response()->json(['data' => UserResource::collection($users)]);
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
            'store_name' => 'nullable|string|max:255',
        ]);

        $allowed = [
            'Customer',
            'Merchant',
            'Rider',
            'OperationsManager',
            'FinanceUser',
            'SuperAdmin',
        ];

        $roles = array_values(array_intersect($data['roles'], $allowed));
        if ($roles === []) {
            return response()->json(['message' => 'No valid roles selected.'], 422);
        }

        if (in_array('Merchant', $roles, true) && ! $user->merchant && empty($data['store_name'])) {
            return response()->json([
                'message' => 'Store name is required when adding merchant access.',
                'errors' => ['store_name' => ['Store name is required when adding merchant access.']],
            ], 422);
        }

        $updated = UserAccountService::syncRoles($user, $roles, $data['store_name'] ?? null);

        return response()->json(['data' => new UserResource($updated)]);
    }
}
