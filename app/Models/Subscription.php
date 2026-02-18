<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends \Illuminate\Database\Eloquent\Model {
    protected $fillable = [
        'salon_id','plan_id','status','trial_ends_at',
        'current_period_starts_at','current_period_ends_at','canceled_at',
        'provider','provider_customer_id','provider_subscription_id'
    ];
    protected $casts = [
        'trial_ends_at'=>'datetime',
        'current_period_starts_at'=>'datetime',
        'current_period_ends_at'=>'datetime',
        'canceled_at'=>'datetime',
    ];

    public function salon(){ return $this->belongsTo(\App\Models\Salon::class); }
    public function plan(){ return $this->belongsTo(\App\Models\Plan::class); }

    public function isActive(): bool {
        if ($this->status === 'active') return true;
        if ($this->status === 'trialing' && $this->trial_ends_at && now()->lt($this->trial_ends_at)) return true;
        return false;
    }
}

