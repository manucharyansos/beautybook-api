<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PublicBookingController extends Controller
{
    public function salon(string $slug)
    {
        $salon = Salon::query()->where('slug', $slug)->firstOrFail();
        return response()->json([
            'id' => $salon->id,
            'name' => $salon->name,
            'slug' => $salon->slug,
            'work_start' => $salon->work_start,
            'work_end' => $salon->work_end,
            'timezone' => $salon->timezone ?? config('app.timezone'),
        ]);
    }

    public function services(string $slug)
    {
        $salon = Salon::query()->where('slug', $slug)->firstOrFail();

        $services = Service::query()
            ->where('salon_id', $salon->id)
            ->orderBy('id')
            ->get(['id','name','duration_minutes','price','is_active']);

        return response()->json($services);
    }

    public function staff(string $slug)
    {
        $salon = Salon::query()->where('slug', $slug)->firstOrFail();

        $staff = User::query()
            ->where('salon_id', $salon->id)
            ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF])
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id','name','role']);

        return response()->json($staff);
    }

    /**
     * GET /api/public/availability?slug=demo-salon&service_id=6&date=2026-02-18&staff_id=1(optional)
     */
    public function availability(Request $request, AvailabilityService $availability)
    {
        $data = $request->validate([
            'slug'       => ['required','string'],
            'service_id' => ['required','integer','exists:services,id'],
            'date'       => ['required','date_format:Y-m-d'],
            'staff_id'   => ['nullable','integer','exists:users,id'],
        ]);

        $salon = Salon::query()->where('slug', $data['slug'])->firstOrFail();

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('salon_id', $salon->id)
            ->firstOrFail();

        $staffId = (int)($data['staff_id'] ?? 0);

        // եթե staff_id չեն ուղարկել՝ վերցնենք առաջին ակտիվ staff
        if (!$staffId) {
            $staffId = (int) User::query()
                ->where('salon_id', $salon->id)
                ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF])
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
        }

        if (!$staffId) return response()->json([]);

        // staff-ը պարտադիր նույն salon-ից
        $staff = User::query()
            ->where('id', $staffId)
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->first();

        if (!$staff) return response()->json([]);

        $slots = $availability->slotsForDay(
            staffId: $staff->id,
            serviceId: $service->id,
            date: $data['date'],
            salonId: $salon->id
        );

        return response()->json($slots)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * POST /api/public/bookings
     * body: slug, service_id, staff_id(optional), starts_at(Y-m-d H:i), client_name, client_phone, notes(optional)
     */
    public function store(Request $request, AvailabilityService $availability)
    {
        $data = $request->validate([
            'slug'         => ['required','string'],
            'service_id'   => ['required','integer','exists:services,id'],
            'staff_id'     => ['nullable','integer','exists:users,id'],
            'starts_at'    => ['required','date_format:Y-m-d H:i'],
            'client_name'  => ['required','string','min:2','max:120'],
            'client_phone' => ['required','string','min:5','max:40'],
            'notes'        => ['nullable','string','max:2000'],
        ]);

        $salon = Salon::query()->where('slug', $data['slug'])->firstOrFail();

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('salon_id', $salon->id)
            ->firstOrFail();

        $staffId = (int)($data['staff_id'] ?? 0);

        if (!$staffId) {
            $staffId = (int) User::query()
                ->where('salon_id', $salon->id)
                ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF])
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
        }

        if (!$staffId) {
            return response()->json(['message' => 'No staff available.'], 422);
        }

        $staff = User::query()
            ->where('id', $staffId)
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->first();

        if (!$staff) {
            return response()->json(['message' => 'Invalid staff.'], 422);
        }

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at']);
        $date = $startsAt->format('Y-m-d');
        $time = $startsAt->format('H:i');

        // ✅ validate via AvailabilityService (no staff schedule errors)
        $slots = $availability->slotsForDay(
            staffId: $staff->id,
            serviceId: $service->id,
            date: $date,
            salonId: $salon->id
        );

        $ok = collect($slots)->contains(fn($s) => substr($s['starts_at'], 11, 5) === $time);

        if (!$ok) {
            return response()->json(['message' => 'Selected time is not available.'], 422);
        }

        $duration = (int)$service->duration_minutes;
        $endsAt = $startsAt->copy()->addMinutes($duration);

        $booking = Booking::query()->create([
            'salon_id'     => $salon->id,
            'service_id'   => $service->id,
            'staff_id'     => $staff->id,
            'starts_at'    => $startsAt->format('Y-m-d H:i:s'),
            'ends_at'      => $endsAt->format('Y-m-d H:i:s'),
            'client_name'  => $data['client_name'],
            'client_phone' => $data['client_phone'],
            'notes'        => $data['notes'] ?? null,
            'status'       => 'pending', // ✅ public -> pending (owner will confirm)
            'code'         => strtoupper(Str::random(8)), // եթե ունես code field
        ]);

        return response()->json($booking, 201);
    }
}
