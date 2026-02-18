<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSalonIsBillable
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        // super admin bypass
        if (method_exists($user,'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        $salon = $user->salon;
        if (!$salon) return $next($request);

        if ($salon->billing_status === 'suspended') {
            return response()->json([
                'message' => 'Salon is suspended. Please contact support.'
            ], 402);
        }

        $sub = $salon->subscription;
        if (!$sub || !$sub->isActive()) {
            return response()->json([
                'message' => 'Subscription inactive or trial expired.'
            ], 402);
        }

        return $next($request);
    }
}
