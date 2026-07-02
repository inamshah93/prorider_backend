<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\CustomerOrderLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $ordersCount = CustomerOrderLinkService::ordersForUserQuery($user)->count();

        return response()->json([
            'user' => new UserResource($user),
            'stats' => [
                'orders_count' => $ordersCount,
            ],
        ]);
    }

    public static function customerOrdersQuery($user)
    {
        return CustomerOrderLinkService::ordersForUserQuery($user);
    }
}
