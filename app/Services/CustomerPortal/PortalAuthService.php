<?php

namespace App\Services\CustomerPortal;

use App\Models\CustomerPortalAccount;
use App\Models\CustomerPortalToken;
use Illuminate\Support\Facades\Hash;

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
