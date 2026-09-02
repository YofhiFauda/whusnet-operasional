<?php

namespace App\Console\Commands;

use App\Models\CustomerPortalAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Aktifkan akun portal `pending_claim` dan set password TANPA klaim asli —
 * satu-satunya jalan menguji `/auth/login` end-to-end (Postman/curl) selama
 * `/auth/claim` masih stub (docs/api/api-portal-pelanggan/, Fase 2, nunggu
 * modul QR/PIN). Diguard `local`/`testing` di baris pertama biar tidak
 * pernah kepakai di production — command ini secara sengaja melewati SEMUA
 * pengecekan PIN yang seharusnya jadi bukti identitas pelanggan.
 */
class CustomersPortalSetPasswordForTesting extends Command
{
    protected $signature = 'customers:portal-set-password-for-testing {login_id} {password}';

    protected $description = 'DEV ONLY — set password akun portal tanpa klaim PIN, buat smoke-test manual';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Command ini cuma boleh jalan di environment local/testing.');

            return self::FAILURE;
        }

        $account = CustomerPortalAccount::where('login_id', $this->argument('login_id'))->first();

        if (! $account) {
            $this->error('login_id tidak ditemukan.');

            return self::FAILURE;
        }

        $account->forceFill([
            'password_hash' => Hash::make($this->argument('password')),
            'status' => 'active',
            'claimed_at' => $account->claimed_at ?? now(),
            'failed_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $this->info("Password akun {$account->login_id} berhasil di-set (status=active).");

        return self::SUCCESS;
    }
}
