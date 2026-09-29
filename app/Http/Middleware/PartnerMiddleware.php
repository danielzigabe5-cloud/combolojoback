<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // የ role መስክ ስም እንደ ፕሮጀክትዎ ይለያያል
        $role = $user->role ?? null;

        if (! in_array($role, ['owner', 'partner'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Partner account required.',
            ], 403);
        }

        return $next($request);
    }
}