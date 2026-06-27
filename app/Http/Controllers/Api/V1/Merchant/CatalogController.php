<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->merchant->manual_saved_items ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['items' => 'required|array']);

        $request->user()->merchant->update(['manual_saved_items' => $data['items']]);

        return response()->json(['data' => $data['items']]);
    }
}
