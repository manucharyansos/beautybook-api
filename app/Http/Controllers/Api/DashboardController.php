<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Salon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $actor = $request->user();

        $salon = Salon::query()
            ->with(['subscription.plan'])
            ->findOrFail($actor->salon_id);

        // Billing
        $subscription = $salon->subscription;

        $billing = [
            'status' => $salon->billing_status,
            'plan' => $subscription?->plan?->name,
            'seats_limit' => $subscription?->plan?->seats,
            'trial_ends_at' => $subscription?->trial_ends_at,
            'period_ends_at' => $subscription?->current_period_ends_at,
        ];

        // Seats
        $activeSeatCount = $salon->seatUsers()->count();

        $seats = [
            'active' => $activeSeatCount,
            'limit' => $subscription?->plan?->seats,
        ];

        // Bookings stats
        $today = Carbon::today();
        $next7 = Carbon::today()->addDays(7);

        $todayCount = Booking::query()
            ->where('salon_id', $salon->id)
            ->whereDate('starts_at', $today)
            ->count();

        $next7Count = Booking::query()
            ->where('salon_id', $salon->id)
            ->whereBetween('starts_at', [$today, $next7])
            ->count();

        $upcoming = Booking::query()
            ->where('salon_id', $salon->id)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get(['id','client_name','starts_at','status']);

        return response()->json([
            'data' => [
                'billing' => $billing,
                'seats' => $seats,
                'stats' => [
                    'today_bookings' => $todayCount,
                    'next_7_days_bookings' => $next7Count,
                ],
                'upcoming' => $upcoming,
            ]
        ]);
    }
}
