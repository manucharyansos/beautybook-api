<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(401);

        $businessId = $user->business_id;

        $today = now($user->timezone ?? 'Asia/Yerevan')->toDateString();

        $todayCount = Booking::query()
            ->where('business_id', $businessId)
            ->whereDate('starts_at', $today)
            ->count();

        $next7 = Booking::query()
            ->where('business_id', $businessId)
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->count();

        return response()->json([
            'data' => [
                'stats' => [
                    'today_bookings' => $todayCount,
                    'next_7_days_bookings' => $next7,
                ],
            ],
        ]);
    }
}
