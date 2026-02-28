<?php
// app/Http/Controllers/Api/BillingMeController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business; // Փոխել Salon-ից Business
use Illuminate\Http\Request;

class BillingMeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $business = Business::query() // Փոխել Salon-ից Business
        ->with(['subscription.plan'])
            ->findOrFail($user->business_id); // Փոխել salon_id-ից business_id

        $sub = $business->subscription;

        $isActive = $sub && $sub->isActive();
        $isSuspended = $business->billing_status === 'suspended';

        $reason = null;
        if ($isSuspended) $reason = 'suspended';
        elseif (!$sub) $reason = 'no_subscription';
        elseif (!$isActive) $reason = 'subscription_inactive';

        $nextAction = null;
        if ($reason === 'subscription_inactive' || $reason === 'no_subscription') {
            $nextAction = 'create_invoice';
        }
        if ($reason === 'suspended') {
            $nextAction = 'contact_support';
        }

        return response()->json([
            'data' => [
                'is_billable' => (!$isSuspended) && $isActive,
                'reason' => $reason,
                'next_action' => $nextAction,

                'business' => [ // Փոխել salon-ից business
                    'id' => $business->id,
                    'name' => $business->name,
                    'slug' => $business->slug,
                    'business_type' => $business->business_type,
                    'billing_status' => $business->billing_status,
                    'suspended_at' => $business->suspended_at,
                ],

                'subscription' => $sub ? [
                    'status' => $sub->status,
                    'trial_ends_at' => $sub->trial_ends_at,
                    'current_period_ends_at' => $sub->current_period_ends_at,
                    'plan' => $sub->plan ? [
                        'code' => $sub->plan->code,
                        'name' => $sub->plan->name,
                        'price' => $sub->plan->price,
                        'currency' => $sub->plan->currency,
                        'seats' => $sub->plan->seats,
                    ] : null,
                ] : null,
            ]
        ]);
    }
}
