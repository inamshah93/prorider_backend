<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\CustomerOrderLinkService;
use App\Support\AppRole;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $phone = PhoneNormalizer::normalize($request->input('phone'));
        $request->merge(['phone' => $phone]);

        $request->validate([
            'phone' => 'required|string|max:20',
            'password' => 'required',
            'device_token' => 'nullable|string',
            'app' => 'nullable|in:merchant,rider',
        ]);

        $user = User::findByPhone($phone);

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'phone' => ['Your account is inactive.'],
            ]);
        }

        if ($app = $request->input('app')) {
            AppRole::assertForApp($user, $app);
        }

        if ($request->device_token) {
            $user->update(['device_token' => $request->device_token]);
        }

        CustomerOrderLinkService::linkOrdersToUser($user);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('roles', 'permissions', 'merchant', 'riderProfile')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(
                $request->user()->load('roles', 'permissions', 'merchant', 'riderProfile')
            ),
        ]);
    }

    public function updateDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_token' => 'required|string',
        ]);

        $request->user()->update(['device_token' => $data['device_token']]);

        return response()->json(['message' => 'Device token updated.']);
    }
}
