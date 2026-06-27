<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CityNotServiceableException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage() ?: 'The specified city is not serviceable.',
            'error' => 'city_not_serviceable',
        ], 422);
    }
}
