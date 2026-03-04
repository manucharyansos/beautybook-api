<?php
// app/Services/AvailabilityService.php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Models\Block;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    /**
     * Slots for a day inside business working hours, excluding staff bookings + blocks.
     * Assumes starts_at/ends_at in DB are stored in business local time (or consistent with business timezone usage).
     */
    public function slotsForDay(int $staffId, int $serviceId, string $date, ?int $businessId = null): array
    {
        // 1) Validate date strictly
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return [];
        }

        // 2) Resolve service (+ optional business scope)
        $serviceQ = Service::query()->whereKey($serviceId);
        if ($businessId) $serviceQ->where('business_id', $businessId);

        /** @var Service|null $service */
        $service = $serviceQ->first();
        if (!$service) return [];

        $businessId = $businessId ?: (int)$service->business_id;

        /** @var Business|null $business */
        $business = Business::query()->find($businessId);
        if (!$business) return [];

        /** @var User|null $staff */
        $staff = User::query()->find($staffId);
        if (!$staff || (int)$staff->business_id !== (int)$businessId) return [];

        // 3) Params
        $step = max(5, min(60, (int)($business->slot_step_minutes ?? 15)));
        $duration = (int)($service->duration_minutes ?? 0);
        if ($duration < 5 || $duration > 600) return [];

        $workStart = $business->work_start ?: '09:00';
        $workEnd   = $business->work_end   ?: '18:00';
        $tz = $business->timezone ?: config('app.timezone', 'Asia/Yerevan');

        // 4) Day bounds in business tz
        try {
            $dayStart = Carbon::parse($day->format('Y-m-d') . ' ' . $workStart, $tz)->seconds(0);
            $dayEnd   = Carbon::parse($day->format('Y-m-d') . ' ' . $workEnd,   $tz)->seconds(0);
        } catch (\Throwable $e) {
            return [];
        }

        if ($dayEnd->lte($dayStart)) $dayEnd = $dayEnd->addDay();

        $lastStart = $dayEnd->copy()->subMinutes($duration);
        if ($lastStart->lt($dayStart)) return [];

        $now = Carbon::now($tz)->seconds(0);
        $isToday = $dayStart->toDateString() === $now->toDateString();

        if ($dayEnd->lt($now)) return [];

        // 5) Busy bookings
        $busyBookings = Booking::query()
            ->where('business_id', $businessId)
            ->where('staff_id', $staffId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $dayEnd->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $dayStart->format('Y-m-d H:i:s'))
            ->get(['starts_at', 'ends_at', 'room_id']);

        // 6) Busy blocks (business-wide OR this staff)
        $busyBlocks = Block::query()
            ->where('business_id', $businessId)
            ->where(function ($q) use ($staffId) {
                $q->whereNull('staff_id')->orWhere('staff_id', $staffId);
            })
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $dayEnd->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $dayStart->format('Y-m-d H:i:s'))
            ->get(['starts_at', 'ends_at', 'staff_id']);

        $slots = [];

        for ($t = $dayStart->copy(); $t->lte($lastStart); $t->addMinutes($step)) {
            $start = $t->copy();
            $end   = $t->copy()->addMinutes($duration);

            // Avoid near-past
            if ($isToday && $start->lte($now->copy()->addMinutes(5))) {
                continue;
            }

            // Booking collision
            $collidesBooking = $busyBookings->contains(function ($b) use ($start, $end, $tz) {
                $bs = $b->starts_at instanceof Carbon ? $b->starts_at->copy() : Carbon::parse($b->starts_at, $tz);
                $be = $b->ends_at   instanceof Carbon ? $b->ends_at->copy()   : Carbon::parse($b->ends_at,   $tz);
                return $bs->lt($end) && $be->gt($start);
            });

            if ($collidesBooking) continue;

            // Block collision
            $collidesBlock = $busyBlocks->contains(function ($bl) use ($start, $end, $tz) {
                $bs = $bl->starts_at instanceof Carbon ? $bl->starts_at->copy() : Carbon::parse($bl->starts_at, $tz);
                $be = $bl->ends_at   instanceof Carbon ? $bl->ends_at->copy()   : Carbon::parse($bl->ends_at,   $tz);
                return $bs->lt($end) && $be->gt($start);
            });

            if ($collidesBlock) continue;

            $slot = [
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'ends_at'   => $end->format('Y-m-d H:i:s'),
            ];

            // Dental rooms (optional)
            if (method_exists($business, 'isDental') && $business->isDental()) {
                $slot['available_rooms'] = $this->getAvailableRooms($businessId, $start, $end, $busyBookings);
            }

            $slots[] = $slot;
        }

        return $slots;
    }

    private function getAvailableRooms(int $businessId, Carbon $start, Carbon $end, $existingBookings): array
    {
        $rooms = Room::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'type']);

        $busyRoomIds = $existingBookings
            ->whereNotNull('room_id')
            ->filter(function ($booking) use ($start, $end) {
                $bs = Carbon::parse($booking->starts_at);
                $be = Carbon::parse($booking->ends_at);
                return $bs->lt($end) && $be->gt($start);
            })
            ->pluck('room_id')
            ->toArray();

        return $rooms->whereNotIn('id', $busyRoomIds)->values()->toArray();
    }
}
