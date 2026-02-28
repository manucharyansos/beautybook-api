<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id',    // փոխել salon_id-ից business_id
        'plan_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'note',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
