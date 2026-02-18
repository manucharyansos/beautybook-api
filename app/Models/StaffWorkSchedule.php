<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSalon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffWorkSchedule extends Model
{
    use HasFactory, BelongsToSalon;

    protected $fillable = [
        'salon_id',
        'staff_id',
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }
}
