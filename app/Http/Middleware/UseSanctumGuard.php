<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseSanctumGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user('sanctum')) {
            auth()->guard('web')->setUser($user);
            auth()->shouldUse('web');
        }

        return $next($request);
    }
}
