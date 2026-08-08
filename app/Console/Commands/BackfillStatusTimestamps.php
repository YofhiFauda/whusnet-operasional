<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fase 5.1 — isi kolom `rejected_at`/`terminated_at` untuk pelanggan yang sudah
 * ada SEBELUM kolom ini dibuat (data hasil import lama). Ambil tanggal dari
 * AuditLog transisi/terminate terakhir per customer.
 *
 * Idempoten & aman diulang: hanya menyentuh baris yang kolomnya masih NULL.
 * Import baru sudah mengisi kolom langsung, jadi command ini murni untuk data
 * lama; tidak perlu dijalankan setelah `migrate:fresh` + import ulang.
 */
class BackfillStatusTimestamps extends Command
{
    protected $signature = 'customers:backfill-status-timestamps';

    protected $description = 'Isi rejected_at/terminated_at dari audit_logs untuk pelanggan lama (Fase 5.1).';

    public function handle(): int
    {
        $rejected = $this->backfillRejected();
        $terminated = $this->backfillTerminated();

        $this->info("rejected_at diisi : {$rejected}");
        $this->info("terminated_at diisi: {$terminated}");

        return self::SUCCESS;
    }

    private function backfillRejected(): int
    {
        // Tanggal reject terakhir per customer dari audit transisi ke 'rejected'.
        $latest = AuditLog::query()
            ->where('auditable_type', Customer::class)
            ->where('module', 'Customer Workflow')
            ->where('action', 'status_transition')
            ->whereJsonContains('new_values->status', 'rejected')
            ->groupBy('auditable_id')
            ->select('auditable_id', DB::raw('MAX(created_at) as at'))
            ->pluck('at', 'auditable_id');

        return $this->apply($latest, 'rejected_at', 'rejected');
    }

    private function backfillTerminated(): int
    {
        $latest = AuditLog::query()
            ->where('auditable_type', Customer::class)
            ->where('module', 'customers')
            ->where('action', 'terminate')
            ->groupBy('auditable_id')
            ->select('auditable_id', DB::raw('MAX(created_at) as at'))
            ->pluck('at', 'auditable_id');

        return $this->apply($latest, 'terminated_at', 'terminated');
    }

    /**
     * @param  Collection<int, mixed>  $timestamps  [customer_id => created_at]
     */
    private function apply(Collection $timestamps, string $column, string $status): int
    {
        $count = 0;

        foreach ($timestamps as $customerId => $at) {
            // Hanya isi yang masih NULL — idempoten, tidak menimpa data baru.
            $affected = Customer::whereKey($customerId)
                ->whereNull($column)
                ->update([$column => $at]);
            $count += $affected;
        }

        return $count;
    }
}
