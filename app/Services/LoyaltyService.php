<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Client;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyPointLedger;
use App\Models\User;

class LoyaltyService
{
    public function getOrCreateProgram(int $businessId): LoyaltyProgram
    {
        return LoyaltyProgram::query()->firstOrCreate(
            ['business_id' => $businessId],
            [
                'is_enabled' => false,
                'currency_unit' => 1000,
                'points_per_currency_unit' => 1,
                'min_booking_amount' => 0,
            ]
        );
    }

    public function computePoints(LoyaltyProgram $program, int $amount): int
    {
        if (!$program->is_enabled) return 0;
        if ($amount < (int)$program->min_booking_amount) return 0;

        $unit = max(1, (int)$program->currency_unit);
        $pp = max(0, (int)$program->points_per_currency_unit);
        if ($pp === 0) return 0;

        return intdiv($amount, $unit) * $pp;
    }

    public function awardForBookingDone(User $actor, Booking $booking): ?LoyaltyPointLedger
    {
        if (!$booking->client_id) return null;

        $program = $this->getOrCreateProgram((int)$booking->business_id);
        if (!$program->is_enabled) return null;

        $amount = (int)($booking->final_price ?? 0);
        $points = $this->computePoints($program, $amount);
        if ($points <= 0) return null;

        // idempotency: prevent double-award
        $exists = LoyaltyPointLedger::query()
            ->where('business_id', $booking->business_id)
            ->where('booking_id', $booking->id)
            ->where('delta_points', '>', 0)
            ->exists();
        if ($exists) return null;

        return LoyaltyPointLedger::create([
            'business_id' => $booking->business_id,
            'client_id' => $booking->client_id,
            'booking_id' => $booking->id,
            'delta_points' => $points,
            'reason' => 'Booking done (auto)',
            'created_by' => $actor->id,
        ]);
    }

    public function adjust(User $actor, Client $client, int $delta, ?string $reason = null): LoyaltyPointLedger
    {
        return LoyaltyPointLedger::create([
            'business_id' => $client->business_id,
            'client_id' => $client->id,
            'booking_id' => null,
            'delta_points' => $delta,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
    }
}
