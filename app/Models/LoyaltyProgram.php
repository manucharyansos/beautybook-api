<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyProgram extends Model
{
    protected $fillable = [
        'business_id',
        'is_enabled',
        'points_per_currency_unit', // e.g. 1 point per 100 AMD
        'currency_unit',            // e.g. 100
        'min_booking_amount',       // e.g. 0
        'notes',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'points_per_currency_unit' => 'integer',
        'currency_unit' => 'integer',
        'min_booking_amount' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
