<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Services\CustomerPortal\PortalAuthService;
use App\Services\CustomerQrCodeRenderer;
use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;
use App\Support\IndonesianDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Halaman staf: lihat/terbitkan/cabut/cetak token QR + PIN pelanggan
 * (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §5, §10 Fase 1 & 2).
 *
 * BUKAN dispatcher scan (itu QrScanController, publik & tanpa permission
 * granular) — controller ini murni sisi manajemen token oleh staf ber-POP-
 * scope, digerbangi permission `customers.qr.*`.
 */
class CustomerQrController extends Controller
{
    public function __construct(
        private readonly CustomerQrTokenService $qrTokens,
        private readonly CustomerQrCodeRenderer $qrRenderer,
        private readonly PortalAuthService $portalAuth,
    ) {}

    public function show(Customer $customer): View
    {
        $this->authorizePopScope($customer);

        $activeToken = $customer->activeQrToken;
        $dispatchUrl = $activeToken ? $this->qrTokens->dispatchUrl($activeToken) : null;

        return view('customers.qr.show', [
            'customer' => $customer,
            'activeToken' => $activeToken,
            'dispatchUrl' => $dispatchUrl,
            'qrDataUri' => $dispatchUrl ? $this->qrRenderer->dataUri($dispatchUrl) : null,
            'revokedTokens' => $customer->qrTokens()->whereNotNull('revoked_at')->latest('revoked_at')->get(),
            'pinHistory' => $activeToken ? $this->pinHistory($activeToken) : [],
            'portalAccount' => $customer->portalAccount,
        ]);
    }

    /**
     * JSON ringkas status token QR — dipakai modal ringkas di tab
     * Pemasangan (`_installation.blade.php`), lazy-load via fetch pas modal
     * dibuka. BUKAN pengganti `show()` (halaman penuh: Reset PIN, riwayat,
     * Cabut Token tetap cuma di sana) — modal ini murni "ada QR apa
     * enggak, ini gambarnya, sini link cetak", nol PIN di payload sama
     * sekali (modal ringkas gak ada UI buat itu).
     */
    public function status(Customer $customer): JsonResponse
    {
        $this->authorizePopScope($customer);

        $activeToken = $customer->activeQrToken;
        $dispatchUrl = $activeToken ? $this->qrTokens->dispatchUrl($activeToken) : null;

        return response()->json([
            'has_token' => (bool) $activeToken,
            'qr_data_uri' => $dispatchUrl ? $this->qrRenderer->dataUri($dispatchUrl) : null,
            'dispatch_url' => $dispatchUrl,
            'issued_at' => $activeToken?->issued_at ? IndonesianDate::dateTime($activeToken->issued_at) : null,
            'scan_count' => $activeToken?->scan_count ?? 0,
            'customer' => [
                'full_name' => $customer->full_name,
                'customer_code' => $customer->customer_code,
                'pop_name' => $customer->pop?->name,
                'portal_login_id' => $customer->portal_login_id,
            ],
            'print_url' => route('customers.qr.print', $customer),
        ]);
    }

    public function print(Customer $customer): View
    {
        $this->authorizePopScope($customer);

        $activeToken = $customer->activeQrToken;

        abort_unless($activeToken, 404, 'Pelanggan ini belum punya token QR aktif — terbitkan dulu.');

        $dispatchUrl = $this->qrTokens->dispatchUrl($activeToken);

        return view('customers.qr.print', [
            'customer' => $customer,
            'activeToken' => $activeToken,
            'dispatchUrl' => $dispatchUrl,
            'qrDataUri' => $this->qrRenderer->dataUri($dispatchUrl),
            // Perintah eksplisit user 2026-08-26: kartu reprintable ini
            // WAJIB nunjukin PIN, bukan cuma modal sekali-cetak — pin_hash
            // sekarang reversible (lihat docblock issuePin()). null kalau
            // belum ada PIN atau baris masih format bcrypt lama.
            'plainPin' => $this->qrTokens->revealPin($activeToken),
        ]);
    }

