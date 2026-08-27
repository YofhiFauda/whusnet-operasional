<?php

namespace App\Http\Controllers;

use App\Helpers\FormatHelper;
use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Services\CustomerQrTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Fungsi A — Pembayaran (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
 * §6.1, §10 Fase 2). Satu-satunya halaman TANPA auth di seluruh modul QR.
 *
 * Two-step disclosure: `show()` merender langkah 1 (info tersamar + gerbang
 * PIN/HP) kalau sesi belum lolos verifikasi, langkah 2 (rincian penuh) kalau
 * sudah. `verify()` yang memindahkan sesi dari langkah 1 ke langkah 2 —
 * TIDAK PERNAH menyimpan PIN itu sendiri ke session, cuma boolean/timestamp
 * "sudah lolos verifikasi sampai kapan", di-scope PER TOKEN.
 *
 * Diakses langsung (bookmark `/q1/{code}/tagihan`) TANPA lewat
 * QrScanController::dispatch() dulu — jadi validasi token/signature di sini
 * SENGAJA diulang mandiri (independen), bukan asumsi sudah divalidasi hop
 * sebelumnya (pola sama seperti QrTicketController — defense in depth).
 */
class QrBillingController extends Controller
{
    /** Sesi verifikasi berlaku 30 menit (§6.1). */
    private const SESSION_TTL_MINUTES = 30;

    public function __construct(private readonly CustomerQrTokenService $qrTokens) {}

    public function show(Request $request, string $code): View|RedirectResponse
    {
        $qrToken = $this->resolveOrFail($request, $code);
        $customer = $qrToken->customer;

        if ($this->isSessionVerified($qrToken)) {
            return view('qr.billing.detail', [
                'customer' => $customer,
                'invoices' => $customer->invoices()->latest('billing_period')->limit(12)->get(),
                'bankAccount' => config('qr.bank_account'),
                'waUrl' => $this->popWhatsAppUrl($customer),
            ]);
        }

        return view('qr.billing.gate', [
            'code' => $code,
            'maskedName' => FormatHelper::maskName($customer->full_name),
            'displayId' => $customer->display_id,
            'popName' => $customer->pop?->name,
            'gateType' => $qrToken->hasActivePin() ? 'pin' : 'phone',
            'error' => $request->session()->get('qr_billing_error'),
        ]);
    }

