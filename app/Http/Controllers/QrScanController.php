<?php

namespace App\Http\Controllers;

use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dispatcher tunggal `GET /q1/{code}` (docs/plan/qr-code/
 * rancangan-qr-pelanggan-final.md §5) — SATU URL, routing ditentukan
 * server berdasarkan siapa yang memindai. Endpoint PUBLIK (throttle
 * `qr-public`, TANPA middleware auth) — pemindai tamu maupun staf lewat
 * jalur yang sama, dibedakan lewat auth()->check() di dalam method ini.
 *
 * Fase 2: cabang tamu (Fungsi A — tagihan) diarahkan ke QrBillingController.
 * Cabang absen (Fungsi C, Fase 3) belum ada halamannya — lihat komentar di
 * cabang ticketing/fallback kenapa itu SENGAJA dilewati dulu.
 */
class QrScanController extends Controller
{
    public function __construct(private readonly CustomerQrTokenService $qrTokens) {}

    public function dispatch(Request $request, string $code): RedirectResponse
    {
        [$token, $signature] = array_pad(explode('.', $code, 2), 2, '');

        $resolution = $this->qrTokens->resolve($token, $signature);
        $qrToken = $resolution['qrToken'];
        $status = $resolution['status'];

        if ($status !== 'success') {
            // Urutan §5: token_not_found / bad_signature / token_revoked /
            // pop_mismatch SEMUA mengembalikan 404 IDENTIK — detail cuma
            // masuk qr_scan_logs, supaya penyerang tidak bisa membedakan
            // "token gak ada" dari "token ada tapi signature-nya salah".
            $this->logScan($request, $qrToken?->id, $qrToken?->customer_id, 'unknown', $status);

            abort(404);
        }

        $customer = $qrToken->customer;

        if (! auth()->check()) {
            // Fungsi A — halaman tagihan publik (§6.1, Fase 2). QrBillingController
            // meresolusi ULANG $code secara mandiri (dispatcher ini tidak
            // meneruskan hasil resolve() lewat apa pun) — endpoint itu juga
            // bisa diakses langsung lewat bookmark, jadi tetap harus validasi
            // sendiri (defense in depth, pola sama QrTicketController).
            $this->logScan($request, $qrToken->id, $customer->id, 'payment', 'success');

            return redirect()->route('qr.billing', ['code' => $code]);
        }

        $access = app(EffectiveAccessService::class);
        $user = auth()->user();

        if (! $access->hasAllPopAccess($user) && ! in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true)) {
            $this->logScan($request, $qrToken->id, $customer->id, 'ticketing', 'out_of_scope');

            abort(403, 'Anda tidak memiliki akses ke pelanggan di POP ini.');
        }

        // Fungsi C (absen teknisi, Fase 3) belum dibangun — cabang "punya
        // task terjadwal hari ini" di §5 SENGAJA dilewati, jatuh ke cabang
        // ticketing/fallback di bawah.

        if ($user->hasPermission('tickets.create')) {
            $this->logScan($request, $qrToken->id, $customer->id, 'ticketing', 'success');

            return redirect()->route('qr.ticket.create', ['code' => $code]);
        }

        $this->logScan($request, $qrToken->id, $customer->id, 'ticketing', 'success');

        return redirect()->route('customers.show', $customer);
    }

    private function logScan(Request $request, ?int $qrTokenId, ?int $customerId, string $purpose, string $result): void
    {
        $this->qrTokens->recordScan([
            'customer_qr_token_id' => $qrTokenId,
            'customer_id' => $customerId,
            'user_id' => auth()->id(),
            'purpose' => $purpose,
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
