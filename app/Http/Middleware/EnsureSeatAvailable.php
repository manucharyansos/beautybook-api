<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSeatAvailable
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $business = $user->business;
        if (!$business) return $next($request);

        // If we are activating an existing user, seat might increase if they are inactive
        // For create staff, seat will increase by 1.
        // We enforce *before* action.
        if (!$business->hasAvailableSeat()) {
            return response()->json([
                'message' => 'Seat limit reached. Please upgrade your plan.',
                'code' => 'seat_limit_reached',
                'limit' => $business->seatLimit(),
                'current' => $business->activeSeatCount(),
            ], 409);
        }

        return $next($request);
    }
}
