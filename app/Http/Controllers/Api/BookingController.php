<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService) {}

    // GET /api/bookings?from=2026-02-01&to=2026-02-07
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['required','date_format:Y-m-d'],
            'to' => ['required','date_format:Y-m-d'],
        ]);

        $user = $request->user();

        $q = Booking::query()
            ->whereBetween('starts_at', [$data['from'].' 00:00:00', $data['to'].' 23:59:59'])
            ->orderBy('starts_at');

        // staff տեսնում է միայն իր bookings-ը
        if ($user->role === User::ROLE_STAFF) {
            $q->where('staff_id', $user->id);
        }

        return response()->json(['data' => $q->get()]);
    }

    // POST /api/bookings
//    public function store(Request $request)
//    {
//        $actor = $request->user();
//
//        $data = $request->validate([
//            'service_id' => ['required','integer','exists:services,id'],
//            'staff_id' => ['nullable','integer','exists:users,id'], // ✅ nullable
//            'starts_at' => ['required','date_format:Y-m-d H:i'],
//            'client_name' => ['required','string','min:2','max:120'],
//            'client_phone' => ['required','string','min:5','max:40'],
//            'notes' => ['nullable','string','max:2000'],
//            'status' => ['sometimes','in:pending,confirmed'],
//        ]);
//
//        // ✅ staff-ի դեպքում staff_id-ը միշտ իրն է
//        $staffId = $actor->role === User::ROLE_STAFF
//            ? $actor->id
//            : (int)($data['staff_id'] ?? 0);
//
//        if ($actor->role !== User::ROLE_STAFF && $staffId <= 0) {
//            return response()->json(['message' => 'Ընտրիր աշխատակից'], 422);
//        }
//
//        $booking = $this->bookingService->makeBooking(
//            actor: $actor,
//            serviceId: (int)$data['service_id'],
//            staffId: $staffId,
//            dateTime: $data['starts_at'],
//            clientName: $data['client_name'],
//            clientPhone: $data['client_phone'],
//            notes: $data['notes'] ?? null,
//            status: $data['status'] ?? 'confirmed'
//        );
//
//        return response()->json([
//            'data' => $booking,
//            'share' => [
//                'booking_code' => $booking->booking_code,
//                'public_url' => config('app.frontend_url') . "/b/" . $booking->booking_code,
//            ]
//        ], 201);
//    }


    public function store(Request $request, AvailabilityService $availability)
    {
        $actor = $request->user(); // եթե auth route է, null չի լինի

        $data = $request->validate([
            'service_id'    => ['required','integer','exists:services,id'],
            'staff_id'      => ['required','integer','exists:users,id'],
            'starts_at'     => ['required','date_format:Y-m-d H:i'],
            'client_name'   => ['required','string','min:2','max:120'],
            'client_phone'  => ['required','string','min:5','max:40'],
            'notes'         => ['nullable','string','max:2000'],
            'status'        => ['nullable','in:pending,confirmed'],
        ]);

        $service = Service::query()->findOrFail((int)$data['service_id']);
        $staff   = User::query()->findOrFail((int)$data['staff_id']);

        // ✅ salon resolve
        $salonId = (int) $service->salon_id;

        // ✅ tenant safety (եթե ունես)
        if ($actor && !$actor->isSuperAdmin()) {
            if ((int)$actor->salon_id !== $salonId) abort(404);
            if ((int)$staff->salon_id !== $salonId) abort(404);
        }

        // ✅ starts_at date + time split
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at']);
        $date = $startsAt->format('Y-m-d');
        $time = $startsAt->format('H:i');

        // ✅ Ստացիր available slots-ը
        $slots = $availability->slotsForDay(
            staffId: (int)$staff->id,
            serviceId: (int)$service->id,
            date: $date,
            salonId: $salonId
        );

        // ✅ ստուգիր՝ ընտրված ժամը կա՞ slots-ի մեջ
        $ok = collect($slots)->contains(fn($s) => substr($s['starts_at'], 11, 5) === $time);

        if (!$ok) {
            return response()->json([
                'message' => 'Selected time is not available for this day.',
            ], 422);
        }

        // ✅ եթե OK է՝ ստեղծիր booking
        $duration = (int)$service->duration_minutes;
        $endsAt = $startsAt->copy()->addMinutes($duration);

        $booking = Booking::query()->create([
            'salon_id'     => $salonId,
            'service_id'   => (int)$service->id,
            'staff_id'     => (int)$staff->id,
            'starts_at'    => $startsAt->format('Y-m-d H:i:s'),
            'ends_at'      => $endsAt->format('Y-m-d H:i:s'),
            'client_name'  => $data['client_name'],
            'client_phone' => $data['client_phone'],
            'notes'        => $data['notes'] ?? null,
            'status'       => $data['status'] ?? 'confirmed',
        ]);

        return response()->json($booking, 201);
    }


    // PUT /api/bookings/{booking} (մի քիչ հետո կավելացնենք update logic)
    public function update(Request $request, Booking $booking)
    {
        // extra tenant guard (եթե BelongsToSalon scope-ով booking-ը չի գտնվել, 404 կստանաս արդեն)
        $user = $request->user();

        // staff can update only own booking
        if ($user->role === User::ROLE_STAFF && $booking->staff_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'service_id' => ['sometimes','integer','exists:services,id'],
            'staff_id' => ['sometimes','integer','exists:users,id'],
            'starts_at' => ['sometimes','date_format:Y-m-d H:i'],
            'client_name' => ['sometimes','string','min:2','max:120'],
            'client_phone' => ['sometimes','string','min:5','max:40'],
            'notes' => ['nullable','string','max:2000'],
            'status' => ['sometimes','in:pending,confirmed,cancelled,done'],
        ]);

        $updated = $this->bookingService->updateBooking($user, $booking, $data);

        return response()->json(['data' => $updated]);
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request->user(), $booking);

        $booking->update(['status' => 'cancelled']);
        return response()->json(['ok' => true]);
    }

    public function done(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request->user(), $booking);

        $booking->update(['status' => 'done']);
        return response()->json(['ok' => true]);
    }

    private function authorizeBookingAccess(User $user, Booking $booking): void
    {
        if (!$user->isSuperAdmin() && $booking->salon_id !== $user->salon_id) {
            abort(404);
        }

        if ($user->role === User::ROLE_STAFF && $booking->staff_id !== $user->id) {
            abort(403);
        }
    }
    public function confirm(Request $request, Booking $booking)
    {
        $actor = $request->user();

        // tenant safety
        if (!$actor->isSuperAdmin() && (int)$booking->salon_id !== (int)$actor->salon_id) {
            abort(404);
        }

        // staff cannot confirm others (optional rule)
        if ($actor->role === User::ROLE_STAFF && (int)$booking->staff_id !== (int)$actor->id) {
            abort(403);
        }

        // already cancelled/done can't confirm
        if (in_array($booking->status, ['cancelled', 'done'], true)) {
            return response()->json([
                'message' => 'Չի կարելի հաստատել չեղարկված/կատարված ամրագրումը'
            ], 422);
        }

        $booking->status = 'confirmed';
        $booking->save();

        return response()->json($booking);
    }

    public function updateTime(Request $request, Booking $booking, AvailabilityService $availability)
    {
        $actor = $request->user();

        if (!$actor->isSuperAdmin() && (int)$booking->salon_id !== (int)$actor->salon_id) {
            abort(404);
        }

        if ($actor->role === User::ROLE_STAFF && (int)$booking->staff_id !== (int)$actor->id) {
            abort(403);
        }

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            // optional: allow changing staff too (եթե ուզում ես)
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $staffId = (int)($data['staff_id'] ?? $booking->staff_id);

        // get slots for that day
        $slots = $availability->slotsForDay(
            staffId: $staffId,
            serviceId: (int)$booking->service_id,
            date: $data['date'],
            salonId: $actor->isSuperAdmin() ? null : (int)$booking->salon_id
        );

        $wantedStart = $data['date'] . ' ' . $data['time'];

        $slot = collect($slots)->first(fn ($s) => ($s['starts_at'] ?? '') === $wantedStart);

        if (!$slot) {
            return response()->json([
                'message' => 'Ընտրված ժամը ազատ չէ կամ չի մտնում աշխատանքային ժամերի մեջ'
            ], 422);
        }

        // ✅ update booking times
        $booking->staff_id  = $staffId;
        $booking->starts_at = $slot['starts_at'];
        $booking->ends_at   = $slot['ends_at'];

        // optional: if was cancelled, keep cancelled (չփոխենք status)
        if ($booking->status === 'pending') {
            // թող մնա pending, կամ ուզում ես ավտո confirmed՝ ասա
            // $booking->status = 'confirmed';
        }

        $booking->save();

        return response()->json($booking);
    }

}
