<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'phone',
        'address',
        'status',
        'work_start',
        'work_end',
        'slot_step_minutes',
    ];

    /* Relations */

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function activeSeatCount(): int
    {
        return $this->users()
            ->where('is_active', true)
            ->whereIn('role', [
                \App\Models\User::ROLE_OWNER,
                \App\Models\User::ROLE_MANAGER,
                \App\Models\User::ROLE_STAFF,
            ])
            ->count();
    }

    public function seatLimit(): ?int
    {
        return $this->subscription?->plan?->seats;
    }

    public function hasAvailableSeat(): bool
    {
        $limit = $this->seatLimit();
        if (!$limit) return true; // եթե plan չկա՝ չենք սահմանափակում
        return $this->activeSeatCount() < $limit;
    }

    public function seatUsers()
    {
        return $this->users()
            ->where('is_active', true)
            ->whereIn('role', [
                \App\Models\User::ROLE_OWNER,
                \App\Models\User::ROLE_MANAGER,
                \App\Models\User::ROLE_STAFF,
            ]);
    }
}
