<?php
// app/Http/Controllers/Admin/BusinessManagementController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Business; // Միայն Business, Salon չկա
use App\Models\User;
use Illuminate\Http\Request;

class BusinessManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Business::withCount(['users', 'bookings'])
            ->withSum('bookings as total_revenue', 'final_price');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%'); // email չկա business-ում, slug-ով փնտրենք
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('business_type')) {
            $query->where('business_type', $request->business_type);
        }

        $businesses = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $businesses
        ]);
    }

    public function show(Business $business, Request $request)
    {
        $business->load([
            'subscription.plan',
            'users' => fn($q) => $q->select('id', 'business_id', 'name', 'email', 'role', 'is_active', 'created_at'), // Փոխել salon_id-ից business_id
        ]);

        $usersTotal = $business->users->count(); // Փոխել $salon-ից $business
        $usersActive = $business->users->where('is_active', true)->count();

        $staffActive = $business->users
            ->where('is_active', true)
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->count();

        $bookingsTotal = $business->bookings()->count(); // Փոխել $salon-ից $business
        $bookingsConfirmedDone = $business->bookings()
            ->whereIn('status', ['confirmed', 'done'])
            ->count();

        $revenueAllTime = (float) $business->bookings()
            ->whereIn('status', ['confirmed', 'done'])
            ->sum('final_price');

        $sub = $business->subscription;
        $plan = $sub?->plan;

        return response()->json([
            'success' => true,
            'data' => [
                'business' => [ // Փոխել 'salon'-ից 'business'
                    'id' => $business->id,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'business_type' => $business->business_type, // Ավելացնել
                    'phone' => $business->phone,
                    'address' => $business->address,
                    'status' => $business->status,
                    'is_onboarding_completed' => (bool) $business->is_onboarding_completed,
                    'work_start' => $business->work_start,
                    'work_end' => $business->work_end,
                    'slot_step_minutes' => $business->slot_step_minutes,
                    'timezone' => $business->timezone,
                    'created_at' => $business->created_at?->toISOString(),
                ],

                'subscription' => $sub ? [
                    'status' => $sub->status,
                    'trial_ends_at' => optional($sub->trial_ends_at)->toISOString(),
                    'current_period_starts_at' => optional($sub->current_period_starts_at)->toISOString(),
                    'current_period_ends_at' => optional($sub->current_period_ends_at)->toISOString(),
                    'is_active' => $sub->isActive(),
                    'plan' => $plan ? [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => $plan->price,
                        'seats' => $plan->seats,
                        'period' => $plan->period ?? null,
                    ] : null,
                ] : null,

                'seats' => [
                    'active' => $business->activeSeatCount(), // Փոխել $salon-ից $business
                    'limit' => $business->seatLimit(),
                    'has_available' => $business->hasAvailableSeat(),
                ],

                'stats' => [
                    'users_total' => $usersTotal,
                    'users_active' => $usersActive,
                    'staff_active' => $staffActive,
                    'bookings_total' => $bookingsTotal,
                    'bookings_confirmed_done' => $bookingsConfirmedDone,
                    'revenue_all_time' => $revenueAllTime,
                    'currency' => 'AMD',
                ],
            ],
        ]);
    }

    public function suspend(Business $business, Request $request) // Փոխել Salon-ից Business
    {
        $business->update(['status' => 'suspended']); // Փոխել $salon-ից $business

        // Optionally deactivate all users
        User::where('business_id', $business->id) // Փոխել salon_id-ից business_id
        ->update(['is_active' => false]);

        AdminLog::create([
            'admin_id' => $request->user('admin')->id,
            'action' => 'suspend_business', // Փոխել suspend_salon-ից suspend_business
            'model_type' => Business::class, // Փոխել Salon::class-ից Business::class
            'model_id' => $business->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Business suspended']); // Փոխել հաղորդագրությունը
    }

    public function restore(Business $business, Request $request) // Փոխել Salon-ից Business
    {
        $business->update(['status' => 'active']); // Փոխել $salon-ից $business

        AdminLog::create([
            'admin_id' => $request->user('admin')->id,
            'action' => 'restore_business', // Փոխել restore_salon-ից restore_business
            'model_type' => Business::class, // Փոխել Salon::class-ից Business::class
            'model_id' => $business->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Business restored']); // Փոխել հաղորդագրությունը
    }

    // Ավելացնել լրացուցիչ մեթոդներ, եթե անհրաժեշտ է
    public function updatePlan(Request $request, Business $business)
    {
        $request->validate([
            'plan_code' => 'required|string|exists:plans,code',
        ]);

        $plan = Plan::where('code', $request->plan_code)->firstOrFail();

        $subscription = $business->subscription;
        if (!$subscription) {
            return response()->json(['message' => 'Business has no subscription'], 404);
        }

        $subscription->update(['plan_id' => $plan->id]);

        AdminLog::create([
            'admin_id' => $request->user('admin')->id,
            'action' => 'update_business_plan',
            'model_type' => Business::class,
            'model_id' => $business->id,
            'new_values' => ['plan_code' => $plan->code],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Plan updated successfully']);
    }

    public function extendTrial(Request $request, Business $business)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:30',
        ]);

        $subscription = $business->subscription;
        if (!$subscription) {
            return response()->json(['message' => 'Business has no subscription'], 404);
        }

        $newTrialEnd = $subscription->trial_ends_at
            ? $subscription->trial_ends_at->addDays($request->days)
            : now()->addDays($request->days);

        $subscription->update([
            'trial_ends_at' => $newTrialEnd,
            'status' => 'trialing',
        ]);

        AdminLog::create([
            'admin_id' => $request->user('admin')->id,
            'action' => 'extend_trial',
            'model_type' => Business::class,
            'model_id' => $business->id,
            'new_values' => ['trial_days_added' => $request->days],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Trial extended successfully']);
    }
}
