<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPartner
{
    /**
     * ተጠቃሚው አጋር (partner) መሆኑን ያረጋግጣል
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // የ user role መስክ ስም እንደ ፕሮጀክትዎ ይለያያል
        // ለምሳሌ: 'role' === 'partner' ወይም 'is_partner' === true
        if ($user->role !== 'partner' && ! $user->is_partner) {
            return response()->json([
                'message' => 'Access denied. Partner account required.',
            ], 403);
        }

        return $next($request);
    }
}