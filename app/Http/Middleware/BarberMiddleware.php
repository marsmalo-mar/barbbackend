<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BarberMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');

        if (!$user || $user->user_type !== 'barber') {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Barber access required.'], 403);
        }

        return $next($request);
    }
}

