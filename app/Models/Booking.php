<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_code',
        'business_id',
        'service_id',
        'staff_id',
        'client_id',
        'room_id',
        'client_name',
        'client_phone',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'final_price',
        'currency',
        'clinical_notes',
        'treatment_codes',
        'is_emergency',

        // phone verification (public booking)
        'phone_verification_code_hash',
        'phone_verification_expires_at',
        'phone_verified_at',
        'phone_verification_attempts',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'treatment_codes' => 'array',
        'is_emergency' => 'boolean',
        'phone_verification_expires_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function isPhoneVerified(): bool
    {
        return (bool)$this->phone_verified_at;
    }
}
