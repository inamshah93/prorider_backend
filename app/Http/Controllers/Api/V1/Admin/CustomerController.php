<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::role('Customer');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate(20);

        return response()->json([
            'data' => collect($customers->items())->map(function (User $user) {
                $ordersCount = Order::query()
                    ->where(function ($q) use ($user) {
                        $q->where('customer_user_id', $user->id);
                        if ($user->phone) {
                            $q->orWhere('customer_phone', $user->phone);
                        }
                    })
                    ->count();

                return array_merge(
                    (new UserResource($user))->resolve(),
                    ['orders_count' => $ordersCount],
                );
            })->values(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ]);
    }
}
