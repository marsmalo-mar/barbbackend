<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Token;
use App\Models\User;
use Carbon\Carbon;

class AuthToken
{
    public function handle(Request $request, Closure $next)
    {
        // let preflight pass
        if ($request->getMethod() === 'OPTIONS') {
            return response()->noContent(204);
        }

        $auth = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $token = $m[1];

        $row = Token::where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$row) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $user = User::find($row->user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        // attach as attributes (your controllers already read these)
        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_token', $token);

        return $next($request);
    }
}
