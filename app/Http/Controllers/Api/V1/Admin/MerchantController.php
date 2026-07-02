<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantResource;
use App\Http\Resources\OrderResource;
use App\Models\FinancialLedger;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MerchantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Merchant::with('user')->withCount('orders');

        if ($search = $request->get('search')) {
            $query->where('store_name', 'like', "%{$search}%");
        }

        $merchants = $query->paginate(20);

        return response()->json([
            'data' => MerchantResource::collection($merchants),
            'meta' => [
                'current_page' => $merchants->currentPage(),
                'last_page' => $merchants->lastPage(),
                'total' => $merchants->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string',
            'store_name' => 'required|string',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);
        $user->assignRole('Merchant');

        $merchant = Merchant::create([
            'user_id' => $user->id,
            'store_name' => $data['store_name'],
        ]);

        return response()->json(['data' => new MerchantResource($merchant->load('user'))], 201);
    }

    public function show(Merchant $merchant): JsonResponse
    {
        $ledger = FinancialLedger::where('merchant_id', $merchant->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => new MerchantResource($merchant->load('user')),
            'ledger' => $ledger,
            'payables' => FinancialLedger::where('merchant_id', $merchant->id)
                ->where('entry_type', 'merchant_payable')
                ->sum('amount'),
        ]);
    }

    public function connectShopify(Request $request, Merchant $merchant): JsonResponse
    {
        $data = $request->validate([
            'shopify_shop_url' => 'required|url',
            'shopify_access_token' => 'required|string',
        ]);

        $merchant->update($data);

        return response()->json(['data' => new MerchantResource($merchant)]);
    }

    public function updateDeliveryCharge(Request $request, Merchant $merchant): JsonResponse
    {
        $data = $request->validate([
            'delivery_charge' => 'nullable|numeric|min:0',
        ]);

        $merchant->update([
            'delivery_charge' => $data['delivery_charge'] ?? null,
        ]);

        return response()->json([
            'data' => new MerchantResource($merchant->load('user')),
            'effective_delivery_charge' => $merchant->fresh()->effectiveDeliveryCharge(),
            'platform_default' => PlatformSetting::defaultDeliveryCharge(),
        ]);
    }

    public function orders(Request $request, Merchant $merchant): JsonResponse
    {
        $query = Order::query()
            ->with(['merchant', 'rider', 'targetCity'])
            ->where('merchant_id', $merchant->id);

        if ($status = $request->get('status')) {
            $query->where('order_status', $status);
        }

        $orders = $query->latest()->paginate(20);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function orderStats(Merchant $merchant): JsonResponse
    {
        $base = Order::query()->where('merchant_id', $merchant->id);

        $total = (clone $base)->count();
        $delivered = (clone $base)->where('order_status', 'delivered')->count();
        // "Return" is currently represented as "cancelled" in OrderStatus enum.
        $returned = (clone $base)->where('order_status', 'cancelled')->count();

        $byStatus = (clone $base)
            ->selectRaw('order_status as status, COUNT(*) as cnt')
            ->groupBy('order_status')
            ->pluck('cnt', 'status')
            ->toArray();

        return response()->json([
            'data' => [
                'total' => $total,
                'delivered' => $delivered,
                'returned' => $returned,
                'by_status' => $byStatus,
            ],
        ]);
    }
}
