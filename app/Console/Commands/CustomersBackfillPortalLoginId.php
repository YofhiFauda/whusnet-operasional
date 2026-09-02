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
 * **BUKAN lagi satu-satunya jalur pembuatan akun (2026-08-27).** Titik
 * PENERBITAN resminya sekarang otomatis lewat `PortalAuthService::
 * ensureAccountExists()`, dipanggil dari titik yang sama dengan QR/PIN
 * (`CustomerWorkflowService` saat `WAITING_INSTALLATION`, & manual dari
 * `CustomerQrController::issue()`) — begitu kartu QR+PIN diterbitkan, akun
 * `pending_claim` ikut lahir di saat yang sama, gak perlu command manual
 * lagi. Command ini sekarang perannya cuma dua: (1) backfill SEKALI untuk
 * ~1.900 pelanggan legacy yang QR/PIN-nya udah lebih dulu ada sebelum hook
 * otomatis ini dipasang, (2) `--resync` buat baris basi (lihat komentar di
 * bawah). Pelanggan BARU gak lagi butuh command ini dijalankan manual.
 *
 * SENGAJA manual (buat backfill massal di atas), bukan otomatis dari
 * migration — sama alasannya seperti
 * `BackfillCustomerBalanceFromOverpay`: butuh review daftar dulu (POP yang
 * belum punya `cid_prefix` terisi akan menghasilkan `login_id` cacat, mis.
 * "-RQ000631"), bukan ditulis buta ke ~1.900 baris sekaligus.
 *
 * `password_hash` diisi PLACEHOLDER acak (bukan string kosong) — akun
 * `pending_claim` memang belum bisa dipakai login sampai diklaim (Fase 2
 * stub, klaim asli nunggu modul QR/PIN), tapi baris dengan hash lemah/
 * predictable tetap risiko kalau suatu saat logic `pending_claim` berubah.
 *
 * **`--resync` (2026-08-26, temuan verifikasi manual end-to-end):** formula
 * `login_id` DIREVISI setelah command ini pertama kali dipakai
 * (`registration_prefix` → `cid_prefix`, lihat `Customer::getPortalLoginIdAttribute()`
 * & `docs/api/api-portal-pelanggan/keputusan.md` §3 poin 1) — baris yang
 * SUDAH dibuat sebelum revisi itu MASIH menyimpan `login_id` versi lama,
 * padahal kartu/halaman QR yang staf cetak SEKARANG menampilkan versi baru
 * (`$customer->portal_login_id`, live accessor, selalu formula terkini).
 * Ketahuan lewat pengujian manual real terhadap DB dev (bukan test suite —
 * test suite selalu bikin akun baru pakai accessor terkini, jadi gak pernah
 * ke-exercise skenario data lama ini): 99 dari 100 akun di DB dev ternyata
 * stale, dan `/auth/claim` GAGAL kalau dicoba pakai `login_id` yang BENERAN
 * tercetak di kartu (soalnya itu beda dari yang tersimpan).
 *
 * `--resync` CUMA menyentuh baris `status=pending_claim` (belum pernah
 * berhasil diklaim — belum ada satu pun kredensial nyata yang bergantung ke
 * nilai lama). Baris `status=active` SENGAJA TIDAK disentuh sama sekali,
 * walau nilainya juga stale — pelanggan yang sudah klaim mungkin sudah tahu
 * & pernah login pakai `login_id` LAMA itu; menimpanya diam-diam berarti
 * mengunci akun pelanggan yang sudah aktif. Baris begitu (kalau ada)
 * dibiarkan basi dengan sengaja — bukan dibersihkan otomatis di sini.
 */
class CustomersBackfillPortalLoginId extends Command
{
    protected $signature = 'customers:backfill-portal-login-id
        {--dry-run : Tampilkan daftar tanpa menulis apa pun}
        {--resync : Perbarui login_id akun pending_claim yang basi (formula lama), BUKAN bikin akun baru}';

    protected $description = 'Terbitkan login_id + akun portal pending_claim untuk pelanggan yang belum punya (atau --resync akun pending_claim yang formulanya basi)';

    public function handle(): int
    {
        if ($this->option('resync')) {
            return $this->resyncStalePendingClaim();
        }

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
            $loginId = $customer->portal_login_id;

            if (! $loginId) {
                $skipped[] = $customer;

                continue;
            }

            $rows[] = [$customer->id, $customer->full_name, $loginId];
        }

        if ($rows !== []) {
            $this->table(['Customer ID', 'Nama', 'login_id yang akan dibuat'], $rows);
        }

        if ($skipped !== []) {
            $this->warn(count($skipped).' pelanggan DILEWATI — POP-nya belum punya cid_prefix:');
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
            $loginId = $customer->portal_login_id;

            if (! $loginId) {
                continue;
            }

            CustomerPortalAccount::create([
                'customer_id' => $customer->id,
                'login_id' => $loginId,
                'password_hash' => Hash::make(Str::random(40)),
                'status' => 'pending_claim',
            ]);

            $created++;
        }

        $this->info("Selesai — {$created} akun portal dibuat.");

        return self::SUCCESS;
    }

    private function resyncStalePendingClaim(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stale = CustomerPortalAccount::query()
            ->where('status', 'pending_claim')
            ->with('customer.pop')
            ->get()
            ->filter(function (CustomerPortalAccount $account) {
                $current = $account->customer?->portal_login_id;

                return $current !== null && $account->login_id !== $current;
            });

        if ($stale->isEmpty()) {
            $this->info('Tidak ada akun pending_claim yang login_id-nya basi.');

            return self::SUCCESS;
        }

        $rows = $stale->map(fn (CustomerPortalAccount $a) => [
            $a->customer_id, $a->customer->full_name, $a->login_id, $a->customer->portal_login_id,
        ])->all();

        $this->table(['Customer ID', 'Nama', 'login_id lama (basi)', 'login_id baru'], $rows);

        if ($dryRun) {
            $this->warn(count($rows).' akun pending_claim akan di-resync. Jalankan tanpa --dry-run untuk eksekusi.');

            return self::SUCCESS;
        }

        if (! $this->confirm(count($rows).' akun pending_claim di atas akan di-resync ke login_id formula terkini, lanjutkan?', true)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($stale as $account) {
            $account->update(['login_id' => $account->customer->portal_login_id]);
            $updated++;
        }

        $this->info("Selesai — {$updated} akun pending_claim di-resync.");

        return self::SUCCESS;
    }
}
