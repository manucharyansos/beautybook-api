<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['required','date_format:Y-m-d'],
            'to' => ['required','date_format:Y-m-d'],
        ]);

        $user = $request->user();

        $from = Carbon::parse($data['from'])->startOfDay();
        $to   = Carbon::parse($data['to'])->endOfDay();

        $query = Booking::query()
            ->with(['service:id,name','staff:id,name'])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at');

        // staff-ը տեսնում է միայն իր booking-ները
        if ($user->role === User::ROLE_STAFF) {
            $query->where('staff_id', $user->id);
        }

        $bookings = $query->get();

        // group by date
        $grouped = [];

        $period = Carbon::parse($from)->daysUntil($to);

        foreach ($period as $date) {
            $grouped[$date->format('Y-m-d')] = [];
        }

        foreach ($bookings as $booking) {
            $day = Carbon::parse($booking->starts_at)->format('Y-m-d');

            $grouped[$day][] = [
                'id' => $booking->id,
                'starts_at' => $booking->starts_at,
                'ends_at' => $booking->ends_at,
                'status' => $booking->status,
                'client_name' => $booking->client_name,
                'service' => $booking->service?->name,
                'staff' => $booking->staff?->name,
            ];
        }

        return response()->json(['data' => $grouped]);
    }
}
