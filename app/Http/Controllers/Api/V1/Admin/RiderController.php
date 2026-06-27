<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RiderProfileResource;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RiderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RiderProfile::with(['user', 'assignedCity']);

        if ($request->boolean('online_only')) {
            $query->where('is_online', true);
        }

        $riders = $query->paginate(20);

        return response()->json([
            'data' => RiderProfileResource::collection($riders),
            'meta' => [
                'current_page' => $riders->currentPage(),
                'last_page' => $riders->lastPage(),
                'total' => $riders->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string',
            'password' => 'required|min:8',
            'assigned_city_id' => 'nullable|exists:cities,id',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);
        $user->assignRole('Rider');

        $profile = RiderProfile::create([
            'user_id' => $user->id,
            'assigned_city_id' => $data['assigned_city_id'] ?? null,
        ]);

        return response()->json(['data' => new RiderProfileResource($profile->load('user', 'assignedCity'))], 201);
    }

    public function approveDocuments(RiderProfile $rider): JsonResponse
    {
        $rider->update(['documents_verified' => true]);

        return response()->json(['data' => new RiderProfileResource($rider->load('user', 'assignedCity'))]);
    }

    public function assignCity(Request $request, RiderProfile $rider): JsonResponse
    {
        $data = $request->validate(['assigned_city_id' => 'required|exists:cities,id']);
        $rider->update($data);

        return response()->json(['data' => new RiderProfileResource($rider->load('user', 'assignedCity'))]);
    }
}
