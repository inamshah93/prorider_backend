<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidOrderTransitionException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage() ?: 'Invalid order status transition.',
            'error' => 'invalid_order_transition',
        ], 422);
    }
}
