<?php
// backend/app/Http/Middleware/RoleMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * ተጠቃሚው የሚፈቀደው ሚና እንዳለው ማረጋገጥ
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles  // የሚፈቀዱ ሚናዎች (ለምሳሌ: 'admin', 'partner')
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // የተረጋገጠ ተጠቃሚ ማግኘት
        $user = $request->user();

        // ተጠቃሚ ካልገባ
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'እባክዎ መጀመሪያ ይግቡ',
                'error' => 'Unauthenticated'
            ], 401); // HTTP 401 Unauthorized
        }

        // ተጠቃሚው ሚና ከሚፈቀዱ ሚናዎች ውስጥ ካልሆነ
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'ይህን ተግባር ለማድረግ ፍቃድ የለዎትም',
                'error' => 'Insufficient permissions',
                'data' => [
                    'your_role' => $user->role,        // ያለዎት ሚና
                    'allowed_roles' => $roles,         // የሚፈቀዱ ሚናዎች
                    'suggestion' => 'እባክዎ ትክክለኛውን መለያ ይጠቀሙ'
                ]
            ], 403); // HTTP 403 Forbidden
        }

        // ሁሉም ነገር ትክክል ከሆነ ጥያቄውን መቀጠል
        return $next($request);
    }
}