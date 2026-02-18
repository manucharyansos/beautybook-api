<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    /**
     * Slots inside salon work hours excluding staff bookings.
     * Assumes DB stores starts_at/ends_at in the SAME timezone as written (most projects: local salon time).
     * If your DB is UTC, tell me ու ես կփոխեմ 2 տողով՝ UTC conversion-ով։
     */
    public function slotsForDay(int $staffId, int $serviceId, string $date, ?int $salonId = null): array
    {
        // 1) strict date
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return [];
        }

        // 2) service
        $serviceQuery = Service::query()->whereKey($serviceId);
        if ($salonId) $serviceQuery->where('salon_id', $salonId);

        /** @var Service|null $service */
        $service = $serviceQuery->first();
        if (!$service) return [];

        $salonId = $salonId ?: (int)$service->salon_id;

        /** @var Salon|null $salon */
        $salon = Salon::query()->find($salonId);
        if (!$salon) return [];

        /** @var User|null $staff */
        $staff = User::query()->find($staffId);
        if (!$staff) return [];
        if (isset($staff->salon_id) && (int)$staff->salon_id !== (int)$salonId) return [];

        // 3) config
        $step = (int)($salon->slot_step_minutes ?? 15);
        $step = max(5, min(60, $step));

        $duration = (int)($service->duration_minutes ?? 0);
        if ($duration < 5 || $duration > 600) return [];

        $workStart = $salon->work_start;
        $workEnd   = $salon->work_end;
        if (!$workStart || !$workEnd) return [];

        // 4) timezone (for generating slots)
        $tz = $salon->timezone ?? config('app.timezone'); // Asia/Yerevan

        // 5) build working window in salon tz
        try {
            $dayStart = Carbon::parse($day->format('Y-m-d').' '.$workStart, $tz)->seconds(0);
            $dayEnd   = Carbon::parse($day->format('Y-m-d').' '.$workEnd,   $tz)->seconds(0);
        } catch (\Throwable $e) {
            return [];
        }

        // overnight support
        if ($dayEnd->lte($dayStart)) $dayEnd = $dayEnd->addDay();

        $lastStart = $dayEnd->copy()->subMinutes($duration);
        if ($lastStart->lt($dayStart)) return [];

        /**
         * IMPORTANT:
         * Here we query bookings that OVERLAP the working window.
         * This version compares with strings in the SAME tz as stored.
         * If your DB is UTC, we will convert dayStart/dayEnd to UTC here.
         */
        $busy = Booking::query()
            ->where('salon_id', $salonId)
            ->where('staff_id', $staffId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereColumn('ends_at', '>', 'starts_at')
            ->where('starts_at', '<', $dayEnd->format('Y-m-d H:i:s'))
            ->where('ends_at',   '>', $dayStart->format('Y-m-d H:i:s'))
            ->get(['starts_at', 'ends_at']);

        $slots = [];

        for ($t = $dayStart->copy(); $t->lte($lastStart); $t->addMinutes($step)) {
            $start = $t->copy();
            $end   = $t->copy()->addMinutes($duration);

            $collides = $busy->contains(function ($b) use ($start, $end, $tz) {
                $bs = $b->starts_at instanceof Carbon ? $b->starts_at->copy() : Carbon::parse($b->starts_at, $tz);
                $be = $b->ends_at   instanceof Carbon ? $b->ends_at->copy()   : Carbon::parse($b->ends_at,   $tz);

                // ensure same tz
                $bs = $bs->setTimezone($tz);
                $be = $be->setTimezone($tz);

                return $bs->lt($end) && $be->gt($start);
            });

            if (!$collides) {
                $slots[] = [
                    'starts_at' => $start->format('Y-m-d H:i:s'),
                    'ends_at'   => $end->format('Y-m-d H:i:s'),
                ];
            }
        }

        // debug log (keep)
        logger()->info('availability_debug', [
            'date' => $date,
            'salon_id' => $salonId,
            'staff_id' => $staffId,
            'service_id' => $serviceId,
            'tz' => $tz,
            'dayStart' => $dayStart->toDateTimeString(),
            'dayEnd' => $dayEnd->toDateTimeString(),
            'step' => $step,
            'duration' => $duration,
            'busyCount' => $busy->count(),
            'slotsCount' => count($slots),
        ]);

        return $slots;
    }
}
