<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Salon;
use Illuminate\Console\Command;

class BillingSweep extends Command
{
    protected $signature = 'billing:sweep {--dry-run}';
    protected $description = 'Auto-suspend salons when trial expired or invoices stale';

    public function handle(): int
    {
        $dry = (bool)$this->option('dry-run');

        // 1) Trial expired → suspend
        $salons = Salon::query()
            ->with(['subscription'])
            ->where('billing_status', 'active')
            ->get();

        $suspended = 0;

        foreach ($salons as $salon) {
            $sub = $salon->subscription;

            $isActive = $sub && $sub->isActive();
            if (!$isActive) {
                if (!$dry) {
                    $salon->update(['billing_status' => 'suspended', 'suspended_at' => now()]);
                }
                $suspended++;
            }
        }

        // 2) Pending invoice older than 48h → suspend (optional)
        $staleInvoices = Invoice::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(48))
            ->with('salon:id,billing_status')
            ->get();

        $staleSuspended = 0;
        foreach ($staleInvoices as $inv) {
            if ($inv->salon && $inv->salon->billing_status === 'active') {
                if (!$dry) {
                    $inv->salon->update(['billing_status'=>'suspended','suspended_at'=>now()]);
                }
                $staleSuspended++;
            }
        }

        $this->info("Suspended (trial/sub inactive): {$suspended}");
        $this->info("Suspended (stale invoices): {$staleSuspended}");

        return self::SUCCESS;
    }
}
