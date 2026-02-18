<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function summary(Request $request)
    {
        $salonId = $request->user()->salon_id;

        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $prevStart = (clone $start)->subMonth()->startOfMonth();
        $prevEnd = (clone $prevStart)->endOfMonth();

        // ---- Monthly revenue grouped by currency (DONE only)
        $monthlyRevenueByCurrency = Booking::query()
            ->where('salon_id', $salonId)
            ->where('status', 'done')
            ->whereBetween('starts_at', [$start, $end])
            ->groupBy('currency')
            ->select('currency', DB::raw('SUM(final_price) as revenue'))
            ->orderBy('currency')
            ->get();

        $prevRevenueByCurrency = Booking::query()
            ->where('salon_id', $salonId)
            ->where('status', 'done')
            ->whereBetween('starts_at', [$prevStart, $prevEnd])
            ->groupBy('currency')
            ->select('currency', DB::raw('SUM(final_price) as revenue'))
            ->orderBy('currency')
            ->get()
            ->keyBy('currency');

        // ---- Growth % per currency
        $growth = $monthlyRevenueByCurrency->map(function ($row) use ($prevRevenueByCurrency) {
            $prev = (int)($prevRevenueByCurrency[$row->currency]->revenue ?? 0);
            $current = (int)$row->revenue;

            $pct = null;
            if ($prev > 0) {
                $pct = round((($current - $prev) / $prev) * 100, 2);
            }

            return [
                'currency' => $row->currency,
                'current' => $current,
                'previous' => $prev,
                'growth_percent' => $pct, // null եթե previous = 0
                'delta' => $current - $prev,
            ];
        })->values();

        // ---- Revenue per staff (DONE)
        $revenuePerStaff = Booking::query()
            ->join('users', 'bookings.staff_id', '=', 'users.id')
            ->where('bookings.salon_id', $salonId)
            ->where('bookings.status', 'done')
            ->whereBetween('bookings.starts_at', [$start, $end])
            ->groupBy('users.id', 'users.name', 'bookings.currency')
            ->select(
                'users.id',
                'users.name',
                'bookings.currency',
                DB::raw('SUM(bookings.final_price) as revenue')
            )
            ->orderByDesc('revenue')
            ->get();

        // ---- Revenue per service (DONE) ✅ (սա քո ուզած #2)
        $revenuePerService = Booking::query()
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->where('bookings.salon_id', $salonId)
            ->where('bookings.status', 'done')
            ->whereBetween('bookings.starts_at', [$start, $end])
            ->groupBy('services.id', 'services.name', 'bookings.currency')
            ->select(
                'services.id',
                'services.name',
                'bookings.currency',
                DB::raw('COUNT(bookings.id) as bookings_count'),
                DB::raw('SUM(bookings.final_price) as revenue')
            )
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // ---- Trend per day (bookings count)
        $trend = Booking::query()
            ->where('salon_id', $salonId)
            ->whereBetween('starts_at', [$start, $end])
            ->groupBy(DB::raw('DATE(starts_at)'))
            ->select(DB::raw('DATE(starts_at) as date'), DB::raw('COUNT(*) as total'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => [
                'month' => $month,
                'revenue_by_currency' => $monthlyRevenueByCurrency,
                'growth' => $growth,
                'revenue_per_staff' => $revenuePerStaff,
                'revenue_per_service' => $revenuePerService,
                'trend' => $trend,
            ]
        ]);
    }
}
