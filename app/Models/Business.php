<?php
// app/Models/Business.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'business_type', // salon | clinic
        'phone',
        'address',
        'is_onboarding_completed',
        'status',
        'billing_status',
        'suspended_at',
        'work_start',
        'work_end',
        'slot_step_minutes',
        'timezone',
    ];

    protected $casts = [
        'is_onboarding_completed' => 'boolean',
        'suspended_at' => 'datetime',
    ];

    // Relations
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function workingHours()
    {
        return $this->hasMany(BusinessWorkingHour::class);
    }

    public function staffSchedules()
    {
        return $this->hasMany(StaffWorkSchedule::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function contactRequests()
    {
        return $this->hasMany(ContactRequest::class);
    }

    // Owner relation
    public function owner()
    {
        return $this->hasOne(User::class)->where('role', User::ROLE_OWNER);
    }

    // Business type checks
    public function isSalon(): bool
    {
        return $this->business_type === 'salon';
    }

    public function isClinic(): bool
    {
        return $this->business_type === 'clinic';
    }

    // Seat management
    public function activeSeatCount(): int
    {
        return $this->users()
            ->where('is_active', true)
            ->whereIn('role', [
                User::ROLE_OWNER,
                User::ROLE_MANAGER,
                User::ROLE_STAFF,
            ])
            ->count();
    }

    public function seatLimit(): ?int
    {
        // snapshot-first
        $sub = $this->subscription;
        if ($sub && $sub->seatsLimit() !== null) return $sub->seatsLimit();

        // fallback (legacy)
        return $this->subscription?->plan?->staffLimit() ?? $this->subscription?->plan?->seats;
    }

    public function hasAvailableSeat(): bool
    {
        $limit = $this->seatLimit();
        if (!$limit) return true;
        return $this->activeSeatCount() < $limit;
    }

    public function seatUsers()
    {
        return $this->users()
            ->where('is_active', true)
            ->whereIn('role', [
                User::ROLE_OWNER,
                User::ROLE_MANAGER,
                User::ROLE_STAFF,
            ]);
    }
}
