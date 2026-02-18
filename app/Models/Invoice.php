<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'salon_id','plan_id','amount','currency',
        'status','payment_method','note',
        'approved_at','rejected_at','cancelled_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function salon() { return $this->belongsTo(Salon::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
}
