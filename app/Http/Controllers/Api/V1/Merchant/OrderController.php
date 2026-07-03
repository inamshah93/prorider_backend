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
        $query = Order::where('merchant_id', $request->user()->merchant->id)
            ->with('targetCity')
            ->latest();

        if ($status = $request->get('status')) {
            $query->where('order_status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_reference_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(20);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
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

    public function label(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->merchant_id === $request->user()->merchant->id, 403);
        abort_unless($order->awb_number, 422, 'Generate label first.');

        $order->load(['targetCity', 'merchant']);

        return response()->json([
            'data' => [
                'awb_number' => $order->awb_number,
                'order_reference' => $order->order_reference_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'delivery_address' => $order->delivery_address,
                'target_city' => $order->targetCity?->name,
                'cod_amount' => $order->cod_amount,
                'parcel_weight' => $order->parcel_weight,
                'store_name' => $order->merchant?->store_name,
                'label_text' => implode("\n", [
                    "AWB: {$order->awb_number}",
                    "Ref: {$order->order_reference_number}",
                    "To: {$order->customer_name}",
                    "Phone: {$order->customer_phone}",
                    "Address: {$order->delivery_address}",
                    "City: {$order->targetCity?->name}",
                    "COD: ₨{$order->cod_amount}",
                ]),
            ],
        ]);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orders' => 'required|array|min:1|max:50',
            'orders.*.customer_name' => 'required|string',
            'orders.*.customer_phone' => 'required|string',
            'orders.*.delivery_address' => 'required|string',
            'orders.*.city_name' => 'required|string',
            'orders.*.cod_amount' => 'required|numeric|min:0',
            'orders.*.parcel_weight' => 'nullable|numeric',
            'orders.*.items' => 'required|array',
        ]);

        $merchant = $request->user()->merchant;
        $created = [];

        foreach ($data['orders'] as $row) {
            $created[] = $this->intakeService->createManualOrder($merchant, $request->user(), $row);
        }

        return response()->json(['data' => OrderResource::collection(collect($created))], 201);
    }
}