    public function verify(Request $request, string $code): RedirectResponse
    {
        $qrToken = $this->resolveOrFail($request, $code);

        $result = ['outcome' => 'invalid'];

        if ($qrToken->hasActivePin()) {
            $validated = $request->validate(['pin' => ['required', 'digits:6']]);
            $result = $this->qrTokens->verifyPin($qrToken, $validated['pin']);
        } else {
            $validated = $request->validate(['hp_last4' => ['required', 'digits:4']]);
            $result['outcome'] = $this->qrTokens->verifyLegacyPhoneSuffix($qrToken, $validated['hp_last4']) ? 'success' : 'invalid';
        }

        $outcome = $result['outcome'];

        $this->qrTokens->recordScan([
            'customer_qr_token_id' => $qrToken->id,
            'customer_id' => $qrToken->customer_id,
            'user_id' => null,
            'purpose' => 'payment',
            'result' => $outcome === 'success' ? 'success' : 'verify_failed',
            'reason' => $outcome !== 'success' ? $outcome : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        if ($outcome === 'success') {
            // Login pertama dengan PIN CETAK (§6.5.5b) — belum boleh lolos
            // ke tagihan sebelum PIN cetak diganti. PIN cetak dibawa
            // teknisi ke lokasi (§7.1), ada jendela waktu orang selain
            // pelanggan memegangnya secara fisik; wajib-ganti membuat
            // pengetahuan itu kedaluwarsa. Jalur 4-digit HP (tanpa PIN)
            // TIDAK kena aturan ini — gak ada "PIN cetak" yang perlu diganti.
            if ($qrToken->hasActivePin() && $qrToken->pin_must_change) {
                $request->session()->put($this->pendingChangeKey($qrToken), true);

                return redirect()->route('qr.billing.pin.change-form', ['code' => $code]);
            }

            $request->session()->put(
                $this->sessionKey($qrToken),
                now()->addMinutes(self::SESSION_TTL_MINUTES)->timestamp
            );

            return redirect()->route('qr.billing', ['code' => $code]);
        }

        $message = match ($outcome) {
            'expired' => 'PIN kedaluwarsa — hubungi helpdesk untuk terbitkan ulang.',
            'locked' => 'Terlalu banyak percobaan — coba lagi dalam '.($result['retry_after_minutes'] ?? self::SESSION_TTL_MINUTES).' menit.',
            default => 'PIN/kode salah.',
        };

        return redirect()->route('qr.billing', ['code' => $code])
            ->with('qr_billing_error', $message);
    }

    /**
     * Halaman wajib-ganti-PIN (§6.5.5b) — cuma bisa diakses SETELAH lolos
     * verifikasi PIN cetak (flag `pendingChangeKey`), tidak bisa dilompati
     * langsung dari gerbang. `hasActivePin() && pin_must_change` dicek
     * ULANG di sini (bukan cuma percaya flag session) — kalau ternyata
     * sudah diganti (mis. dua tab), lempar balik ke tagihan biasa.
     */
    public function changePinForm(Request $request, string $code): View|RedirectResponse
    {
        $qrToken = $this->resolveOrFail($request, $code);

        if (! $request->session()->get($this->pendingChangeKey($qrToken))) {
            return redirect()->route('qr.billing', ['code' => $code]);
        }

        if (! $qrToken->hasActivePin() || ! $qrToken->pin_must_change) {
            $request->session()->forget($this->pendingChangeKey($qrToken));

            return redirect()->route('qr.billing', ['code' => $code]);
        }

        return view('qr.billing.change-pin', [
            'code' => $code,
            'error' => $request->session()->get('qr_billing_error'),
        ]);
    }

    public function changePinSubmit(Request $request, string $code): RedirectResponse
    {
        $qrToken = $this->resolveOrFail($request, $code);

        if (! $request->session()->get($this->pendingChangeKey($qrToken))) {
            return redirect()->route('qr.billing', ['code' => $code]);
        }

        $validated = $request->validate([
            'new_pin' => ['required', 'digits:6'],
            'new_pin_confirmation' => ['required', 'same:new_pin'],
        ]);

        try {
            $this->qrTokens->changePin($qrToken, $validated['new_pin']);
        } catch (RuntimeException $e) {
            return redirect()->route('qr.billing.pin.change-form', ['code' => $code])
                ->with('qr_billing_error', $e->getMessage());
        }

        $request->session()->forget($this->pendingChangeKey($qrToken));
        $request->session()->put(
            $this->sessionKey($qrToken),
            now()->addMinutes(self::SESSION_TTL_MINUTES)->timestamp
        );

        return redirect()->route('qr.billing', ['code' => $code]);
    }

    /**
     * Resolusi mandiri — 4 kegagalan (§5) SEMUA 404 identik, sama seperti
     * QrScanController::dispatch(). Endpoint ini publik & dibookmark, jadi
     * TIDAK BOLEH asumsi sudah tervalidasi dispatcher.
     */
    private function resolveOrFail(Request $request, string $code): CustomerQrToken
    {
        [$token, $signature] = array_pad(explode('.', $code, 2), 2, '');
        $resolution = $this->qrTokens->resolve($token, $signature);

        if ($resolution['status'] !== 'success') {
            $this->qrTokens->recordScan([
                'customer_qr_token_id' => $resolution['qrToken']?->id,
                'customer_id' => $resolution['qrToken']?->customer_id,
                'user_id' => null,
                'purpose' => 'payment',
                'result' => $resolution['status'],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);

            abort(404);
        }

        return $resolution['qrToken'];
    }

    private function isSessionVerified(CustomerQrToken $qrToken): bool
    {
        $expiresAt = session($this->sessionKey($qrToken));

        return $expiresAt !== null && $expiresAt > now()->timestamp;
    }

    private function sessionKey(CustomerQrToken $qrToken): string
    {
        return "qr_billing_verified.{$qrToken->token}";
    }

    /**
     * Flag sementara: "sudah benar masukin PIN CETAK, tapi belum ganti".
     * Terpisah dari sessionKey() (akses penuh) — orang yang masih di flag
     * ini TIDAK BOLEH lihat tagihan sebelum PIN-nya diganti.
     */
    private function pendingChangeKey(CustomerQrToken $qrToken): string
    {
        return "qr_billing_pending_change.{$qrToken->token}";
    }

    /**
     * Nomor WhatsApp admin POP (`pops.pic_phone`) — pola normalisasi 62xxx
     * sama persis `invoices/show.blade.php`, disatukan di sini biar gak
     * dobel di halaman publik.
     */
    private function popWhatsAppUrl(Customer $customer): ?string
    {
        $phone = (string) ($customer->pop?->pic_phone ?? '');
        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        $text = rawurlencode("Halo, saya {$customer->full_name} ({$customer->display_id}) ingin menanyakan tagihan internet.");

        return "https://wa.me/{$phone}?text={$text}";
    }
}
