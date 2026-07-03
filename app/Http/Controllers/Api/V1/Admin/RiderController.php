<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RiderProfileResource;
use App\Models\Order;
use App\Models\RiderDocument;
use App\Models\RiderProfile;
use App\Models\RiderSettlement;
use App\Models\User;
use App\Services\RiderSettlementService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RiderController extends Controller
{
    public function __construct(private RiderSettlementService $settlements) {}

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
        $phone = PhoneNormalizer::normalize($request->input('phone'));
        $request->merge(['phone' => $phone]);

        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
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

    public function map(Request $request): JsonResponse
    {
        $riders = RiderProfile::with(['user', 'assignedCity'])
            ->where('is_online', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->get()
            ->map(fn (RiderProfile $r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'name' => $r->user?->name,
                'phone' => $r->user?->phone,
                'city' => $r->assignedCity?->name,
                'lat' => (float) $r->current_lat,
                'lng' => (float) $r->current_lng,
                'cash_in_hand' => (float) ($r->cash_in_hand ?? 0),
            ]);

        return response()->json(['data' => $riders]);
    }

    public function documents(RiderProfile $rider): JsonResponse
    {
        $docs = RiderDocument::where('rider_profile_id', $rider->id)->latest()->get();

        return response()->json([
            'data' => $docs->map(fn (RiderDocument $d) => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'file_url' => url('storage/'.$d->file_path),
                'status' => $d->status,
                'rejection_reason' => $d->rejection_reason,
                'created_at' => $d->created_at,
            ]),
        ]);
    }

    public function assignCity(Request $request, RiderProfile $rider): JsonResponse
    {
        $data = $request->validate(['assigned_city_id' => 'required|exists:cities,id']);
        $rider->update($data);

        return response()->json(['data' => new RiderProfileResource($rider->load('user', 'assignedCity'))]);
    }

    public function updateOnlineStatus(Request $request, RiderProfile $rider): JsonResponse
    {
        $data = $request->validate(['is_online' => 'required|boolean']);
        $rider->update($data);

        return response()->json(['data' => new RiderProfileResource($rider->load('user', 'assignedCity'))]);
    }

    public function updateRiderUser(Request $request, RiderProfile $rider): JsonResponse
    {
        $user = $rider->user;
        abort_unless($user, 404);

        $phone = PhoneNormalizer::normalize($request->input('phone'));
        if ($phone !== null) {
            $request->merge(['phone' => $phone]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'required|string|max:20|unique:users,phone,'.$user->id,
        ]);

        $user->update($data);

        return response()->json(['data' => new RiderProfileResource($rider->fresh()->load('user', 'assignedCity'))]);
    }

    public function updateCommissionRate(Request $request, RiderProfile $rider): JsonResponse
    {
        $data = $request->validate([
            'commission_rate' => 'nullable|numeric|min:0|max:1',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $rate = array_key_exists('commission_rate', $data)
            ? $data['commission_rate']
            : (isset($data['commission_percent']) ? $data['commission_percent'] / 100 : null);

        $rider->update(['commission_rate' => $rate]);

        return response()->json([
            'data' => new RiderProfileResource($rider->load('user', 'assignedCity')),
            'effective_commission_rate' => $rider->fresh()->effectiveCommissionRate(),
        ]);
    }

    public function wallet(RiderProfile $rider): JsonResponse
    {
        $user = $rider->user;
        abort_unless($user, 404);

        return response()->json(['data' => $this->settlements->walletSummary($user)]);
    }

    public function settlements(RiderProfile $rider): JsonResponse
    {
        $items = RiderSettlement::query()
            ->where('rider_id', $rider->user_id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $items->map(fn (RiderSettlement $s) => [
                'id' => $s->id,
                'amount' => $s->amount,
                'cash_before' => $s->cash_before,
                'cash_after' => $s->cash_after,
                'notes' => $s->notes,
                'proof_url' => $s->proof_url,
                'created_at' => $s->created_at,
            ]),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function storeSettlement(Request $request, RiderProfile $rider): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
            'proof_image' => 'required|image|max:5120',
        ]);

        $settlement = $this->settlements->record(
            $rider->user,
            $request->user(),
            (float) $data['amount'],
            $request->file('proof_image'),
            $data['notes'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $settlement->id,
                'amount' => $settlement->amount,
                'cash_before' => $settlement->cash_before,
                'cash_after' => $settlement->cash_after,
                'notes' => $settlement->notes,
                'proof_url' => $settlement->proof_url,
                'created_at' => $settlement->created_at,
            ],
        ], 201);
    }

    public function orders(Request $request, RiderProfile $rider): JsonResponse
    {
        $query = Order::query()
            ->with(['merchant', 'rider', 'targetCity'])
            ->where('rider_id', $rider->user_id);

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

    public function orderStats(RiderProfile $rider): JsonResponse
    {
        $base = Order::query()->where('rider_id', $rider->user_id);

        $total = (clone $base)->count();
        $delivered = (clone $base)->where('order_status', 'delivered')->count();
        // Returned orders (RTO) and legacy cancelled proxy
        $returned = (clone $base)->whereIn('order_status', ['returned', 'cancelled'])->count();

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
