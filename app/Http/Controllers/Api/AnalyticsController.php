<?php
// app/Http/Controllers/Api/AnalyticsController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business; // Փոխել Salon-ից Business
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    private const REVENUE_STATUSES = ['confirmed', 'done'];

    private function getBusiness(Request $request): Business // Փոխել getSalon-ից getBusiness
    {
        $businessId = (int) ($request->query('business_id') ?? 0); // Փոխել salon_id-ից business_id
        if ($businessId) {
            return Business::query()->findOrFail($businessId); // Փոխել Salon-ից Business
        }

        $user = $request->user();
        if ($user && $user->business_id) { // Փոխել salon_id-ից business_id
            return Business::query()->findOrFail((int) $user->business_id); // Փոխել Salon-ից Business
        }

        if (app()->environment('local', 'development')) {
            $business = Business::query()->orderBy('id')->first(); // Փոխել Salon-ից Business
            if ($business) return $business;
        }

        abort(404, 'Business not found'); // Փոխել Salon not found-ից Business not found
    }

    /**
     * GET /api/analytics/overview
     */
    public function overview(Request $request)
    {
        $business = $this->getBusiness($request); // Փոխել $salon-ից $business

        $nowUtc = Carbon::now('UTC');

        $todayFromUtc = $nowUtc->copy()->startOfDay();
        $todayToUtc   = $nowUtc->copy()->endOfDay();

        $weekFromUtc  = $nowUtc->copy()->subDays(6)->startOfDay();
        $weekToUtc    = $nowUtc->copy()->endOfDay();

        $base = Booking::query()->where('business_id', $business->id); // Փոխել salon_id-ից business_id

        // Today's stats
        $todayBookings = (clone $base)
            ->whereBetween('starts_at', [$todayFromUtc, $todayToUtc])
            ->count();

        $todayRevenue = (float) (clone $base)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('starts_at', [$todayFromUtc, $todayToUtc])
            ->sum('final_price');

        // Last 7 days stats
        $weekBookings = (clone $base)
            ->whereBetween('starts_at', [$weekFromUtc, $weekToUtc])
            ->count();

        $weekRevenue = (float) (clone $base)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('starts_at', [$weekFromUtc, $weekToUtc])
            ->sum('final_price');

        // Unique clients in last 7 days (client_id-ով, ոչ թե client_phone-ով)
        $uniqueClients = (clone $base)
            ->whereBetween('starts_at', [$weekFromUtc, $weekToUtc])
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');

        // Last 7 days trend
        $daily = (clone $base)
            ->select(
                DB::raw('DATE(starts_at) as date'),
                DB::raw('COUNT(*) as bookings'),
                DB::raw("SUM(CASE WHEN status IN ('confirmed','done') THEN COALESCE(final_price,0) ELSE 0 END) as revenue")
            )
            ->whereBetween('starts_at', [$weekFromUtc, $weekToUtc])
            ->groupBy(DB::raw('DATE(starts_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $nowUtc->copy()->subDays($i)->toDateString();
            $trend[] = [
                'date' => $date,
                'bookings' => (int) ($daily[$date]->bookings ?? 0),
                'revenue' => (float) ($daily[$date]->revenue ?? 0),
            ];
        }

        // Business type info
        $businessType = $business->business_type ?? 'salon';

        return response()->json([
            'data' => [
                'business' => [ // Փոխել salon-ից business
                    'id' => $business->id,
                    'name' => $business->name,
                    'type' => $businessType,
                ],
                'today' => [
                    'bookings' => $todayBookings,
                    'revenue' => $todayRevenue,
                ],
                'last_7_days' => [
                    'bookings' => $weekBookings,
                    'revenue' => $weekRevenue,
                    'unique_clients' => $uniqueClients,
                ],
                'trend' => $trend,
                'currency' => 'AMD',
            ],
        ]);
    }

    /**
     * GET /api/analytics/revenue?months=12
     */
    public function revenue(Request $request)
    {
        $business = $this->getBusiness($request); // Փոխել $salon-ից $business

        $months = (int) ($request->query('months') ?? 12);
        $months = max(1, min(36, $months));

        $toUtc = Carbon::now('UTC')->endOfDay();
        $fromUtc = Carbon::now('UTC')->subMonths($months - 1)->startOfMonth();

        // Get all bookings and group in PHP
        $bookings = Booking::query()
            ->where('business_id', $business->id) // Փոխել salon_id-ից business_id
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('starts_at', [$fromUtc, $toUtc])
            ->get(['starts_at', 'final_price']);

        // Initialize all months with zero
        $monthlyData = [];
        $cursor = $fromUtc->copy()->startOfMonth();
        for ($i = 0; $i < $months; $i++) {
            $ym = $cursor->format('Y-m');
            $monthlyData[$ym] = ['revenue' => 0, 'bookings' => 0];
            $cursor->addMonth();
        }

        // Aggregate data
        foreach ($bookings as $booking) {
            $ym = $booking->starts_at->format('Y-m');
            if (isset($monthlyData[$ym])) {
                $monthlyData[$ym]['revenue'] += (float) ($booking->final_price ?? 0);
                $monthlyData[$ym]['bookings']++;
            }
        }

        // Format result
        $result = [];
        foreach ($monthlyData as $ym => $data) {
            $result[] = [
                'year_month' => $ym,
                'revenue' => $data['revenue'],
                'bookings' => $data['bookings'],
            ];
        }

        return response()->json([
            'data' => [
                'months' => $result,
                'currency' => 'AMD',
            ],
        ]);
    }

    /**
     * GET /api/analytics/services?days=28
     */
    public function services(Request $request)
    {
        $business = $this->getBusiness($request); // Փոխել $salon-ից $business

        $days = (int) ($request->query('days') ?? 28);
        $days = max(7, min(365, $days));

        $toUtc = Carbon::now('UTC')->endOfDay();
        $fromUtc = Carbon::now('UTC')->subDays($days - 1)->startOfDay();

        // վերցնում ենք bookings-ը (service_id, final_price)
        $bookings = Booking::query()
            ->where('business_id', $business->id) // Փոխել salon_id-ից business_id
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('starts_at', [$fromUtc, $toUtc])
            ->get(['service_id', 'final_price']);

        // aggregate ըստ service_id
        $agg = []; // [service_id => ['bookings'=>x,'revenue'=>y]]
        foreach ($bookings as $b) {
            $sid = (int) $b->service_id;
            if (!$sid) continue;

            if (!isset($agg[$sid])) $agg[$sid] = ['bookings' => 0, 'revenue' => 0.0];
            $agg[$sid]['bookings']++;
            $agg[$sid]['revenue'] += (float) ($b->final_price ?? 0);
        }

        // վերցնում ենք ծառայությունների անունները մեկ query-ով
        $serviceIds = array_keys($agg);
        $servicesMap = Service::query()
            ->whereIn('id', $serviceIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        // ձևավորում ենք rows-ը
        $rows = [];
        foreach ($agg as $sid => $data) {
            $service = $servicesMap->get($sid);
            $rows[] = [
                'service_id' => $sid,
                'service_name' => $service?->name ?? 'Deleted Service',
                'bookings' => (int) $data['bookings'],
                'revenue' => (float) $data['revenue'],
            ];
        }

        // sort by bookings desc
        usort($rows, fn($a, $b) => $b['bookings'] <=> $a['bookings']);

        // limit 10
        $rows = array_slice($rows, 0, 10);

        return response()->json([
            'data' => [
                'top' => $rows,
                'currency' => 'AMD',
            ],
        ]);
    }

    /**
     * GET /api/analytics/staff?days=28
     */
    public function staff(Request $request)
    {
        $business = $this->getBusiness($request); // Փոխել $salon-ից $business

        $days = (int) ($request->query('days') ?? 28);
        $days = max(7, min(365, $days));

        $toUtc = Carbon::now('UTC')->endOfDay();
        $fromUtc = Carbon::now('UTC')->subDays($days - 1)->startOfDay();

        // վերցնում ենք bookings-ը (staff_id, final_price)
        $bookings = Booking::query()
            ->where('business_id', $business->id) // Փոխել salon_id-ից business_id
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('starts_at', [$fromUtc, $toUtc])
            ->whereNotNull('staff_id')
            ->get(['staff_id', 'final_price']);

        // aggregate ըստ staff_id
        $agg = []; // [staff_id => ['bookings'=>x,'revenue'=>y]]
        foreach ($bookings as $b) {
            $uid = (int) $b->staff_id;
            if (!$uid) continue;

            if (!isset($agg[$uid])) $agg[$uid] = ['bookings' => 0, 'revenue' => 0.0];
            $agg[$uid]['bookings']++;
            $agg[$uid]['revenue'] += (float) ($b->final_price ?? 0);
        }

        // մեկ query-ով վերցնում ենք staff անունները
        $staffIds = array_keys($agg);
        $staffMap = User::query()
            ->whereIn('id', $staffIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $rows = [];
        foreach ($agg as $uid => $data) {
            $staff = $staffMap->get($uid);
            $rows[] = [
                'staff_id' => $uid,
                'staff_name' => $staff?->name ?? 'Deleted Staff',
                'bookings' => (int) $data['bookings'],
                'revenue' => (float) $data['revenue'],
            ];
        }

        usort($rows, fn($a, $b) => $b['bookings'] <=> $a['bookings']);

        return response()->json([
            'data' => [
                'rows' => $rows,
                'currency' => 'AMD',
            ],
        ]);
    }

    /**
     * GET /api/analytics/summary
     * Արագ summary dashboard-ի համար
     */
    public function summary(Request $request)
    {
        $business = $this->getBusiness($request);

        $now = Carbon::now('UTC');
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();

        $base = Booking::query()->where('business_id', $business->id);

        // Today
        $todayBookings = (clone $base)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->count();

        $todayRevenue = (clone $base)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->sum('final_price');

        // This month
        $monthBookings = (clone $base)
            ->where('starts_at', '>=', $monthStart)
            ->count();

        $monthRevenue = (clone $base)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->where('starts_at', '>=', $monthStart)
            ->sum('final_price');

        // Total
        $totalBookings = (clone $base)->count();
        $totalRevenue = (clone $base)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->sum('final_price');

        // Staff count
        $staffCount = User::query()
            ->where('business_id', $business->id)
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_MANAGER])
            ->where('is_active', true)
            ->count();

        // Service count
        $serviceCount = Service::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'data' => [
                'business' => [
                    'id' => $business->id,
                    'name' => $business->name,
                    'type' => $business->business_type,
                ],
                'today' => [
                    'bookings' => $todayBookings,
                    'revenue' => (float) $todayRevenue,
                ],
                'this_month' => [
                    'bookings' => $monthBookings,
                    'revenue' => (float) $monthRevenue,
                ],
                'total' => [
                    'bookings' => $totalBookings,
                    'revenue' => (float) $totalRevenue,
                ],
                'counts' => [
                    'staff' => $staffCount,
                    'services' => $serviceCount,
                ],
                'currency' => 'AMD',
            ],
        ]);
    }
}
