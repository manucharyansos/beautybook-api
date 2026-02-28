<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business; // Ավելացնել Business մոդելը
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessSettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->business_id) { // Փոխել salon_id-ից business_id
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $business = $user->business()->firstOrFail(); // Փոխել $salon-ից $business

        // Get working hours if they exist
        $workingHours = $business->workingHours()
            ->orderBy('weekday')
            ->get(['weekday', 'is_closed', 'start', 'end', 'break_start', 'break_end']);

        return response()->json([
            'data' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'business_type' => $business->business_type,
                'phone' => $business->phone,
                'address' => $business->address,
                'timezone' => $business->timezone ?? config('app.timezone', 'Asia/Yerevan'),
                'slot_step_minutes' => $business->slot_step_minutes ?? 15,
                'work_start' => $business->work_start,
                'work_end' => $business->work_end,
                'is_onboarding_completed' => $business->is_onboarding_completed,
                'working_hours' => $workingHours,
            ]
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->business_id) { // Փոխել salon_id-ից business_id
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!in_array($user->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:60', Rule::in(timezone_identifiers_list())],
            'slot_step_minutes' => ['nullable', 'integer', 'min:5', 'max:60'],
            'work_start' => ['nullable', 'date_format:H:i'],
            'work_end' => ['nullable', 'date_format:H:i', 'after:work_start'],
        ]);

        $business = $user->business()->firstOrFail(); // Փոխել $salon-ից $business

        $business->update([
            'phone' => $data['phone'] ?? $business->phone,
            'address' => $data['address'] ?? $business->address,
            'timezone' => $data['timezone'] ?? $business->timezone,
            'slot_step_minutes' => $data['slot_step_minutes'] ?? $business->slot_step_minutes,
            'work_start' => $data['work_start'] ?? $business->work_start,
            'work_end' => $data['work_end'] ?? $business->work_end,
        ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'phone' => $business->phone,
                'address' => $business->address,
                'timezone' => $business->timezone,
                'slot_step_minutes' => $business->slot_step_minutes,
                'work_start' => $business->work_start,
                'work_end' => $business->work_end,
            ]
        ]);
    }

    /**
     * Update working hours for the business
     */
    public function updateWorkingHours(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->business_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!in_array($user->role, [User::ROLE_OWNER, User::ROLE_MANAGER], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'working_hours' => ['required', 'array', 'size:7'],
            'working_hours.*.weekday' => ['required', 'integer', 'between:1,7'],
            'working_hours.*.is_closed' => ['required', 'boolean'],
            'working_hours.*.start' => ['required_if:is_closed,false', 'nullable', 'date_format:H:i'],
            'working_hours.*.end' => ['required_if:is_closed,false', 'nullable', 'date_format:H:i', 'after:working_hours.*.start'],
            'working_hours.*.break_start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.break_end' => ['nullable', 'date_format:H:i', 'after:working_hours.*.break_start'],
        ]);

        $business = $user->business;

        // Delete existing working hours
        $business->workingHours()->delete();

        // Create new working hours
        foreach ($data['working_hours'] as $hours) {
            $business->workingHours()->create([
                'weekday' => $hours['weekday'],
                'is_closed' => $hours['is_closed'],
                'start' => $hours['start'] ?? null,
                'end' => $hours['end'] ?? null,
                'break_start' => $hours['break_start'] ?? null,
                'break_end' => $hours['break_end'] ?? null,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Get business statistics
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->business_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $business = $user->business;

        $stats = [
            'staff_count' => $business->users()
                ->whereIn('role', [User::ROLE_STAFF, User::ROLE_MANAGER])
                ->where('is_active', true)
                ->count(),
            'services_count' => $business->services()->where('is_active', true)->count(),
            'clients_count' => $business->clients()->count(),
            'bookings_today' => $business->bookings()
                ->whereDate('starts_at', today())
                ->count(),
            'bookings_this_week' => $business->bookings()
                ->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'revenue_this_month' => $business->bookings()
                ->whereIn('status', ['confirmed', 'done'])
                ->whereMonth('starts_at', now()->month)
                ->sum('final_price'),
        ];

        return response()->json(['data' => $stats]);
    }
}
