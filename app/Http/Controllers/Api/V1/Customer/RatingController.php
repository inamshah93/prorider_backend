<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRating;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        abort_unless($this->ownsOrder($request->user(), $order), 403);
        abort_unless(($order->order_status?->value ?? $order->order_status) === 'delivered', 422, 'Order must be delivered.');

        $data = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $rating = DeliveryRating::updateOrCreate(
            ['order_id' => $order->id],
            [
                'customer_user_id' => $request->user()->id,
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
            ],
        );

        return response()->json(['data' => $rating]);
    }

    private function ownsOrder($user, Order $order): bool
    {
        if ($order->customer_user_id === $user->id) {
            return true;
        }

        return $user->phone && $order->customer_phone === $user->phone;
    }
}
