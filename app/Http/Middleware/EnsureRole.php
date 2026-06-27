<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user && collect($roles)->intersect($user->getRoleNames())->isNotEmpty(),
            403,
            'Forbidden.',
        );

        return $next($request);
    }
}
