<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Http\Request;

class BillingMeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $salon = Salon::query()
            ->with(['subscription.plan'])
            ->findOrFail($user->salon_id);

        $sub = $salon->subscription;

        $isActive = $sub && $sub->isActive();
        $isSuspended = $salon->billing_status === 'suspended';

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

                'salon' => [
                    'id' => $salon->id,
                    'name' => $salon->name,
                    'slug' => $salon->slug,
                    'billing_status' => $salon->billing_status,
                    'suspended_at' => $salon->suspended_at,
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
