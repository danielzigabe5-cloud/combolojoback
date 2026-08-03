
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PlatformMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Get platform from header
        $platform = $request->header('X-Platform', 'web');
        
        // Get authenticated user
        $user = $request->user();
        
        // ===== Mobile App: Only User role allowed =====
        if ($platform === 'mobile' && $user) {
            // Check if user is NOT a regular user
            if (!$user->isUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ይህ አፕሊኬሽን ለተጠቃሚዎች ብቻ ነው',
                    'error' => 'Access denied for this platform',
                    'data' => [
                        'your_role' => $user->role,
                        'allowed_role' => 'user',
                        'suggestion' => 'Please use the web platform'
                    ]
                ], 403);
            }
        }
        
        // ===== Web: All roles allowed =====
        // No restriction for web platform
        
        return $next($request);
    }
}