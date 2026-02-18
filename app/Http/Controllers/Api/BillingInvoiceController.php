<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class BillingInvoiceController extends Controller
{
    // GET /api/billing/invoices
    public function index(Request $request)
    {
        $user = $request->user();

        $items = Invoice::query()
            ->where('salon_id', $user->salon_id)
            ->with(['plan:id,name,code,price,currency,seats'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $items]);
    }

    // POST /api/billing/upgrade-request
    // body: { "plan_code":"pro", "payment_method":"idram" (optional), "note":"..." (optional) }
    public function requestUpgrade(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, [User::ROLE_OWNER, User::ROLE_MANAGER])) {
            abort(403);
        }

        $data = $request->validate([
            'plan_code' => ['required','string','exists:plans,code'],
            'payment_method' => ['nullable','string','in:bank_transfer,idram,card,cash'],
            'note' => ['nullable','string','max:255'],
        ]);

        $plan = Plan::query()
            ->where('code', $data['plan_code'])
            ->where('is_active', true)
            ->firstOrFail();

        // Եթե plan-ը free է՝ միանգամից ակտիվացնում ենք (invoice պետք չի)
        if ((int)$plan->price === 0) {
            $sub = Subscription::firstOrCreate(
                ['salon_id' => $user->salon_id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_starts_at' => now(),
                    'current_period_ends_at' => now()->addMonth(),
                ]
            );

            $sub->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'canceled_at' => null,
            ]);

            return response()->json([
                'ok' => true,
                'mode' => 'instant',
                'data' => [
                    'plan' => ['code'=>$plan->code,'name'=>$plan->name,'price'=>$plan->price,'currency'=>$plan->currency],
                    'subscription_status' => $sub->status,
                ]
            ]);
        }

        // Որպեսզի spam չլինի՝ եթե կա pending invoice նույն plan-ի համար՝ վերադարձնենք դա
        $existing = Invoice::query()
            ->where('salon_id', $user->salon_id)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json(['ok' => true, 'mode' => 'invoice', 'data' => $existing]);
        }

        $salon = $user->salon()->with(['subscription.plan'])->firstOrFail();

        $limit = (int) $plan->seats;
        $activeCount = $salon->seatUsers()->count();

        if ($activeCount > $limit) {
            $users = $salon->seatUsers()
                ->orderByRaw("FIELD(role,'owner','manager','staff')")
                ->orderBy('id')
                ->get(['id','name','email','role','is_active']);

            return response()->json([
                'message' => 'Seat limit exceeded for selected plan. Deactivate staff first.',
                'data' => [
                    'selected_plan' => [
                        'code' => $plan->code,
                        'name' => $plan->name,
                        'seats' => $plan->seats,
                    ],
                    'active_seat_count' => $activeCount,
                    'seat_limit' => $limit,
                    'users' => $users,
                ]
            ], 409);
        }


        $invoice = Invoice::create([
            'salon_id' => $user->salon_id,
            'plan_id' => $plan->id,
            'amount' => (int)$plan->price,
            'currency' => $plan->currency ?? 'AMD',
            'status' => 'pending',
            'payment_method' => $data['payment_method'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        $bank = config('billing.bank');
        $idram = config('billing.idram');

        return response()->json([
            'ok' => true,
            'mode' => 'invoice',
            'data' => $invoice->load('plan:id,name,code,price,currency,seats'),
            'payment' => [
                'bank_transfer' => [
                    'company' => $bank['company_name'],
                    'bank_name' => $bank['bank_name'],
                    'account_number' => $bank['account_number'],
                    'recipient' => $bank['recipient_name'],
                    'payment_note' => str_replace(
                        [':id', ':salon'],
                        [$invoice->id, $invoice->salon_id],
                        $bank['note_template']
                    ),
                ],
                'idram' => [
                    'wallet' => $idram['wallet_id'],
                    'payment_note' => str_replace(
                        ':id',
                        $invoice->id,
                        $idram['note_template']
                    ),
                ],
                'message' => 'Վճարելուց հետո admin-ը կհաստատի invoice-ը և plan-ը կակտիվանա։',
            ],
        ], 201);
    }

    // POST /api/billing/invoices/{invoice}/cancel
    public function cancel(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        if ($invoice->salon_id !== $user->salon_id) abort(404);

        if ($invoice->status !== 'pending') {
            return response()->json(['ok' => true]);
        }

        $invoice->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