    /**
     * Terbitkan QR + PIN — SATU aksi, bukan dua tombol terpisah (§6.5.3:
     * penerbitan PIN "dipicu saat pelanggan masuk WAITING_INSTALLATION,
     * BUKAN aksi admin lepas" — token dan PIN adalah satu paket, sama
     * seperti hook otomatis di CustomerWorkflowService). Ini jalur MANUAL
     * buat kasus token belum sempat terbit otomatis (mis. customer_code
     * belum lengkap saat transisi workflow).
     *
     * AMAN diklik berkali-kali — token idempoten (§7.2), dan PIN CUMA
     * diterbitkan kalau `pin_hash` masih kosong. Begitu PIN sudah pernah
     * ada, tombol ini TIDAK PERNAH menimpanya lagi — reset PIN wajib lewat
     * reissuePin(), tombol TERPISAH di UI (dialog pratinjau + konfirmasi
     * eksplisit di sisi klien — lihat show.blade.php), supaya klik gak
     * sengaja di sini tidak pernah mematikan PIN yang sudah dipegang
     * pelanggan (koreksi 2026-08-26: sebelumnya ini dua tombol terpisah,
     * salah paham dari rancangan dokumen; lalu direvisi lagi 2026-08-26
     * dari halaman/tab baru jadi modal in-page atas masukan user).
     *
     * Merender JSON (bukan halaman/redirect) kalau PIN BENAR-BENAR baru
     * diterbitkan — dipakai fetch() dari show.blade.php buat modal PIN.
     * Kalau token sudah ada & PIN sudah ada juga, tetap redirect PRG biasa
     * (gak ada apa pun baru buat ditampilkan).
     */
    public function issue(Customer $customer): JsonResponse|RedirectResponse
    {
        $this->authorizePopScope($customer);

        try {
            $token = $this->qrTokens->issue($customer, auth()->user());
        } catch (RuntimeException $e) {
            return redirect()->route('customers.qr.show', $customer)->withErrors(['error' => $e->getMessage()]);
        }

        // Kartu yang dicetak dari titik ini punya login_id + PIN — akun
        // `customer_portal_accounts` (pending_claim) wajib udah ada di sini
        // juga, sama seperti hook otomatis di CustomerWorkflowService (lihat
        // docblock PortalAuthService::ensureAccountExists()).
        $this->portalAuth->ensureAccountExists($customer);

        if (! $token->pin_hash) {
            $plainPin = $this->qrTokens->issuePin($token, auth()->user());

            return $this->pinRevealJson($customer, $token, $plainPin);
        }

        return redirect()->route('customers.qr.show', $customer)
            ->with('success', 'Token QR sudah aktif.');
    }

    /**
     * Reset PIN (§6.5.5 "Terbitkan Ulang PIN"). Koreksi 2026-08-26 (2x atas
     * masukan user): SEBELUMNYA minta `verification_note` wajib + dialog
     * server-rendered — dicabut. Gerbangnya sekarang murni sisi KLIEN:
     * modal pratinjau (lihat show.blade.php) yang wajib dikonfirmasi
     * sebelum request ini dikirim sama sekali — bukan berarti tanpa
     * pengaman, cuma pindah bentuk dari "isi alasan" ke "lihat dulu, baru
     * yakin". Jejak siapa/kapan tetap tercatat — `CustomerQrTokenService::
     * issuePin()` yang menulis `AuditLog` (PIN-nya sendiri tetap TIDAK
     * pernah masuk log, §6.5.3), ditampilkan balik sebagai "Riwayat PIN"
     * di show.blade.php.
     */
    public function reissuePin(Customer $customer): JsonResponse
    {
        $this->authorizePopScope($customer);

        $activeToken = $customer->activeQrToken;
        abort_unless($activeToken, 404, 'Pelanggan ini belum punya token QR aktif — terbitkan token dulu.');

        $plainPin = $this->qrTokens->issuePin($activeToken, auth()->user());

        return $this->pinRevealJson($customer, $activeToken, $plainPin);
    }

