<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderSettlement;
use App\Services\RiderSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private RiderSettlementService $settlements) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->settlements->walletSummary($request->user())]);
    }

    public function settlements(Request $request): JsonResponse
    {
        $items = RiderSettlement::query()
            ->where('rider_id', $request->user()->id)
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
}
