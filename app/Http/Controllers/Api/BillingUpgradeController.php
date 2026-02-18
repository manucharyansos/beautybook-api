<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class BillingUpgradeController extends Controller
{
    public function upgrade(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, [User::ROLE_OWNER, User::ROLE_MANAGER])) {
            abort(403);
        }

        $data = $request->validate([
            'plan_code' => ['required','string','exists:plans,code'],
        ]);

        $plan = Plan::where('code', $data['plan_code'])
            ->where('is_active', true)
            ->firstOrFail();

        $salon = $user->salon()->with('subscription')->firstOrFail();

        $sub = Subscription::firstOrCreate(
            ['salon_id' => $salon->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addMonth(),
            ]
        );

        // MVP: եթե plan-ը free է → անմիջապես active
        // Եթե վճարովի է → այս պահին էլ active ենք դնում (հետո փոխելու ենք վճարման logic-ով)
        $sub->update([
            'plan_id' => $plan->id,
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_starts_at' => $sub->current_period_starts_at ?? now(),
            'current_period_ends_at' => $sub->current_period_ends_at ?? now()->addMonth(),
            'canceled_at' => null,
        ]);

        // ensure salon not suspended
        if ($salon->billing_status === 'suspended') {
            $salon->update(['billing_status' => 'active', 'suspended_at' => null]);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'plan' => [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'seats' => $plan->seats,
                ],
                'subscription' => [
                    'status' => $sub->status,
                    'current_period_ends_at' => $sub->current_period_ends_at,
                ],
            ],
        ]);
    }
}
