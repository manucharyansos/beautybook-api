<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToSalon;

class Service extends Model
{
    use HasFactory, BelongsToSalon;
    protected $fillable = [
        'name',
        'duration_minutes',
        'price',
        'is_active',
        'currency',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }
}
