<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business; // Փոխել Salon-ից Business
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Http\Request;

class InvoiceAdminController extends Controller
{
    private function requireSuperAdmin(Request $request): void
    {
        $u = $request->user();
        if (!$u || !method_exists($u,'isSuperAdmin') || !$u->isSuperAdmin()) abort(403);
    }

    // GET /api/admin/invoices?status=pending
    public function index(Request $request)
    {
        $this->requireSuperAdmin($request);

        $status = $request->query('status', 'pending');

        $items = Invoice::query()
            ->where('status', $status)
            ->with(['business:id,name,slug', 'plan:id,name,code,price,currency,seats']) // Փոխել salon-ից business
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $items]);
    }

    // PATCH /api/admin/invoices/{invoice}/approve
    public function approve(Request $request, Invoice $invoice)
    {
        $this->requireSuperAdmin($request);

        if ($invoice->status !== 'pending') {
            return response()->json(['ok' => true]);
        }

        $sub = Subscription::firstOrCreate(
            ['business_id' => $invoice->business_id], // Փոխել salon_id-ից business_id
            [
                'plan_id' => $invoice->plan_id,
                'status' => 'active',
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addMonth(),
            ]
        );

        $sub->update([
            'plan_id' => $invoice->plan_id,
            'status' => 'active',
            'trial_ends_at' => null,
            'canceled_at' => null,
            'current_period_starts_at' => $sub->current_period_starts_at ?? now(),
            'current_period_ends_at' => $sub->current_period_ends_at ?? now()->addMonth(),
        ]);

        $invoice->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // restore business if suspended
        $business = $invoice->business; // Փոխել $salon-ից $business
        if ($business && $business->billing_status === 'suspended') {
            $business->update(['billing_status' => 'active', 'suspended_at' => null]);
        }

        return response()->json(['ok' => true]);
    }

    // PATCH /api/admin/invoices/{invoice}/reject
    public function reject(Request $request, Invoice $invoice)
    {
        $this->requireSuperAdmin($request);

        if ($invoice->status !== 'pending') {
            return response()->json(['ok' => true]);
        }

        $invoice->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
