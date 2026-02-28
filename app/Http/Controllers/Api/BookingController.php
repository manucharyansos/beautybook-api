<?php
// app/Http/Controllers/Api/BookingController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Models\BookingBlock;

class BookingController extends Controller
{
    /**
     * GET /api/bookings
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        $q = Booking::query()->with(['service', 'staff', 'business']);

        // Scope by business / staff
        if ($actor->isSuperAdmin()) {
            if ($request->filled('business_id')) {
                $q->where('business_id', $request->integer('business_id'));
            }
        } else {
            $q->where('business_id', $actor->business_id);

            if ($actor->role === User::ROLE_STAFF) {
                $q->where('staff_id', $actor->id);
            }
        }

        // Filter by date
        if ($request->filled('date')) {
            $q->whereDate('starts_at', $request->date);
        }

        // Filter by week (optional legacy)
        if ($request->filled('week_start')) {
            $weekStart = Carbon::parse($request->week_start)->startOfWeek(Carbon::MONDAY);
            $weekEnd = (clone $weekStart)->addDays(7);
            $q->whereBetween('starts_at', [$weekStart, $weekEnd]);
        }

        // Optional filters
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('staff_id')) {
            $q->where('staff_id', $request->integer('staff_id'));
        }

        // Date filter helpers (Calendar uses from/to)
        if ($request->filled('from') && $request->filled('to')) {
            $q->whereBetween('starts_at', [
                Carbon::parse($request->string('from'))->startOfDay(),
                Carbon::parse($request->string('to'))->endOfDay(),
            ]);
        } elseif ($request->filled('days')) {
            $days = max(1, min(365, (int)$request->integer('days')));
            $q->whereBetween('starts_at', [now()->subDays($days)->startOfDay(), now()->endOfDay()]);
        }

        // Hide public bookings pending phone verification (default)
        if (!$request->boolean("include_unverified")) {
            $q->where(function ($qq) {
                $qq->whereNull("phone_verification_code_hash")
                    ->orWhereNotNull("phone_verified_at");
            });
        }

        return response()->json([
            'data' => $q->orderByDesc('starts_at')->get()
        ]);
    }

    /**
     * GET /api/bookings/{booking}
     */
    public function show(Request $request, Booking $booking)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int)$booking->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        return response()->json([
            'data' => $booking->load(['service', 'staff', 'business', 'client', 'room'])
        ]);
    }

    /**
     * POST /api/bookings
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        $data = $request->validate([
            'service_id'   => ['required','integer','exists:services,id'],
            'staff_id'     => ['required','integer','exists:users,id'],
            'starts_at'    => ['required','date_format:Y-m-d H:i'],
            'client_name'  => ['required','string','max:120'],
            'client_phone' => ['required','string','max:40'],
            'client_id'    => ['nullable','integer','exists:clients,id'],
            'notes'        => ['nullable','string','max:2000'],
            'status'       => ['nullable','in:pending,confirmed'],
            'room_id'      => ['nullable','integer','exists:rooms,id'],
        ]);

        /** @var Service $service */
        $service = Service::query()->findOrFail((int)$data['service_id']);

        // Service must belong to same business
        if (!$actor->isSuperAdmin() && (int)$service->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        // Staff must belong to same business
        $staff = User::query()->findOrFail((int)$data['staff_id']);
        if (!$actor->isSuperAdmin() && (int)$staff->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        // staff user can only create for self
        if ($actor->role === User::ROLE_STAFF && (int)$staff->id !== (int)$actor->id) {
            abort(403);
        }

        // Compute ends_at from duration in business timezone
        $business = $actor->business;
        $tz = $business?->timezone ?? config('app.timezone', 'Asia/Yerevan');

        $startLocal = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at'], $tz)->seconds(0);

        $duration = (int)($service->duration_minutes ?? 0);
        if ($duration < 5 || $duration > 600) {
            return response()->json(['message' => 'Invalid service duration'], 422);
        }

        $endLocal = (clone $startLocal)->addMinutes($duration);

        // Check for overlapping bookings
        $this->checkOverlap(
            (int)$actor->business_id,
            (int)$data['staff_id'],
            $startLocal,
            $endLocal
        );

        // Check blocked time (break/day off)
        $this->checkBlocked(
            (int)$actor->business_id,
            (int)$data['staff_id'],
            $startLocal,
            $endLocal
        );

        $bookingData = [
            'business_id'   => $actor->isSuperAdmin() ? $service->business_id : $actor->business_id,
            'service_id'    => (int)$data['service_id'],
            'staff_id'      => (int)$data['staff_id'],
            'client_name'   => $data['client_name'],
            'client_phone'  => $data['client_phone'],
            'client_id'     => $data['client_id'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'status'        => $data['status'] ?? 'pending',
            'starts_at'     => $startLocal->format('Y-m-d H:i:s'),
            'ends_at'       => $endLocal->format('Y-m-d H:i:s'),
            'final_price'   => $service->price ?? null,
            'currency'      => $service->currency ?? 'AMD',
            'booking_code'  => $this->generateBookingCode(),
        ];

        // Dental-ի համար ավելացնել room_id
        if ($business?->isDental() && !empty($data['room_id'])) {
            $bookingData['room_id'] = $data['room_id'];
        }

        $booking = Booking::create($bookingData);

        return response()->json(['data' => $booking->load(['service', 'staff'])], 201);
    }

    /**
     * PUT /api/bookings/{booking}
     */
    public function update(Request $request, Booking $booking)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int)$booking->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        // staff can update only own bookings
        if ($actor->role === User::ROLE_STAFF && (int)$booking->staff_id !== (int)$actor->id) {
            abort(403);
        }

        $data = $request->validate([
            'client_name' => ['sometimes', 'string', 'max:120'],
            'client_phone' => ['sometimes', 'string', 'max:40'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'starts_at' => ['sometimes', 'date_format:Y-m-d H:i'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'in:pending,confirmed,cancelled,done'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        $business = $actor->business;
        $tz = $business?->timezone ?? config('app.timezone', 'Asia/Yerevan');

        /**
         * 1) If service changed -> recompute ends_at + final_price snapshot
         */
        if (!empty($data['service_id'])) {
            $service = Service::query()->findOrFail((int)$data['service_id']);

            if (!$actor->isSuperAdmin() && (int)$service->business_id !== (int)$actor->business_id) {
                abort(404);
            }

            $duration = (int)($service->duration_minutes ?? 0);
            if ($duration < 5 || $duration > 600) {
                return response()->json(['message' => 'Invalid service duration'], 422);
            }

            $startsInput = $data['starts_at'] ?? Carbon::parse($booking->starts_at)->timezone($tz)->format('Y-m-d H:i');
            $startLocal = Carbon::createFromFormat('Y-m-d H:i', $startsInput, $tz)->seconds(0);
            $endLocal = (clone $startLocal)->addMinutes($duration);

            // overlap + blocked checks
            $staffIdForCheck = (int)($data['staff_id'] ?? $booking->staff_id);
            $this->checkOverlap((int)$actor->business_id, $staffIdForCheck, $startLocal, $endLocal, (int)$booking->id);
            $this->checkBlocked((int)$actor->business_id, $staffIdForCheck, $startLocal, $endLocal);

            $data['ends_at'] = $endLocal->format('Y-m-d H:i:s');
            $data['final_price'] = $service->price ?? null;
            $data['currency'] = $service->currency ?? 'AMD';
        }

        /**
         * 2) If time changed without service change -> recompute ends_at from existing service duration
         */
        if (!empty($data['starts_at']) && empty($data['service_id'])) {
            $existingService = $booking->service;
            $duration = (int)($existingService?->duration_minutes ?? 0);

            $startLocal = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at'], $tz)->seconds(0);
            $endLocal = $duration >= 5 && $duration <= 600
                ? (clone $startLocal)->addMinutes($duration)
                : Carbon::parse($booking->ends_at);

            // checks
            $staffIdForCheck = (int)($data['staff_id'] ?? $booking->staff_id);
            $this->checkOverlap((int)$actor->business_id, $staffIdForCheck, $startLocal, $endLocal, (int)$booking->id);
            $this->checkBlocked((int)$actor->business_id, $staffIdForCheck, $startLocal, $endLocal);

            $data['ends_at'] = $endLocal->format('Y-m-d H:i:s');

            // normalize starts_at
            $data['starts_at'] = $startLocal->format('Y-m-d H:i:s');
        }

        /**
         * 3) Normalize starts_at if provided but not normalized yet (service changed case)
         */
        if (!empty($data['starts_at']) && strlen($data['starts_at']) === 16) {
            // "Y-m-d H:i" -> "Y-m-d H:i:s"
            $data['starts_at'] = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at'], $tz)->seconds(0)->format('Y-m-d H:i:s');
        }

        /**
         * 4) Staff change validation + checks (IMPORTANT FIX)
         */
        if (!empty($data['staff_id'])) {
            $staff = User::query()->findOrFail((int)$data['staff_id']);

            if (!$actor->isSuperAdmin() && (int)$staff->business_id !== (int)$actor->business_id) {
                abort(404);
            }

            // staff user cannot reassign to another staff
            if ($actor->role === User::ROLE_STAFF && (int)$staff->id !== (int)$actor->id) {
                abort(403);
            }

            $start = Carbon::parse($data['starts_at'] ?? $booking->starts_at);
            $end   = Carbon::parse($data['ends_at'] ?? $booking->ends_at);

            $this->checkOverlap((int)$actor->business_id, (int)$staff->id, $start, $end, (int)$booking->id);
            $this->checkBlocked((int)$actor->business_id, (int)$staff->id, $start, $end);
        }

        $booking->update($data);

        return response()->json(['data' => $booking->fresh(['service', 'staff', 'room'])]);
    }

    /**
     * PATCH /api/bookings/{booking}/confirm
     */
    public function confirm(Request $request, Booking $booking)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int)$booking->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        if ($actor->role === User::ROLE_STAFF && (int)$booking->staff_id !== (int)$actor->id) {
            abort(403);
        }

        if (!in_array($booking->status, ['pending'])) {
            return response()->json(['message' => 'Only pending bookings can be confirmed'], 422);
        }

        $booking->update(['status' => 'confirmed']);

        return response()->json(['ok' => true, 'data' => $booking]);
    }

    /**
     * PATCH /api/bookings/{booking}/cancel
     */
    public function cancel(Request $request, Booking $booking)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int)$booking->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        if ($actor->role === User::ROLE_STAFF && (int)$booking->staff_id !== (int)$actor->id) {
            abort(403);
        }

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Only pending or confirmed bookings can be cancelled'], 422);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json(['ok' => true]);
    }

    /**
     * PATCH /api/bookings/{booking}/done
     */
    public function done(Request $request, Booking $booking)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int)$booking->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        if ($actor->role === User::ROLE_STAFF && (int)$booking->staff_id !== (int)$actor->id) {
            abort(403);
        }

        if (!in_array($booking->status, ['confirmed'])) {
            return response()->json(['message' => 'Only confirmed bookings can be marked as done'], 422);
        }

        $booking->update(['status' => 'done']);

        return response()->json(['ok' => true]);
    }

    /**
     * PATCH /api/bookings/{booking}/time
     */
    public function updateTime(Request $request, Booking $booking)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int)$booking->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        if ($actor->role === User::ROLE_STAFF && (int)$booking->staff_id !== (int)$actor->id) {
            abort(403);
        }

        $data = $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i:s', 'after:starts_at'],
        ]);

        $business = $actor->business;
        $tz = $business?->timezone ?? config('app.timezone', 'Asia/Yerevan');

        $startsAt = Carbon::parse($data['starts_at'], $tz);
        $endsAt = Carbon::parse($data['ends_at'], $tz);

        $this->checkOverlap(
            (int)$actor->business_id,
            (int)$booking->staff_id,
            $startsAt,
            $endsAt,
            (int)$booking->id
        );

        // ✅ IMPORTANT: blocked check was missing
        $this->checkBlocked(
            (int)$actor->business_id,
            (int)$booking->staff_id,
            $startsAt,
            $endsAt
        );

        $booking->update([
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
        ]);

        return response()->json([
            'ok' => true,
            'data' => $booking->fresh(['service', 'staff'])
        ]);
    }

    /**
     * Check for overlapping bookings
     */
    private function checkOverlap(
        int $businessId,
        int $staffId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreBookingId = null
    ): void {
        $query = Booking::query()
            ->where('business_id', $businessId)
            ->where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);

        if ($ignoreBookingId) {
            $query->where('id', '!=', $ignoreBookingId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => ['This time slot is already booked'],
            ]);
        }
    }

    /**
     * Generate unique booking code
     */
    private function generateBookingCode(): string
    {
        do {
            $code = 'BK-' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }

    /**
     * Check if time is blocked (break / day off)
     */
    private function checkBlocked(int $businessId, int $staffId, Carbon $start, Carbon $end): void
    {
        $blocked = BookingBlock::query()
            ->where('business_id', $businessId)
            ->where(function ($q) use ($staffId) {
                $q->whereNull('staff_id')->orWhere('staff_id', $staffId);
            })
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'starts_at' => ['This time is blocked (break / day off).'],
            ]);
        }
    }
}
