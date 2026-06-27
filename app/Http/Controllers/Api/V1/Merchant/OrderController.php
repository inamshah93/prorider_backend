<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\MerchantPrepStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderIntakeService;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderIntakeService $intakeService,
        private OrderStateMachine $stateMachine,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('merchant_id', $request->user()->merchant->id)
            ->with('targetCity')
            ->latest()
            ->paginate(20);

        return response()->json(['data' => OrderResource::collection($orders)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
            'delivery_address' => 'required|string',
            'city_name' => 'required|string',
            'parcel_weight' => 'nullable|numeric',
            'items' => 'required|array',
            'cod_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:cod,bank_transfer,manual',
        ]);

        $order = $this->intakeService->createManualOrder(
            $request->user()->merchant,
            $request->user(),
            $data,
        );

        return response()->json(['data' => new OrderResource($order)], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->merchant_id === $request->user()->merchant->id, 403);

        return response()->json([
            'data' => new OrderResource($order->load(['targetCity', 'events'])),
        ]);
    }

    public function generateLabel(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->merchant_id === $request->user()->merchant->id, 403);

        $order = $this->stateMachine->advancePrep(
            $order,
            MerchantPrepStatus::LabelGenerated,
            $request->user(),
        );

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function markPacked(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->merchant_id === $request->user()->merchant->id, 403);

        $order = $this->stateMachine->advancePrep(
            $order,
            MerchantPrepStatus::Packed,
            $request->user(),
        );

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function readyToShip(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->merchant_id === $request->user()->merchant->id, 403);

        $this->stateMachine->transition($order, OrderStatus::ReadyToShip, $request->user());

        return response()->json(['data' => new OrderResource($order->fresh())]);
    }
}
