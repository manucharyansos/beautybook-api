<?php
// app/Http/Controllers/Api/AvailabilityController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business; // Ավելացնել
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    protected $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    public function availability(Request $request)
    {
        $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'staff_id' => 'nullable|integer|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
        ]);

        $serviceId = (int) $request->service_id;
        $staffId = $request->staff_id ? (int) $request->staff_id : null;
        $date = $request->date;

        $service = Service::findOrFail($serviceId);
        $businessId = $service->business_id; // Փոխել salon_id-ից business_id

        if (!$staffId) {
            $staff = User::where('business_id', $businessId) // Փոխել salon_id-ից business_id
            ->whereIn('role', ['staff', 'manager', 'owner'])
                ->where('is_active', true)
                ->first();

            if ($staff) {
                $staffId = $staff->id;
            } else {
                return response()->json([]);
            }
        }

        $staff = User::where('id', $staffId)
            ->where('business_id', $businessId) // Փոխել salon_id-ից business_id
            ->where('is_active', true)
            ->first();

        if (!$staff) {
            return response()->json([]);
        }

        $slots = $this->availabilityService->slotsForDay(
            staffId: $staffId,
            serviceId: $serviceId,
            date: $date,
            businessId: $businessId // Փոխել salonId-ից businessId
        );

        return response()->json($slots);
    }
}
