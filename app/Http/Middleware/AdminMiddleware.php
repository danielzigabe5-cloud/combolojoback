<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // ✅ ለፈተና - ሎግ መጻፍ
        \Log::info('AdminMiddleware called', [
            'user_id' => $user?->id,
            'user_role' => $user?->role,
            'authenticated' => $user ? 'Yes' : 'No'
        ]);
        
        if (!$user) {
            \Log::warning('AdminMiddleware: No user found');
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login first.'
            ], 401);
        }
        
        if ($user->role !== 'admin') {
            \Log::warning('AdminMiddleware: User is not admin', ['role' => $user->role]);
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only admins can access this page.',
                'user_role' => $user->role
            ], 403);
        }
        
        \Log::info('AdminMiddleware: Access granted', ['user_id' => $user->id]);
        return $next($request);
    }
}