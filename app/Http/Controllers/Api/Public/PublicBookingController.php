<?php
// app/Http/Controllers/Api/Public/PublicBookingController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\SmsService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PublicBookingController extends Controller
{
    public function business(string $slug)
    {
        $business = Business::query()->where('slug', $slug)->firstOrFail();
        $businessType = $business->business_type ?? 'salon';

        return response()->json([
            'id' => $business->id,
            'name' => $business->name,
            'slug' => $business->slug,
            'business_type' => $businessType,
            'work_start' => $business->work_start,
            'work_end' => $business->work_end,
            'timezone' => $business->timezone ?? config('app.timezone'),
            'settings' => [
                'has_rooms' => $businessType === 'clinic',
                'has_patients' => $businessType === 'clinic',
                'phone_verification' => true,
            ],
        ]);
    }

    public function services(string $slug)
    {
        $business = Business::query()->where('slug', $slug)->firstOrFail();

        $services = Service::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'duration_minutes', 'price', 'currency', 'is_active']);

        return response()->json([
            'data' => $services,
            'meta' => ['business_type' => $business->business_type],
        ]);
    }

    public function staff(string $slug)
    {
        $business = Business::query()->where('slug', $slug)->firstOrFail();

        $staff = User::query()
            ->where('business_id', $business->id)
            ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF])
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'role']);

        return response()->json([
            'data' => $staff,
            'meta' => ['business_type' => $business->business_type],
        ]);
    }

    /**
     * GET /api/public/businesses/{slug}/availability?service_id=..&date=YYYY-MM-DD&staff_id(optional)
     */
    public function availability(string $slug, Request $request, AvailabilityService $availability)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date'       => ['required', 'date_format:Y-m-d'],
            'staff_id'   => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $business = Business::query()->where('slug', $slug)->firstOrFail();

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) {
            $staffId = (int) User::query()
                ->where('business_id', $business->id)
                ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF])
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
        }
        if (!$staffId) return response()->json([]);

        $staff = User::query()
            ->where('id', $staffId)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->first();

        if (!$staff) return response()->json([]);

        $slots = $availability->slotsForDay(
            staffId: $staff->id,
            serviceId: $service->id,
            date: $data['date'],
            businessId: $business->id
        );

        return response()->json([
            'data' => $slots,
            'meta' => [
                'business_type' => $business->business_type,
                'has_rooms' => $business->business_type === 'clinic',
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache');
    }

    /**
     * POST /api/public/businesses/{slug}/bookings
     * Creates booking in "pending" but hides from staff until phone verified.
     */
    public function store(string $slug, Request $request, AvailabilityService $availability, SmsService $sms)
    {
        $data = $request->validate([
            'service_id'   => ['required', 'integer', 'exists:services,id'],
            'staff_id'     => ['nullable', 'integer', 'exists:users,id'],
            'starts_at'    => ['required', 'date_format:Y-m-d H:i'],
            'client_name'  => ['required', 'string', 'min:2', 'max:120'],
            'client_phone' => ['required', 'string', 'min:5', 'max:40'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'room_id'      => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        $business = Business::query()->where('slug', $slug)->firstOrFail();

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        // pick staff
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) {
            $staffId = (int) User::query()
                ->where('business_id', $business->id)
                ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF])
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
        }
        if (!$staffId) return response()->json(['message' => 'No staff available.'], 422);

        $staff = User::query()
            ->where('id', $staffId)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->first();
        if (!$staff) return response()->json(['message' => 'Invalid staff.'], 422);

        // normalize phone
        $phoneNorm = Phone::normalizeAM($data['client_phone']);
        if (!$phoneNorm) {
            return response()->json(['message' => 'Invalid phone number'], 422);
        }

        // check slot
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at']);
        $date = $startsAt->format('Y-m-d');
        $time = $startsAt->format('H:i');

        $slots = $availability->slotsForDay(
            staffId: $staff->id,
            serviceId: $service->id,
            date: $date,
            businessId: $business->id
        );
        $ok = collect($slots)->contains(fn($s) => substr($s['starts_at'], 11, 5) === $time);
        if (!$ok) return response()->json(['message' => 'Selected time is not available.'], 422);

        $endsAt = $startsAt->copy()->addMinutes((int)$service->duration_minutes);

        // create/find client inside this business
        $client = Client::query()
            ->where('business_id', $business->id)
            ->where('phone', $phoneNorm)
            ->first();
        if (!$client) {
            $client = Client::query()->create([
                'business_id' => $business->id,
                'name' => $data['client_name'],
                'phone' => $phoneNorm,
            ]);
        }

        $code = (string)random_int(1000, 9999);
        $expires = now()->addMinutes(10);

        $booking = Booking::query()->create([
            'business_id'   => $business->id,
            'service_id'    => $service->id,
            'staff_id'      => $staff->id,
            'client_id'     => $client->id,
            'room_id'       => ($business->business_type === 'clinic') ? ($data['room_id'] ?? null) : null,
            'starts_at'     => $startsAt->format('Y-m-d H:i:s'),
            'ends_at'       => $endsAt->format('Y-m-d H:i:s'),
            'client_name'   => $data['client_name'],
            'client_phone'  => $phoneNorm,
            'notes'         => $data['notes'] ?? null,
            'status'        => 'pending',
            'booking_code'  => strtoupper(Str::random(8)),
            'final_price'   => $service->price,
            'currency'      => $service->currency ?? 'AMD',

            'phone_verification_code_hash' => Hash::make($code),
            'phone_verification_expires_at' => $expires,
            'phone_verified_at' => null,
            'phone_verification_attempts' => 0,
        ]);

        // send code (SMS/WhatsApp)
        $sms->send($phoneNorm, "Ձեր ամրագրման կոդը՝ {$code}. Վավեր է 10 րոպե։");

        return response()->json([
            'data' => [
                'booking_code' => $booking->booking_code,
                'needs_phone_verification' => true,
                'phone' => $phoneNorm,
                'expires_at' => $expires->toISOString(),
            ],
            'meta' => ['business_type' => $business->business_type],
        ], 201);
    }

    /**
     * POST /api/public/bookings/{code}/verify
     */
    public function verifyPhone(string $code, Request $request)
    {
        $data = $request->validate([
            'otp' => ['required','string','min:4','max:8'],
        ]);

        $booking = Booking::query()->where('booking_code', $code)->firstOrFail();

        if ($booking->phone_verified_at) {
            return response()->json(['ok' => true, 'already' => true]);
        }

        if (!$booking->phone_verification_expires_at || now()->greaterThan($booking->phone_verification_expires_at)) {
            return response()->json(['message' => 'Code expired. Please create a new booking.'], 422);
        }

        if ($booking->phone_verification_attempts >= 5) {
            return response()->json(['message' => 'Too many attempts.'], 429);
        }

        $booking->increment('phone_verification_attempts');

        if (!Hash::check($data['otp'], (string)$booking->phone_verification_code_hash)) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $booking->update([
            'phone_verified_at' => now(),
            'phone_verification_code_hash' => null,
            'phone_verification_expires_at' => null,
        ]);

        return response()->json([
            'ok' => true,
            'data' => $booking->fresh()->load(['business:id,name,slug,business_type', 'service:id,name', 'staff:id,name']),
        ]);
    }

    public function show(string $code)
    {
        $booking = Booking::query()
            ->with(['business:id,name,slug,business_type', 'service:id,name', 'staff:id,name'])
            ->where('booking_code', $code)
            ->firstOrFail();

        return response()->json([
            'data' => $booking,
            'meta' => ['business_type' => $booking->business->business_type],
        ]);
    }

    public function cancel(string $code, Request $request)
    {
        $booking = Booking::query()->where('booking_code', $code)->firstOrFail();

        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            return response()->json(['data' => $booking]);
        }

        $booking->update(['status' => 'cancelled']);
        return response()->json(['data' => $booking]);
    }
}
