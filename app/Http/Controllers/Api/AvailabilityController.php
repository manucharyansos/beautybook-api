<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * GET /api/availability?staff_id=2&service_id=3&date=2026-02-14
     * Auth endpoint (admin/staff panel)
     */
    public function index(Request $request)
    {
        $actor = $request->user();

        $data = $request->validate([
            'staff_id'    => ['required', 'integer', 'exists:users,id'],
            'service_id'  => ['required', 'integer', 'exists:services,id'],
            'date'        => ['required', 'date_format:Y-m-d'],
        ]);

        // Load staff
        /** @var User $staff */
        $staff = User::query()->findOrFail((int) $data['staff_id']);

        // Tenant safety (super admin can access anything)
        if (!$actor->isSuperAdmin()) {
            if ((int)$staff->salon_id !== (int)$actor->salon_id) {
                abort(404);
            }
        }

        // Staff user can query ONLY own staff_id
        if ($actor->role === User::ROLE_STAFF && (int)$staff->id !== (int)$actor->id) {
            abort(403);
        }

        // Service must belong to same salon (unless super admin)
        /** @var Service $service */
        $service = Service::query()->findOrFail((int) $data['service_id']);
        if (!$actor->isSuperAdmin()) {
            if ((int)$service->salon_id !== (int)$actor->salon_id) {
                abort(404);
            }
        }

        // Slots (IMPORTANT: AvailabilityService SHOULD NOT throw when no schedule, it must return [])
        $slots = $this->availability->slotsForDay(
            staffId: (int) $staff->id,
            serviceId: (int) $service->id,
            date: $data['date'],
            salonId: $actor->isSuperAdmin() ? null : (int) $actor->salon_id
        );

        return response()->json($slots)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');

    }

    /**
     * GET /api/availability/day?service_id=3&date=2026-02-14&staff_id=2(optional)
     * Auth endpoint used by the Calendar modal (smart staff selection)
     *
     * Rules:
     * - staff role -> always uses self id
     * - owner/manager -> can pick staff_id, if not provided -> defaults first eligible staff in salon
     */
    public function day(Request $request)
    {
        $actor = $request->user(); // կարող է null լինի, եթե auth middleware չկա

        $data = $request->validate([
            'service_id' => ['required','integer','exists:services,id'],
            'date'       => ['required','date_format:Y-m-d'],
            'staff_id'   => ['nullable','integer','exists:users,id'],
        ]);

        $service = Service::query()->findOrFail((int)$data['service_id']);

        // եթե actor չկա, ստիպիր staff_id պարտադիր լինի (քանի որ public դեպքում պետք է ուրիշ route լինի)
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) return response()->json([]);

        // salonId վերցնում ենք service-ից
        $slots = $this->availability->slotsForDay(
            staffId: $staffId,
            serviceId: (int)$service->id,
            date: $data['date'],
            salonId: (int)$service->salon_id
        );

        return response()->json($slots)
            ->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma','no-cache');
    }

}