    /**
     * "Lupa Password" — jalur PEMULIHAN buat akun portal `active` yang
     * pelanggannya lupa password (`PortalAuthService::resetToPendingClaim()`
     * docblock: `reissuePin()` biasa TIDAK menyentuh status akun, jadi tanpa
     * ini akun `active` yang kelupaan password TERKUNCI PERMANEN — nemu
     * lubang ini 2026-08-27).
     *
     * SATU aksi gabungan: terbitin PIN BARU (buat klaim ulang) + turunin
     * status akun balik ke `pending_claim`. Gerbangnya sisi KLIEN — modal
     * konfirmasi TERPISAH dari "Reset PIN" biasa (lihat show.blade.php),
     * karena efeknya lebih berat: password pelanggan yang lagi dipakai
     * langsung mati & semua sesi portal-nya dicabut, bukan cuma PIN billing-
     * gate yang ganti.
     */
    public function resetPortalAccount(Customer $customer): JsonResponse
    {
        $this->authorizePopScope($customer);

        $account = $customer->portalAccount;
        abort_unless($account, 404, 'Pelanggan ini belum punya akun Portal.');
        abort_unless($account->status === 'active', 409, 'Akun ini belum pernah diklaim — belum ada password buat direset. Pakai "Terbitkan/Reset PIN" biasa.');

        $activeToken = $customer->activeQrToken;
        abort_unless($activeToken, 404, 'Pelanggan ini belum punya token QR aktif — terbitkan token dulu.');

        $plainPin = $this->qrTokens->issuePin($activeToken, auth()->user());
        $this->portalAuth->resetToPendingClaim($customer, auth()->user());

        return $this->pinRevealJson($customer, $activeToken, $plainPin);
    }

    /**
     * Payload JSON dikonsumsi Alpine (fetch) di show.blade.php buat modal
     * PIN — SENGAJA JSON, bukan halaman/redirect PRG biasa, biar modal +
     * "Status PIN" di halaman belakang update tanpa reload penuh. Tetap
     * `no-store` di bawah — PIN gak boleh nyangkut di cache/proxy walau
     * sekarang bisa dibuka ulang kapan pun lewat `revealPin()`/`/qr/cetak`
     * (koreksi 2026-08-26, `pin_hash` jadi reversible — lihat docblock
     * `CustomerQrTokenService::issuePin()`).
     */
    private function pinRevealJson(Customer $customer, CustomerQrToken $token, string $plainPin): JsonResponse
    {
        $dispatchUrl = $this->qrTokens->dispatchUrl($token);

        $payload = [
            'pin' => $plainPin,
            'customer' => [
                'full_name' => $customer->full_name,
                'customer_code' => $customer->customer_code,
                'pop_name' => $customer->pop?->name,
                'portal_login_id' => $customer->portal_login_id,
            ],
            'qr_data_uri' => $this->qrRenderer->dataUri($dispatchUrl),
            'pin_issued_at' => IndonesianDate::dateTime($token->pin_issued_at),
            'pin_must_change' => $token->pin_must_change,
            'history' => $this->pinHistory($token),
        ];

        // no-store + noindex — respons ini TIDAK BOLEH kena cache di mana
        // pun (proxy, browser), PIN-nya cuma hidup selama respons ini (§6.5.3).
        return response()->json($payload)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Riwayat PIN (diterbitkan/direset) — dibaca dari `AuditLog` yang
     * ditulis `CustomerQrTokenService::issuePin()`, BUKAN kolom baru di
     * `customer_qr_tokens` (tabel itu cuma nyimpan status PIN AKTIF saat
     * ini, bukan riwayatnya — audit trail generik yang sudah ada cukup).
     *
     * @return list<array{at: string, by: string, action: string}>
     */
    private function pinHistory(CustomerQrToken $token): array
    {
        return AuditLog::query()
            ->where('auditable_type', CustomerQrToken::class)
            ->where('auditable_id', $token->id)
            ->whereIn('action', ['pin_issued', 'pin_reissued'])
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'at' => IndonesianDate::dateTime($log->created_at),
                'by' => $log->user?->name ?? 'Sistem (otomatis)',
                'action' => $log->action === 'pin_issued' ? 'Diterbitkan' : 'Direset',
            ])
            ->values()
            ->all();
    }

    public function revoke(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizePopScope($customer);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $activeToken = $customer->activeQrToken;

        abort_unless($activeToken, 404, 'Pelanggan ini tidak punya token QR aktif.');

        $this->qrTokens->revoke($activeToken, $validated['reason'], auth()->user());

        return redirect()->route('customers.qr.show', $customer)
            ->with('success', 'Token QR dicabut.');
    }

    /**
     * Guard per-record, pola sama seperti
     * CustomerController::authorizeCustomerPopScope() — route model binding
     * langsung lewat ID, tidak lewat query yang sudah di-scope.
     */
    private function authorizePopScope(Customer $customer): void
    {
        $access = app(EffectiveAccessService::class);
        $user = auth()->user();

        if ($access->hasAllPopAccess($user)) {
            return;
        }

        abort_unless(
            in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true),
            403,
            'Anda tidak memiliki akses ke pelanggan di POP ini.'
        );
    }
}
