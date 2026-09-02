<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\StaffPortalToken;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Penerbit token pertukaran staf/kolektor → Portal
 * (docs/plan/qr-code/analisa-unifikasi-qr-staff-portal.md §4). Dipanggil
 * `QrScanController` PERSIS di titik yang sudah membuktikan sesi staf sah
 * DAN QR pelanggan sudah divalidasi — service ini tidak melakukan validasi
 * apa pun sendiri, murni menerbitkan & mengonsumsi token.
 */
class StaffPortalTokenService
{
    public const PURPOSE_TICKETS = 'tickets';

    public const PURPOSE_KOLEKTOR = 'kolektor';

    /**
     * TTL pendek — pola sama `PortalAuthService::ACCESS_TTL_MINUTES`. Cukup
     * buat staf pindah ke Portal & isi form, tidak cukup buat jadi bearer
     * token yang bertahan lama kalau nyasar/kebobol.
     */
    private const TTL_MINUTES = 15;

    /**
     * @return array{plaintext: string, expires_at: Carbon}
     */
    public function issue(User $staff, Customer $customer, string $purpose, ?string $ip): array
    {
        $result = StaffPortalToken::issue($staff->id, $customer->id, $purpose, self::TTL_MINUTES, $ip);

        return [
            'plaintext' => $result['plaintext'],
            'expires_at' => $result['model']->expires_at,
        ];
    }

    /**
     * Resolve token aktif (belum konsumsi, belum kedaluwarsa) buat `$purpose`
     * tertentu. Dipanggil middleware `PortalStaffToken` di setiap request API
     * yang datang dari Portal atas nama staf.
     */
    public function resolve(string $plaintext, string $purpose): ?StaffPortalToken
    {
        return StaffPortalToken::resolveActive($plaintext, $purpose);
    }
}
