<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankWebhookController extends Controller
{
    public function __construct(private PaymentWebhookService $paymentService) {}

    public function paymentSuccess(Request $request): JsonResponse
    {
        $this->verifyBankSignature($request);

        $order = $this->paymentService->handleBankSuccess($request->all());

        return response()->json(['data' => ['order_id' => $order->id, 'payment_status' => $order->payment_status]]);
    }

    private function verifyBankSignature(Request $request): void
    {
        $secret = config('prorider.bank_webhook_secret');
        if (! $secret) {
            return;
        }

        $signature = $request->header('X-Bank-Signature');
        $calculated = hash_hmac('sha256', $request->getContent(), $secret);

        abort_unless(hash_equals($calculated, $signature ?? ''), 401, 'Invalid bank webhook signature.');
    }
}
