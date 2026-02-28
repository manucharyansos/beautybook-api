<?php
// app/Services/AvailabilityService.php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    public function slotsForDay(int $staffId, int $serviceId, string $date, ?int $businessId = null): array
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return [];
        }

        $service = Service::query()->whereKey($serviceId);
        if ($businessId) {
            $service->where('business_id', $businessId);
        }

        /** @var Service|null $service */
        $service = $service->first();
        if (!$service) return [];

        $businessId = $businessId ?: $service->business_id;

        /** @var Business|null $business */
        $business = Business::query()->find($businessId);
        if (!$business) return [];

        /** @var User|null $staff */
        $staff = User::query()->find($staffId);
        if (!$staff || $staff->business_id !== $businessId) return [];

        $step = max(5, min(60, (int)($business->slot_step_minutes ?? 15)));
        $duration = (int)($service->duration_minutes ?? 0);
        if ($duration < 5 || $duration > 600) return [];

        $workStart = $business->work_start ?: '09:00';
        $workEnd   = $business->work_end   ?: '18:00';
        $tz = $business->timezone ?: config('app.timezone', 'Asia/Yerevan');

        try {
            $dayStart = Carbon::parse($day->format('Y-m-d') . ' ' . $workStart, $tz)->seconds(0);
            $dayEnd   = Carbon::parse($day->format('Y-m-d') . ' ' . $workEnd,   $tz)->seconds(0);
        } catch (\Throwable $e) {
            return [];
        }

        if ($dayEnd->lte($dayStart)) {
            $dayEnd = $dayEnd->addDay();
        }

        $lastStart = $dayEnd->copy()->subMinutes($duration);
        if ($lastStart->lt($dayStart)) return [];

        $now = Carbon::now($tz)->seconds(0);
        $isToday = $dayStart->toDateString() === $now->toDateString();

        if ($dayEnd->lt($now)) {
            return [];
        }

        $busy = Booking::query()
            ->where('business_id', $businessId)
            ->where('staff_id', $staffId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $dayEnd->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $dayStart->format('Y-m-d H:i:s'))
            ->get(['starts_at', 'ends_at', 'room_id']);

        $slots = [];

        for ($t = $dayStart->copy(); $t->lte($lastStart); $t->addMinutes($step)) {
            $start = $t->copy();
            $end   = $t->copy()->addMinutes($duration);

            if ($isToday && $start->lte($now->copy()->addMinutes(5))) {
                continue;
            }

            $collides = $busy->contains(function ($b) use ($start, $end, $tz) {
                $bs = $b->starts_at instanceof Carbon
                    ? $b->starts_at->copy()
                    : Carbon::parse($b->starts_at, $tz);

                $be = $b->ends_at instanceof Carbon
                    ? $b->ends_at->copy()
                    : Carbon::parse($b->ends_at, $tz);

                return $bs->lt($end) && $be->gt($start);
            });

            if (!$collides) {
                $slot = [
                    'starts_at' => $start->format('Y-m-d H:i:s'),
                    'ends_at'   => $end->format('Y-m-d H:i:s'),
                ];

                // Dental-ի համար ավելացնենք room info (եթե պետք է)
                if ($business->isDental()) {
                    $slot['available_rooms'] = $this->getAvailableRooms($businessId, $start, $end, $busy);
                }

                $slots[] = $slot;
            }
        }

        return $slots;
    }

    private function getAvailableRooms(int $businessId, Carbon $start, Carbon $end, $existingBookings): array
    {
        // Ստանալ բոլոր ակտիվ rooms
        $rooms = Room::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'type']);

        // Գտնել զբաղված rooms այս ժամանակահատվածում
        $busyRoomIds = $existingBookings
            ->whereNotNull('room_id')
            ->filter(function ($booking) use ($start, $end) {
                $bs = Carbon::parse($booking->starts_at);
                $be = Carbon::parse($booking->ends_at);
                return $bs->lt($end) && $be->gt($start);
            })
            ->pluck('room_id')
            ->toArray();

        // Վերադարձնել ազատ rooms
        return $rooms->whereNotIn('id', $busyRoomIds)->values()->toArray();
    }
}
