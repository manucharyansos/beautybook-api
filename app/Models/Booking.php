<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSalon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model {
    use HasFactory, BelongsToSalon;
    protected $fillable = [
        'salon_id',
        'service_id',
        'staff_id',
        'client_name',
        'client_phone',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'booking_code',
        'final_price','currency',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

}
