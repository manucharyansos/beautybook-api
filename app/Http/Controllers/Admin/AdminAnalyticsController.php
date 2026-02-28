<?php
// app/Http/Controllers/Admin/AdminAnalyticsController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business; // Միայն Business, Salon չկա
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    private array $paidStatuses = ['confirmed', 'done'];

    public function dashboard(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:7_days,30_days,90_days,12_months,custom',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'business_id' => 'nullable|integer|exists:businesses,id', // Փոխել salon_id-ից business_id
        ]);

        $period = $validated['period'] ?? '30_days';
        $range = $this->getDateRange($period, $request);
        $start = $range['start'];
        $end = $range['end'];

        $prev = $this->getPreviousPeriod($start, $end);

        $businessId = $validated['business_id'] ?? null; // Փոխել salonId-ից businessId

        // ------------------------
        // Businesses (global)
        // ------------------------
        $businessesTotal = Business::count();
        $businessesActive = Business::where('status', 'active')->count();
        $businessesSuspended = Business::where('status', 'suspended')->count();
        $businessesPending = Business::where('status', 'pending')->count();

        $businessesNewPeriod = Business::whereBetween('created_at', [$start, $end])->count();
        $businessesNewPrev = Business::whereBetween('created_at', [$prev['start'], $prev['end']])->count();
        $businessesGrowth = $this->pctChange($businessesNewPrev, $businessesNewPeriod);

        // ------------------------
        // Users (global)
        // ------------------------
        $usersTotal = User::count();
        $usersOwners = User::where('role', User::ROLE_OWNER)->count();
        $usersManagers = User::where('role', User::ROLE_MANAGER)->count();
        $usersStaff = User::where('role', User::ROLE_STAFF)->count();

        $usersNewPeriod = User::whereBetween('created_at', [$start, $end])->count();
        $usersNewPrev = User::whereBetween('created_at', [$prev['start'], $prev['end']])->count();
        $usersGrowth = $this->pctChange($usersNewPrev, $usersNewPeriod);

        // ------------------------
        // Bookings (period-aware)
        // ------------------------
        $bookingsPeriod = Booking::query()->whereBetween('starts_at', [$start, $end]);
        $bookingsPrev = Booking::query()->whereBetween('starts_at', [$prev['start'], $prev['end']]);

        if ($businessId) {
            $bookingsPeriod->where('business_id', $businessId); // Փոխել salon_id-ից business_id
            $bookingsPrev->where('business_id', $businessId);
        }

        $bookingsPeriodTotal = (clone $bookingsPeriod)->count();
        $bookingsPrevTotal = (clone $bookingsPrev)->count();
        $bookingsTrend = $this->pctChange($bookingsPrevTotal, $bookingsPeriodTotal);

        $todayBookings = Booking::query()
            ->when($businessId, fn($q) => $q->where('business_id', $businessId)) // Փոխել salon_id-ից business_id
            ->whereDate('starts_at', today())
            ->count();

        // ------------------------
        // Revenue (period-aware, paid statuses only)
        // ------------------------
        $paidPeriod = Booking::query()
            ->whereIn('status', $this->paidStatuses)
            ->whereBetween('starts_at', [$start, $end]);

        $paidPrev = Booking::query()
            ->whereIn('status', $this->paidStatuses)
            ->whereBetween('starts_at', [$prev['start'], $prev['end']]);

        if ($businessId) {
            $paidPeriod->where('business_id', $businessId); // Փոխել salon_id-ից business_id
            $paidPrev->where('business_id', $businessId);
        }

        $revenuePeriodTotal = (clone $paidPeriod)->sum('final_price');
        $revenuePrevTotal = (clone $paidPrev)->sum('final_price');
        $revenueTrend = $this->pctChange($revenuePrevTotal, $revenuePeriodTotal);

        $revenueToday = Booking::query()
            ->whereIn('status', $this->paidStatuses)
            ->when($businessId, fn($q) => $q->where('business_id', $businessId)) // Փոխել salon_id-ից business_id
            ->whereDate('starts_at', today())
            ->sum('final_price');

        // Optional all-time
        $revenueAllTime = Booking::query()
            ->whereIn('status', $this->paidStatuses)
            ->when($businessId, fn($q) => $q->where('business_id', $businessId)) // Փոխել salon_id-ից business_id
            ->sum('final_price');

        // ------------------------
        // Subscriptions (global)
        // ------------------------
        $subscriptionStats = [
            'active' => Subscription::where('status', 'active')->count(),
            'trialing' => Subscription::where('status', 'trialing')->count(),
            'canceled' => Subscription::where('status', 'canceled')->count(),
            'mrr' => $this->calculateMRR(),
        ];

        // ------------------------
        // Recent businesses
        // ------------------------
        $recentBusinesses = Business::withCount(['users', 'bookings'])
            ->latest()
            ->limit(10)
            ->get();

        // ------------------------
        // Charts series (single response for dashboard)
        // ------------------------
        $groupBy = $this->suggestGroupBy($period);
        $revenueSeries = $this->revenueSeries($start, $end, $groupBy, $businessId);
        $bookingsSeries = $this->bookingsSeries($start, $end, $groupBy, $businessId);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'date_range' => [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                ],
                'businesses' => [ // Փոխել salons-ից businesses
                    'total' => $businessesTotal,
                    'active' => $businessesActive,
                    'suspended' => $businessesSuspended,
                    'pending' => $businessesPending,
                    'new' => $businessesNewPeriod,
                    'growth' => $businessesGrowth,
                ],
                'users' => [
                    'total' => $usersTotal,
                    'owners' => $usersOwners,
                    'managers' => $usersManagers,
                    'staff' => $usersStaff,
                    'new' => $usersNewPeriod,
                    'growth' => $usersGrowth,
                ],
                'bookings' => [
                    'period_total' => $bookingsPeriodTotal,
                    'today' => $todayBookings,
                    'trend' => $bookingsTrend,
                ],
                'revenue' => [
                    'period_total' => $revenuePeriodTotal,
                    'today' => $revenueToday,
                    'all_time_total' => $revenueAllTime,
                    'trend' => $revenueTrend,
                ],
                'subscriptions' => $subscriptionStats,
                'recent_businesses' => $recentBusinesses, // Փոխել recent_salons-ից recent_businesses
                'charts' => [
                    'group_by' => $groupBy,
                    'revenue' => $revenueSeries,
                    'bookings' => $bookingsSeries,
                ],
                'currency' => 'AMD',
            ],
        ]);
    }

    public function businesses(Request $request) // Փոխել salons-ից businesses
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,suspended,pending',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'search' => 'nullable|string|max:100',
            'sort_by' => 'nullable|string',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Business::query() // Business, ոչ թե Salon
        ->withCount(['users', 'bookings'])
            ->withSum('bookings as total_revenue', 'final_price');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from']) && !empty($validated['to'])) {
            $query->whereBetween('created_at', [$validated['from'], $validated['to']]);
        }

        if (!empty($validated['search'])) {
            $s = $validated['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $allowedSort = ['created_at', 'name', 'status', 'users_count', 'bookings_count', 'total_revenue'];
        $sortBy = $validated['sort_by'] ?? 'created_at';
        if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $businesses = $query->paginate($validated['per_page'] ?? 20);

        return response()->json(['success' => true, 'data' => $businesses]);
    }

    public function revenue(Request $request)
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:day,week,month',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'business_id' => 'nullable|integer|exists:businesses,id', // Փոխել salon_id-ից business_id
        ]);

        $groupBy = $validated['group_by'] ?? 'month';

        $from = !empty($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->subMonths(12)->startOfDay();
        $to = !empty($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        $businessId = $validated['business_id'] ?? null;

        $items = $this->revenueSeries($from, $to, $groupBy, $businessId);

        return response()->json([
            'success' => true,
            'data' => [
                'group_by' => $groupBy,
                'items' => $items,
                'currency' => 'AMD',
            ],
        ]);
    }

    // ------------------------
    // Helpers
    // ------------------------
    private function getDateRange(string $period, Request $request): array
    {
        $now = Carbon::now();

        return match ($period) {
            '7_days' => [
                'start' => $now->copy()->subDays(7)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            '30_days' => [
                'start' => $now->copy()->subDays(30)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            '90_days' => [
                'start' => $now->copy()->subDays(90)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            '12_months' => [
                'start' => $now->copy()->subMonths(12)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'custom' => [
                'start' => Carbon::parse($request->get('from', now()->subDays(30)))->startOfDay(),
                'end' => Carbon::parse($request->get('to', now()))->endOfDay(),
            ],
            default => [
                'start' => $now->copy()->subDays(30)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
        };
    }

    private function getPreviousPeriod(Carbon $start, Carbon $end): array
    {
        $days = max(1, $end->diffInDays($start));
        return [
            'start' => $start->copy()->subDays($days)->startOfDay(),
            'end' => $start->copy()->subSecond(),
        ];
    }

    private function pctChange(float|int $previous, float|int $current): float
    {
        $previous = (float) $previous;
        $current = (float) $current;
        if ($previous <= 0) return 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function suggestGroupBy(string $period): string
    {
        return match ($period) {
            '7_days' => 'day',
            '30_days' => 'day',
            '90_days' => 'week',
            '12_months' => 'month',
            default => 'day',
        };
    }

    private function revenueSeries(Carbon $from, Carbon $to, string $groupBy, ?int $businessId = null): array
    {
        $q = Booking::query()
            ->whereIn('status', $this->paidStatuses)
            ->whereBetween('starts_at', [$from, $to])
            ->when($businessId, fn($qq) => $qq->where('business_id', $businessId)); // Փոխել salon_id-ից business_id

        if ($groupBy === 'day') {
            return $q->select(
                DB::raw('DATE(starts_at) as period'),
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(final_price) as revenue')
            )
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(fn($x) => [
                    'period' => (string) $x->period,
                    'bookings' => (int) $x->bookings,
                    'revenue' => (float) $x->revenue,
                ])->all();
        }

        if ($groupBy === 'week') {
            return $q->select(
                DB::raw('YEAR(starts_at) as y'),
                DB::raw('WEEK(starts_at, 3) as w'),
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(final_price) as revenue')
            )
                ->groupBy('y', 'w')
                ->orderBy('y')
                ->orderBy('w')
                ->get()
                ->map(fn($x) => [
                    'period' => "{$x->y}-W{$x->w}",
                    'bookings' => (int) $x->bookings,
                    'revenue' => (float) $x->revenue,
                ])->all();
        }

        // month
        return $q->select(
            DB::raw('DATE_FORMAT(starts_at, "%Y-%m") as period'),
            DB::raw('COUNT(*) as bookings'),
            DB::raw('SUM(final_price) as revenue')
        )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($x) => [
                'period' => (string) $x->period,
                'bookings' => (int) $x->bookings,
                'revenue' => (float) $x->revenue,
            ])->all();
    }

    private function bookingsSeries(Carbon $from, Carbon $to, string $groupBy, ?int $businessId = null): array
    {
        $q = Booking::query()
            ->whereBetween('starts_at', [$from, $to])
            ->when($businessId, fn($qq) => $qq->where('business_id', $businessId)); // Փոխել salon_id-ից business_id

        if ($groupBy === 'day') {
            return $q->select(
                DB::raw('DATE(starts_at) as period'),
                DB::raw('COUNT(*) as bookings')
            )
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(fn($x) => [
                    'period' => (string) $x->period,
                    'bookings' => (int) $x->bookings,
                ])->all();
        }

        if ($groupBy === 'week') {
            return $q->select(
                DB::raw('YEAR(starts_at) as y'),
                DB::raw('WEEK(starts_at, 3) as w'),
                DB::raw('COUNT(*) as bookings')
            )
                ->groupBy('y', 'w')
                ->orderBy('y')
                ->orderBy('w')
                ->get()
                ->map(fn($x) => [
                    'period' => "{$x->y}-W{$x->w}",
                    'bookings' => (int) $x->bookings,
                ])->all();
        }

        // month
        return $q->select(
            DB::raw('DATE_FORMAT(starts_at, "%Y-%m") as period'),
            DB::raw('COUNT(*) as bookings')
        )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($x) => [
                'period' => (string) $x->period,
                'bookings' => (int) $x->bookings,
            ])->all();
    }

    private function calculateMRR(): float
    {
        return (float) Subscription::where('status', 'active')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price');
    }

    public function exportBusinesses(Request $request) // Փոխել exportSalons-ից exportBusinesses
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,suspended,pending',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'search' => 'nullable|string|max:100',
            'sort_by' => 'nullable|string',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $query = Business::query() // Business, ոչ թե Salon
        ->withCount(['users', 'bookings'])
            ->withSum('bookings as total_revenue', 'final_price');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from']) && !empty($validated['to'])) {
            $query->whereBetween('created_at', [$validated['from'], $validated['to']]);
        }

        if (!empty($validated['search'])) {
            $s = $validated['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $allowedSort = ['created_at', 'name', 'status', 'users_count', 'bookings_count', 'total_revenue'];
        $sortBy = $validated['sort_by'] ?? 'created_at';
        if (!in_array($sortBy, $allowedSort, true)) $sortBy = 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $filename = 'businesses_export_' . now()->format('Y-m-d_His') . '.csv'; // Փոխել salons_export-ից businesses_export

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, [
                'ID',
                'Անուն',
                'Slug',
                'Տիպ', // Ավելացնենք business_type
                'Կարգավիճակ',
                'Օգտատերեր',
                'Ամրագրումներ',
                'Եկամուտ',
                'Ստեղծման ամսաթիվ',
            ]);

            $query->chunk(500, function ($businesses) use ($out) {
                foreach ($businesses as $business) {
                    fputcsv($out, [
                        $business->id,
                        $business->name,
                        $business->slug,
                        $business->business_type ?? 'salon', // beauty/dental
                        $business->status,
                        $business->users_count ?? 0,
                        $business->bookings_count ?? 0,
                        $business->total_revenue ?? 0,
                        optional($business->created_at)->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportRevenue(Request $request)
    {
        $validated = $request->validate([
            'period' => 'nullable|in:7_days,30_days,90_days,12_months,custom',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'group_by' => 'nullable|in:day,week,month',
            'business_id' => 'nullable|integer|exists:businesses,id', // Փոխել salon_id-ից business_id
        ]);

        $period = $validated['period'] ?? null;

        if ($period) {
            $range = $this->getDateRange($period, $request);
            $from = $range['start'];
            $to = $range['end'];
            $groupBy = $validated['group_by'] ?? $this->suggestGroupBy($period);
        } else {
            $from = !empty($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->subMonths(12)->startOfDay();
            $to = !empty($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
            $groupBy = $validated['group_by'] ?? 'month';
        }

        $businessId = $validated['business_id'] ?? null;

        $items = $this->revenueSeries($from, $to, $groupBy, $businessId);

        $filename = 'revenue_export_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['Ժամանակահատված', 'Ամրագրումներ', 'Եկամուտ (AMD)']);

            foreach ($items as $row) {
                fputcsv($out, [
                    $row['period'],
                    $row['bookings'],
                    $row['revenue'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
