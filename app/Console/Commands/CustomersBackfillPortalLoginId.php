<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Terbitkan `login_id` + baris `customer_portal_accounts` (status
 * `pending_claim`) untuk pelanggan yang belum punya akun portal
 * (docs/api/api-portal-pelanggan/, Fase 2 — termasuk ~1.900 pelanggan legacy
 * yang disebut dokumen).
 *
 * SENGAJA manual, bukan otomatis dari migration — sama alasannya seperti
 * `BackfillCustomerBalanceFromOverpay`: butuh review daftar dulu (POP yang
 * belum punya `registration_prefix` terisi akan menghasilkan `login_id`
 * cacat, mis. "-RQ000631"), bukan ditulis buta ke ~1.900 baris sekaligus.
 *
 * `password_hash` diisi PLACEHOLDER acak (bukan string kosong) — akun
 * `pending_claim` memang belum bisa dipakai login sampai diklaim (Fase 2
 * stub, klaim asli nunggu modul QR/PIN), tapi baris dengan hash lemah/
 * predictable tetap risiko kalau suatu saat logic `pending_claim` berubah.
 */
class CustomersBackfillPortalLoginId extends Command
{
    protected $signature = 'customers:backfill-portal-login-id {--dry-run : Tampilkan daftar tanpa menulis apa pun}';

    protected $description = 'Terbitkan login_id + akun portal pending_claim untuk pelanggan yang belum punya';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = Customer::query()
            ->whereDoesntHave('portalAccount')
            ->with('pop')
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Semua pelanggan sudah punya akun portal.');

            return self::SUCCESS;
        }

        $rows = [];
        $skipped = [];

        foreach ($candidates as $customer) {
            $prefix = $customer->pop?->registration_prefix;

            if (! $prefix) {
                $skipped[] = $customer;

                continue;
            }

            $rows[] = [$customer->id, $customer->full_name, "{$prefix}-{$customer->customer_code}"];
        }

        if ($rows !== []) {
            $this->table(['Customer ID', 'Nama', 'login_id yang akan dibuat'], $rows);
        }

        if ($skipped !== []) {
            $this->warn(count($skipped).' pelanggan DILEWATI — POP-nya belum punya registration_prefix:');
            foreach ($skipped as $customer) {
                $this->line("  - #{$customer->id} {$customer->full_name} (pop_id={$customer->pop_id})");
            }
        }

        if ($rows === []) {
            $this->info('Tidak ada baris yang bisa dibuat.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn(count($rows).' akun portal akan dibuat. Jalankan tanpa --dry-run untuk eksekusi.');

            return self::SUCCESS;
        }

        if (! $this->confirm(count($rows).' akun portal di atas akan dibuat, lanjutkan?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($candidates as $customer) {
            $prefix = $customer->pop?->registration_prefix;

            if (! $prefix) {
                continue;
            }

            CustomerPortalAccount::create([
                'customer_id' => $customer->id,
                'login_id' => "{$prefix}-{$customer->customer_code}",
                'password_hash' => Hash::make(Str::random(40)),
                'status' => 'pending_claim',
            ]);

            $created++;
        }

        $this->info("Selesai — {$created} akun portal dibuat.");

        return self::SUCCESS;
    }
}
