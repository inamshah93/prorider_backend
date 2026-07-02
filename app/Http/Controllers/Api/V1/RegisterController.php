<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Merchant;
use App\Models\User;
use App\Services\CustomerOrderLinkService;
use App\Services\UserAccountService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function merchant(Request $request): JsonResponse
    {
        $phone = PhoneNormalizer::normalize($request->input('phone'));
        $request->merge(['phone' => $phone]);

        $existing = User::findByPhone($phone);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => $existing ? 'required|email' : 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'store_name' => 'required|string|max:255',
        ]);

        if ($existing) {
            $user = UserAccountService::enrollExistingUser($existing, $data, 'Merchant', $data['store_name']);

            return $this->authResponse($user);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'status' => UserStatus::Active,
        ]);
        $user->assignRole('Merchant');

        Merchant::create([
            'user_id' => $user->id,
            'store_name' => $data['store_name'],
        ]);

        return $this->authResponse($user);
    }

    public function customer(Request $request): JsonResponse
    {
        $phone = PhoneNormalizer::normalize($request->input('phone'));
        $request->merge(['phone' => $phone]);

        $existing = User::findByPhone($phone);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => $existing ? 'required|email' : 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($existing) {
            $user = UserAccountService::enrollExistingUser($existing, $data, 'Customer');

            return $this->authResponse($user);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'status' => UserStatus::Active,
        ]);
        $user->assignRole('Customer');

        CustomerOrderLinkService::linkOrdersToUser($user);

        return $this->authResponse($user);
    }

    private function authResponse(User $user): JsonResponse
    {
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('roles', 'merchant', 'riderProfile')),
        ], 201);
    }
}
