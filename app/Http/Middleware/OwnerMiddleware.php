<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OwnerMiddleware
{
    /**
     * Verify that the authenticated user is a property owner
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login first.',
                'error' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->isOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Property owner access required to perform this action.',
                'error' => 'Insufficient permissions',
                'data' => [
                    'your_role' => $user->role,
                    'required_role' => 'owner',
                ]
            ], 403);
        }

        return $next($request);
    }
}
