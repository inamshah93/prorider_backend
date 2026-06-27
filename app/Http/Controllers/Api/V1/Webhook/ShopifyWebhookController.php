<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\ShopifyWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifyWebhookController extends Controller
{
    public function __construct(private ShopifyWebhookService $shopifyService) {}

    public function ordersCreate(Request $request): JsonResponse
    {
        $this->verifyShopifyHmac($request);

        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $merchant = Merchant::where('shopify_shop_url', 'like', "%{$shopDomain}%")->firstOrFail();

        $order = $this->shopifyService->handleOrderCreate($request->all(), $merchant);

        return response()->json(['data' => ['id' => $order->id, 'reference' => $order->order_reference_number]], 201);
    }

    private function verifyShopifyHmac(Request $request): void
    {
        $secret = config('prorider.shopify_webhook_secret');
        if (! $secret) {
            return;
        }

        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $calculated = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        abort_unless(hash_equals($calculated, $hmac ?? ''), 401, 'Invalid Shopify webhook signature.');
    }
}
