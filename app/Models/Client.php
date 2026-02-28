<?php
// app/Models/Client.php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'notes',
        'birth_date',
        'blood_type',
        'medical_history',
        'allergies',
        'emergency_contact_name',
        'emergency_contact_phone',
        'medical_notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'medical_notes' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // Scopes
    public function scopeForDental($query)
    {
        return $query->whereHas('business', fn($q) => $q->where('business_type', 'clinic'));
    }
}
