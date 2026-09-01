<?php

namespace App\Services\CustomerPortal;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\CustomerPortalToken;
use App\Models\User;
use App\Services\CustomerQrTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Business logic auth portal pelanggan (docs/api/api-portal-pelanggan/,
 * Fase 2) — diekstrak dari `PortalAuthController` (2026-08-25) supaya
 * konsisten pola repo: controller tipis, Service isi business logic
 * (CLAUDE.md "Pembagian layer"). Perilaku PERSIS SAMA seperti sebelum
 * refactor — murni dipindah, bukan ditulis ulang.
 */
class PortalAuthService
{
    private const ACCESS_TTL_MINUTES = 15;

    private const REFRESH_TTL_MINUTES = 60 * 24 * 30; // 30 hari

    public function __construct(private readonly CustomerQrTokenService $qrTokens) {}

    /**
     * Jaminan idempoten: pastikan pelanggan punya baris `customer_portal_accounts`
     * (`pending_claim`) — dipanggil dari titik yang SAMA dengan penerbitan QR
     * (`CustomerWorkflowService` saat `WAITING_INSTALLATION` & `CustomerQrController::issue()`
     * manual), BUKAN cuma dari `customers:backfill-portal-login-id`.
     *
     * Sebelum ini ada, akun portal HANYA lahir lewat command backfill —
     * pelanggan baru pasca command terakhir jalan (atau pasca `migrate:fresh`)
     * dapat kartu QR+PIN dari staf tapi `/auth/claim` gagal 401 generik karena
     * baris akunnya belum pernah dibuat sama sekali (ketahuan 2026-08-27 lewat
     * pengujian manual, gejala sama seperti kasus stale login_id di
     * `CustomersBackfillPortalLoginId`). Command backfill TETAP ada — perannya
     * sekarang murni data lama/lupa-lengkapi (`--resync` dsb), bukan lagi
     * satu-satunya jalur pembuatan akun.
     *
     * No-op diam-diam (bukan exception) kalau `portal_login_id` belum bisa
     * dihitung (POP belum punya `cid_prefix`) — sama seperti perilaku skip
     * command backfill, dan caller (workflow transition) tidak boleh gagal
     * gara-gara ini.
     */
    public function ensureAccountExists(Customer $customer): void
    {
        $loginId = $customer->portal_login_id;

        if (! $loginId || $customer->portalAccount) {
            return;
        }

        try {
            CustomerPortalAccount::create([
                'customer_id' => $customer->id,
                'login_id' => $loginId,
                // Placeholder acak, sama seperti command backfill — akun
                // pending_claim belum bisa dipakai login sampai diklaim,
                // tapi baris ber-hash lemah/predictable tetap risiko.
                'password_hash' => Hash::make(Str::random(40)),
                'status' => 'pending_claim',
            ]);
        } catch (QueryException) {
            // Unique constraint customer_id/login_id — dua pemanggil nyaris
            // bersamaan (mis. staf klik "Terbitkan" pas transisi workflow
            // otomatis lagi jalan) sudah dimenangkan salah satu, baris sudah
            // ada. Idempoten by design, bukan error.
        }
    }

