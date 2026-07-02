<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerOrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = ProfileController::customerOrdersQuery($request->user())
            ->with(['merchant', 'targetCity'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => CustomerOrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($this->ownsOrder($request->user(), $order), 403);

        $order->load(['merchant', 'targetCity', 'events', 'rider']);

        return response()->json(['data' => new CustomerOrderResource($order)]);
    }

    private function ownsOrder($user, Order $order): bool
    {
        if ($order->customer_user_id === $user->id) {
            return true;
        }

        return $user->phone && $order->customer_phone === $user->phone;
    }
}
