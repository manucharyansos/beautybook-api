<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToBusiness;

class Service extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'name',
        'duration_minutes',
        'price',
        'is_active',
        'currency',
        'business_id', // Փոխել salon_id-ից business_id
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function business() // Փոխել salon() -ից business()
    {
        return $this->belongsTo(Business::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
