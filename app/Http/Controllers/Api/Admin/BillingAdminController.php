<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use Illuminate\Http\Request;

class BillingAdminController extends Controller
{
    private function requireSuperAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isSuperAdmin()) {
            abort(403);
        }
    }

    // PATCH /api/admin/salons/{salon}/suspend
    public function suspend(Request $request, Salon $salon)
    {
        $this->requireSuperAdmin($request);

        $salon->update([
            'billing_status' => 'suspended',
            'suspended_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // PATCH /api/admin/salons/{salon}/restore
    public function restore(Request $request, Salon $salon)
    {
        $this->requireSuperAdmin($request);

        $salon->update([
            'billing_status' => 'active',
            'suspended_at' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    // PATCH /api/admin/salons/{salon}/plan
    // body: { "plan_code": "pro" }
    public function changePlan(Request $request, Salon $salon)
    {
        $this->requireSuperAdmin($request);

        $data = $request->validate([
            'plan_code' => ['required','string','exists:plans,code'],
        ]);

        $plan = Plan::where('code', $data['plan_code'])->firstOrFail();

        $sub = Subscription::firstOrCreate(
            ['salon_id' => $salon->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addMonth(),
            ]
        );

        $sub->update([
            'plan_id' => $plan->id,
        ]);

        return response()->json(['ok' => true]);
    }

    // PATCH /api/admin/salons/{salon}/trial
    // body: { "days": 7 }
    public function extendTrial(Request $request, Salon $salon)
    {
        $this->requireSuperAdmin($request);

        $data = $request->validate([
            'days' => ['required','integer','min:1','max:365'],
        ]);

        $sub = Subscription::firstOrCreate(
            ['salon_id' => $salon->id],
            [
                'plan_id' => Plan::where('code','starter')->value('id') ?? 1,
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]
        );

        $sub->update([
            'status' => 'trialing',
            'trial_ends_at' => ($sub->trial_ends_at ?? now())->addDays((int)$data['days']),
        ]);

        return response()->json(['ok' => true, 'trial_ends_at' => $sub->trial_ends_at]);
    }
}