    /**
     * "Lupa Password" (business-logic.md §"Aktivasi akun": "yang diterbitkan
     * helpdesk adalah PIN klaim baru, bukan password pilihan admin") — SATU-
     * SATUNYA jalan pulih buat akun `active` yang pelanggannya lupa password.
     * `claim()` menolak keras akun `active` (`already_claimed`), jadi tanpa
     * method ini gak ada jalan balik sama sekali begitu pelanggan lupa
     * password — ketahuan 2026-08-27, `reissuePin()` biasa TIDAK menyentuh
     * `customer_portal_accounts` sama sekali.
     *
     * Turunin status balik ke `pending_claim` supaya `/auth/claim` bisa
     * dipakai ULANG dengan PIN BARU (caller WAJIB terbitkan PIN baru
     * bebarengan — lihat `CustomerQrController::resetPortalAccount()`).
     * Password lama langsung tidak berlaku (ditimpa placeholder acak, sama
     * pola `ensureAccountExists()`) dan SEMUA token portal pelanggan itu
     * dicabut — sesi lama tidak boleh tetap hidup pakai password yang baru
     * saja dianggap "mungkin bocor".
     *
     * SENGAJA endpoint/aksi TERPISAH dari `reissuePin()` biasa, bukan
     * otomatis nempel di situ — `reissuePin()` juga dipakai buat rotasi PIN
     * billing-gate pelanggan yang TETAP mau tetap `active` (§6.5.2, rotasi
     * independen dari status klaim). Kalau digabung, staf yang reset PIN
     * biasa buat alasan LAIN bisa gak sengaja nge-lockout password portal
     * pelanggan yang gak ada masalah. Gerbangnya di sisi klien: konfirmasi
     * eksplisit terpisah, pola sama `reset-pin-confirm`.
     *
     * No-op kalau akun belum ada atau statusnya BUKAN `active` (belum ada
     * password buat "dilupakan") — caller (controller) yang menolak lebih
     * dulu dengan pesan jelas, method ini defensif kalau dipanggil di luar
     * jalur itu.
     */
    public function resetToPendingClaim(Customer $customer, ?User $actor = null): void
    {
        $account = $customer->portalAccount;

        if (! $account || $account->status !== 'active') {
            return;
        }

        DB::transaction(function () use ($account, $actor) {
            $account->forceFill([
                'status' => 'pending_claim',
                'password_hash' => Hash::make(Str::random(40)),
                'password_changed_at' => now(),
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();

            CustomerPortalToken::revokeAllForCustomer($account->customer_id);

            // Audit MANUAL (sama alasannya seperti PortalMeController::
            // updatePassword() — trait RecordsAuditLogs sengaja tidak
            // dipasang di CustomerPortalAccount, password/PIN TIDAK PERNAH
            // masuk log).
            AuditLog::create([
                'user_id' => $actor?->id,
                'module' => 'Portal Pelanggan',
                'action' => 'account_reset_to_pending_claim',
                'auditable_type' => CustomerPortalAccount::class,
                'auditable_id' => $account->id,
                'old_values' => null,
                'new_values' => null,
                'ip_address' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 255),
            ]);
        });
    }

    /**
     * Resolusi QR pelanggan → `login_id` + status akun, dipakai Portal
     * (app terpisah) buat pre-fill halaman klaim begitu pelanggan scan QR
     * (2026-08-27, keputusan: scan QR SELALU ke Portal, gerbang tagihan
     * internal `QrBillingController` dicabut). SENGAJA gak minta PIN di
     * sini — PIN tetap diverifikasi di `claim()`, method ini cuma bilang
     * "QR ini punya siapa" (login_id BUKAN rahasia, sudah tercetak di kartu
     * fisik yang sama).
     *
     * 4 kegagalan resolve token (§5, sama seperti `QrScanController`) SEMUA
     * dijawab `not_found` identik — jangan bocorkan detail mana yang salah.
     *
     * @return array{outcome: 'success'|'not_found', login_id?: string, account_status?: string}
     */
    public function resolveQr(string $code): array
    {
        [$token, $signature] = array_pad(explode('.', $code, 2), 2, '');
        $resolution = $this->qrTokens->resolve($token, $signature);

        if ($resolution['status'] !== 'success') {
            return ['outcome' => 'not_found'];
        }

        $customer = $resolution['qrToken']->customer;
        $loginId = $customer?->portal_login_id;

        if (! $customer || ! $loginId) {
            return ['outcome' => 'not_found'];
        }

        return [
            'outcome' => 'success',
            'login_id' => $loginId,
            // 'pending_claim' (belum) / 'active' (sudah) / null (akun belum
            // pernah dibuat sama sekali — kasus jarang, biasanya
            // ensureAccountExists() udah jalan bareng penerbitan QR).
            'account_status' => $customer->portalAccount?->status,
        ];
    }

    /**
     * @return array{outcome: 'success'|'invalid_credentials'|'locked', tokens?: array}
     */
    public function login(string $loginId, string $password, ?string $ip): array
    {
        $account = CustomerPortalAccount::where('login_id', $loginId)->first();

        // Pelanggan TANPA akun portal dijawab identik dengan password salah
        // — pesan "akun belum diaktifkan" membocorkan bahwa login_id itu
        // valid, dan seluruh guna throttle hilang (flowchart.md §1).
        if (! $account) {
            return ['outcome' => 'invalid_credentials'];
        }

        // Cek locked_until SEBELUM verifikasi password — akun yang lagi
        // dikunci tidak perlu menghitung ulang Hash::check (juga menghindari
        // reset failed_attempts yang salah kalau urutannya kebalik).
        if ($account->isLocked()) {
            return ['outcome' => 'locked'];
        }

        if (! Hash::check($password, $account->password_hash)) {
            $account->registerFailedAttempt();

            return ['outcome' => 'invalid_credentials'];
        }

        $account->resetFailedAttempts();
        $account->forceFill(['last_login_at' => now()])->save();

        return ['outcome' => 'success', 'tokens' => $this->issueTokenPair($account->customer_id, $ip)];
    }

    /**
     * Klaim akun (§6.6.5, diaktifkan 2026-08-26 — QR/PIN Fase 2 sudah
     * jalan). PIN adalah kunci klaim SEKALI PAKAI (§6.5.1), bukan
     * kredensial portal seterusnya — begitu klaim sukses, password yang
     * pelanggan pilih sendiri yang berlaku, PIN tetap hidup terpisah buat
     * gerbang halaman tagihan publik (rotasi independen, §6.5.2).
     *
     * `lockForUpdate()` pada baris akun — dua klaim nyaris bersamaan buat
     * login_id yang sama (dua tab, atau percobaan ganda) tidak boleh
     * dua-duanya lolos status `pending_claim` sebelum salah satu commit
     * (pola sama seperti `CustomerQrTokenService::issue()`).
     *
     * @return array{outcome: 'success'|'invalid'|'already_claimed'|'locked', tokens?: array}
     */
    public function claim(string $loginId, string $pin, string $newPassword, ?string $ip): array
    {
        return DB::transaction(function () use ($loginId, $pin, $newPassword, $ip) {
            $account = CustomerPortalAccount::where('login_id', $loginId)->lockForUpdate()->first();

            // Login ID tidak ketemu DIJAWAB SAMA dengan kasus lain yang
            // gagal — jangan bocorkan login_id mana yang valid (pola sama
            // login(), flowchart.md §1).
            if (! $account) {
                return ['outcome' => 'invalid'];
            }

            if ($account->status === 'active') {
                return ['outcome' => 'already_claimed'];
            }

            if ($account->status !== 'pending_claim') {
                // 'disabled' (pelanggan terminated) atau status lain yang
                // belum dikenal — jangan bedakan dari 'invalid' biasa.
                return ['outcome' => 'invalid'];
            }

            if ($account->isLocked()) {
                return ['outcome' => 'locked'];
            }

            $qrToken = $account->customer?->activeQrToken;

            // Pelanggan belum pernah punya token QR aktif (belum sampai
            // WAITING_INSTALLATION, atau token dicabut) — tidak ada PIN
            // yang bisa diverifikasi sama sekali.
            if (! $qrToken) {
                return ['outcome' => 'invalid'];
            }

            // Jalur verifikasi PIN SAMA PERSIS §6.5.4 — lockout 5x/15menit
            // per token QR ikut berlaku di sini, tidak ada jalur bypass
            // kedua (dokumen eksplisit mensyaratkan ini).
            $pinResult = $this->qrTokens->verifyPin($qrToken, $pin);

            if ($pinResult['outcome'] !== 'success') {
                return ['outcome' => $pinResult['outcome'] === 'locked' ? 'locked' : 'invalid'];
            }

            $account->forceFill([
                'password_hash' => Hash::make($newPassword),
                'password_changed_at' => now(),
                'status' => 'active',
                'claimed_at' => now(),
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();

            return ['outcome' => 'success', 'tokens' => $this->issueTokenPair($account->customer_id, $ip)];
        });
    }

    /**
     * @return array{outcome: 'success'|'invalid_session', tokens?: array}
     */
    public function refresh(string $refreshToken, ?string $ip): array
    {
        $token = CustomerPortalToken::resolveRefreshToken($refreshToken);

        if (! $token) {
            return ['outcome' => 'invalid_session'];
        }

        // Refresh yang SUDAH dipakai (revoked_at terisi) dipakai lagi =
        // indikasi pencurian. Cabut SEMUA token pelanggan itu (bukan cuma
        // turunan token ini) — flowchart.md eksplisit "cabut seluruh rantai,
        // PAKSA LOGIN ULANG", jadi revoke total lebih sesuai semangat
        // dokumen daripada cuma revokeDescendants().
        if ($token->revoked_at !== null) {
            CustomerPortalToken::revokeAllForCustomer($token->customer_id);

            return ['outcome' => 'invalid_session'];
        }

        if ($token->expires_at->isPast()) {
            return ['outcome' => 'invalid_session'];
        }

        $token->forceFill(['revoked_at' => now()])->save();

        return [
            'outcome' => 'success',
            'tokens' => $this->issueTokenPair($token->customer_id, $ip, parentRefreshId: $token->id),
        ];
    }

    /**
     * Cabut SEMUA token pelanggan itu — bukan cuma sesi pemanggil. Access
     * token tidak punya rantai ke refresh pasangannya tanpa client kirim
     * refresh_token tambahan, jadi logout satu-sesi presisi butuh mekanisme
     * lain; keputusan user 2026-08-24: samakan perilaku dengan logoutAll
     * daripada membangun mekanisme itu sekarang. Method terpisah dari
     * logoutAll() cuma supaya nama method di controller tetap jelas
     * mencerminkan endpoint mana yang manggil — isinya sengaja identik.
     */
    public function logout(int $customerId): void
    {
        CustomerPortalToken::revokeAllForCustomer($customerId);
    }

    public function logoutAll(int $customerId): void
    {
        CustomerPortalToken::revokeAllForCustomer($customerId);
    }

    /**
     * `$parentRefreshId` HANYA dipakai untuk mem-parent-kan REFRESH token
     * baru ke refresh token lama (rantai rotasi) — access token tidak pernah
     * punya parent, ia bukan bagian dari rantai rotasi refresh sama sekali,
     * masing-masing terbitan berdiri sendiri (15 menit lalu kedaluwarsa).
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function issueTokenPair(int $customerId, ?string $ip, ?int $parentRefreshId = null): array
    {
        $access = CustomerPortalToken::issue($customerId, 'access', null, self::ACCESS_TTL_MINUTES, $ip);
        $refresh = CustomerPortalToken::issue($customerId, 'refresh', $parentRefreshId, self::REFRESH_TTL_MINUTES, $ip);

        return [
            'access_token' => $access['plaintext'],
            'refresh_token' => $refresh['plaintext'],
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL_MINUTES * 60,
        ];
    }
}
