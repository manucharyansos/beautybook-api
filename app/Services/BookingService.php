<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Service;
use App\Models\StaffWorkSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function makeBooking(
        User $actor,
        int $serviceId,
        int $staffId,
        string $dateTime, // "Y-m-d H:i"
        string $clientName,
        string $clientPhone,
        ?string $notes = null,
        string $status = 'confirmed'
    ): Booking {
        $service = Service::query()->findOrFail($serviceId);

        $startsAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $dateTime);
        $endsAt = $startsAt->addMinutes((int)$service->duration_minutes);

        $this->assertStaffAllowed($actor, $staffId);
        $this->assertSameSalon($actor, $service->salon_id);

        $staff = User::query()->findOrFail($staffId);
        $this->assertSameSalon($actor, $staff->salon_id);

        $this->assertWithinSchedule($actor->salon_id, $staffId, $startsAt, $endsAt);
        $this->assertNoOverlap($actor->salon_id, $staffId, $startsAt, $endsAt);

        return Booking::create([
            'service_id' => $service->id,
            'staff_id' => $staffId,
            'client_name' => $clientName,
            'client_phone' => $clientPhone,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'notes' => $notes,
            // salon_id will be auto set by BelongsToSalon creating hook
        ]);
    }

    private function assertStaffAllowed(User $actor, int $staffId): void
    {
        if ($actor->role === User::ROLE_STAFF && $actor->id !== $staffId) {
            throw ValidationException::withMessages([
                'staff_id' => 'Staff cannot create booking for another staff member.',
            ]);
        }
    }

    private function assertSameSalon(User $actor, ?int $entitySalonId): void
    {
        if ($actor->isSuperAdmin()) return;

        if (!$entitySalonId || $entitySalonId !== $actor->salon_id) {
            throw ValidationException::withMessages([
                'salon' => 'Invalid salon context.',
            ]);
        }
    }

    private function assertWithinSchedule(int $salonId, int $staffId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $dow = $start->dayOfWeek;

        $schedule = StaffWorkSchedule::query()
            ->where('salon_id', $salonId)
            ->where('staff_id', $staffId)
            ->where('day_of_week', $dow)
            ->first();

        if (!$schedule) {
            throw ValidationException::withMessages([
                'starts_at' => 'Staff has no working schedule for this day.',
            ]);
        }

        $workStart = CarbonImmutable::parse($start->format('Y-m-d').' '.$schedule->starts_at);
        $workEnd   = CarbonImmutable::parse($start->format('Y-m-d').' '.$schedule->ends_at);

        if (!($start >= $workStart && $end <= $workEnd)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Time is outside staff working hours.',
            ]);
        }
    }

    private function assertNoOverlap(int $salonId, int $staffId, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreBookingId = null): void
    {
        $q = Booking::query()
            ->where('salon_id', $salonId)
            ->where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);

        if ($ignoreBookingId) {
            $q->where('id', '!=', $ignoreBookingId);
        }

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'This time slot is already booked.',
            ]);
        }
    }
    public function updateBooking(User $actor, Booking $booking, array $data): Booking
    {
        // tenant safety
        if (!$actor->isSuperAdmin() && $booking->salon_id !== $actor->salon_id) {
            throw ValidationException::withMessages(['booking' => 'Invalid booking context.']);
        }

        // staff can update only own booking
        if ($actor->role === User::ROLE_STAFF && $booking->staff_id !== $actor->id) {
            throw ValidationException::withMessages(['booking' => 'Forbidden.']);
        }

        // What can change
        $serviceId = isset($data['service_id']) ? (int)$data['service_id'] : (int)$booking->service_id;
        $staffId   = isset($data['staff_id']) ? (int)$data['staff_id'] : (int)$booking->staff_id;

        // Staff actor cannot reassign to another staff
        $this->assertStaffAllowed($actor, $staffId);

        $service = Service::query()->findOrFail($serviceId);
        $this->assertSameSalon($actor, $service->salon_id);

        $staff = User::query()->findOrFail($staffId);
        $this->assertSameSalon($actor, $staff->salon_id);

        // starts_at change?
        $startsAt = isset($data['starts_at'])
            ? CarbonImmutable::createFromFormat('Y-m-d H:i', $data['starts_at'])
            : CarbonImmutable::parse($booking->starts_at);

        $endsAt = $startsAt->addMinutes((int)$service->duration_minutes);

        $this->assertWithinSchedule($actor->salon_id, $staffId, $startsAt, $endsAt);
        $this->assertNoOverlap($actor->salon_id, $staffId, $startsAt, $endsAt, $booking->id);

        $booking->fill([
            'service_id' => $service->id,
            'staff_id' => $staffId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        if (array_key_exists('client_name', $data)) {
            $booking->client_name = $data['client_name'];
        }
        if (array_key_exists('client_phone', $data)) {
            $booking->client_phone = $data['client_phone'];
        }
        if (array_key_exists('notes', $data)) {
            $booking->notes = $data['notes'];
        }
        if (array_key_exists('status', $data)) {
            $booking->status = $data['status'];
        }

        $booking->save();

        return $booking;
    }
}

