<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $business = $user->business;
        if (!$business) {
            return response()->json([
                'message' => 'Business context required.',
                'code' => 'business_required',
            ], 403);
        }

        if (!($business->is_onboarding_completed ?? false)) {
            return response()->json([
                'message' => 'Onboarding is not completed.',
                'code' => 'onboarding_required',
            ], 403);
        }

        return $next($request);
    }
}
