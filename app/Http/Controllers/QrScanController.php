<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Models\User;
use App\Services\CollectorWorklistService;
use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;
use App\Services\StaffPortalTokenService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dispatcher tunggal `GET /q1/{code}` (docs/plan/qr-code/
 * rancangan-qr-pelanggan-final.md §5) — SATU URL, routing ditentukan
 * server berdasarkan siapa yang memindai. Endpoint PUBLIK (throttle
 * `qr-public`, TANPA middleware auth) — pemindai tamu maupun staf lewat
 * jalur yang sama, dibedakan lewat auth()->check() di dalam method ini.
 *
 * Koreksi 2026-08-27 (keputusan eksplisit user): cabang tamu SEKARANG
 * redirect KE PORTAL (app terpisah), bukan lagi gerbang tagihan internal.
 * `QrBillingController` (Fungsi A lama) DICABUT total — Portal satu-satunya
 * tempat pelanggan lihat tagihan, lewat akun yang diklaim pakai Login
 * ID+PIN dari kartu yang sama. Cabang absen (Fungsi C, Fase 3) belum ada
 * halamannya — lihat komentar di cabang ticketing/fallback kenapa itu
 * SENGAJA dilewati dulu.
 *
 * Kolektor (2026-08-27) — cabang baru: pelanggan di worklist-nya sendiri →
 * catat pembayaran (`collector-worklist.index`, tersaring lewat pencarian
 * yang sudah ada); di luar worklist-nya → 403 tegas, TIDAK jatuh ke cabang
 * lain (lihat blok kodenya buat detail invarian).
 *
 * Staf ticketing & kolektor → Portal (2026-08-29, docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md) — dua cabang ini SEKARANG redirect
 * ke Portal juga (bukan lagi `qr.ticket.create`/`collector-worklist.index`
 * internal), pola sama seperti cabang tamu. Titik ini SUDAH membuktikan
 * `auth()->check()` sah (cookie sesi Operasional — bukan scan lewat app
 * kamera luar, staf WAJIB masuk lewat `/scan-qr` di dalam app, lihat
 * `QrInAppScanController`) dan `$customer` sudah resolve dari QR valid,
 * jadi di sinilah `StaffPortalTokenService` menerbitkan token one-shot yang
 * dibawa Portal buat manggil balik `POST /api/customer-portal/tickets` /
 * `.../kolektor/*`. Permission yang dicek `tickets.qr.create` /
 * `kolektor.qr.pay` — TERPISAH dari `tickets.create`/`kolektor.pay`
 * dashboard (§1.4 dokumen di atas), jadi jangan disamakan.
 *
 * Ambiguitas dua permission sekaligus (2026-08-29, ditemukan lewat uji coba
 * akun `owner`/full-access — punya `kolektor.qr.pay` DAN `tickets.qr.create`
 * berbarengan, beda dari staf lapangan biasa yang cuma pegang satu peran)
 * — SEBELUMNYA dipilih diam-diam lewat urutan `if` (kolektor duluan), staf
 * yang sebetulnya mau kirim tiket selalu kelempar ke form kolektor tanpa
 * penjelasan apa pun. Sekarang: kalau KEDUANYA eligible buat pelanggan yang
 * sama, dispatch() TIDAK memutuskan sendiri — redirect ke `qr.scan.choose`
 * (halaman internal, di dalam app ini, BUKAN Portal) yang menyajikan pilihan
 * eksplisit. Baru dari situ token diterbitkan sesuai pilihan staf lewat
 * `chooseConfirm()`, yang mengulang SEMUA pengecekan eligibility dari nol —
 * halaman pilihan cuma UI, bukan otorisasi (pola sama seperti Portal cuma
 * UI di §2/§3 dokumen rancangan).
 */
class QrScanController extends Controller
{
    public function __construct(
        private readonly CustomerQrTokenService $qrTokens,
        private readonly CollectorWorklistService $worklist,
        private readonly StaffPortalTokenService $staffTokens,
    ) {}

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
            // Tamu → Portal (2026-08-27, gantikan gerbang tagihan internal
            // yang dicabut). Portal manggil balik GET /api/customer-portal/
            // qr/resolve pakai $code yang sama buat dapetin login_id — jadi
            // token TETAP divalidasi ulang independen di sana, bukan asumsi
            // sudah divalidasi hop ini (pola sama seperti sebelumnya).
            $this->logScan($request, $qrToken->id, $customer->id, 'payment', 'success');

            return $this->redirectToPortal("/klaim?code={$code}");
        }

        $access = app(EffectiveAccessService::class);
        $user = auth()->user();

        if (! $access->hasAllPopAccess($user) && ! in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true)) {
            $this->logScan($request, $qrToken->id, $customer->id, 'ticketing', 'out_of_scope');

            abort(403, 'Anda tidak memiliki akses ke pelanggan di POP ini.');
        }

        // Fungsi C (absen teknisi, Fase 3) belum dibangun — cabang "punya
        // task terjadwal hari ini" di §5 SENGAJA dilewati, jatuh ke cabang
        // kolektor/ticketing/fallback di bawah.

        $eligibility = $this->resolveEligibility($user, $customer);

        if ($eligibility['kolektor'] && $eligibility['tickets']) {
            // Dua-duanya eligible — JANGAN pilih diam-diam, lihat docblock
            // kelas §"Ambiguitas dua permission sekaligus".
            return redirect()->route('qr.scan.choose', ['code' => $code]);
        }

        if ($eligibility['kolektor']) {
            return $this->finalizeKolektor($request, $qrToken, $customer, $user, $code);
        }

        if ($eligibility['tickets']) {
            return $this->finalizeTicketing($request, $qrToken, $customer, $user, $code);
        }

        // Kolektor (2026-08-27, keputusan eksplisit user) yang scan pelanggan
        // DI LUAR worklist-nya (bukan tanggung jawab dia) DITOLAK 403 tegas —
        // TIDAK fallback ke customers.show. Konsisten sama invarian
        // CollectorWorklistController: "tak bisa menyentuh pelanggan di luar
        // collector_id-nya sendiri". Cuma berlaku kalau staf itu BENERAN
        // tidak punya opsi lain (tickets.qr.create juga gak eligible) —
        // kalau eligible dua-duanya sudah ke-tangkap cabang chooser di atas.
        if ($eligibility['canKolektorRole']) {
            $this->logScan($request, $qrToken->id, $customer->id, 'payment', 'out_of_scope');

            abort(403, 'Pelanggan ini bukan tanggung jawab Anda.');
        }

        $this->logScan($request, $qrToken->id, $customer->id, 'ticketing', 'success');

        return redirect()->route('customers.show', $customer);
    }

    /**
     * Halaman pilihan (2026-08-29) — cuma dirender kalau staf BENERAN
     * eligible dua-duanya (dicek ULANG di sini, `dispatch()` cuma nebak
     * lewat redirect). Kalau ternyata cuma satu/nol yang eligible (mis. race
     * condition: worklist berubah di antara dua request, atau staf buka URL
     * `qr.scan.choose` manual tanpa lewat dispatch dulu), lempar balik ke
     * `qr.dispatch` yang MEMANG satu-satunya titik yang boleh memutuskan
     * jalur single-eligible/403/fallback — jangan duplikasi logic itu di sini.
     */
    public function choose(Request $request, string $code): View|RedirectResponse
    {
        [, $customer, $eligibility] = $this->resolveChooserContext($request, $code);

        if (! ($eligibility['kolektor'] && $eligibility['tickets'])) {
            return redirect()->route('qr.dispatch', ['code' => $code]);
        }

        return view('qr.scan-choose', [
            'code' => $code,
            'customer' => $customer,
        ]);
    }

    /**
     * Konfirmasi pilihan — MENGULANG SEMUA pengecekan eligibility dari nol
     * (permission, POP scope, worklist), bukan percaya begitu saja pilihan
     * yang dikirim staf. Halaman `choose()` cuma UI, bukan otorisasi.
     */
    public function chooseConfirm(Request $request, string $code): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['tickets', 'kolektor'])],
        ]);

        [$user, $customer, $eligibility] = $this->resolveChooserContext($request, $code);
        $qrToken = $this->resolveQrTokenOrFail($request, $code);

        if ($validated['action'] === 'kolektor') {
            abort_unless($eligibility['kolektor'], 403, 'Pelanggan ini bukan tanggung jawab Anda.');

            return $this->finalizeKolektor($request, $qrToken, $customer, $user, $code);
        }

        abort_unless($eligibility['tickets'], 403, 'Anda tidak punya izin membuat tiket lewat kanal ini.');

        return $this->finalizeTicketing($request, $qrToken, $customer, $user, $code);
    }

    /**
     * Koreksi 2026-08-29 (dua putaran).
     *
     * Putaran 1 (keliru, DIBALIKIN): sempat diubah jadi permission-only
     * (`hasPermission('kolektor.qr.pay')` doang, drop `hasRole('kolektor')`)
     * biar "konsisten sama tickets" & biar owner (`*`) eligible. SALAH —
     * `hasRole('kolektor')` di seluruh modul Kolektor 2.0 (`CollectorPayment
     * Controller`, `CollectorDepositController`, `CollectorVisitController`,
     * `PaymentBatchController`, `PortalStaffKolektorController::payments()`,
     * dst — BUKAN cuma satu tempat) itu BUKAN pengganti RBAC, itu lapis
     * BEDA: `collector_id`/`collected_by` adalah identitas yang dipakai
     * laporan keuangan kolektor (saldo, setoran, worklist, cross-check).
     * Kalau owner "jadi kolektor" cuma modal permission `*`, transaksinya
     * nempel ke pembukuan kolektor buat akun yang secara struktural BUKAN
     * penagih lapangan — merusak laporan itu. RBAC (`hasPermission()`)
     * jawab "boleh ngapain", `hasRole()` di sini jawab "identitas ini
     * struktural kolektor apa bukan" — dua pertanyaan beda, bukan
     * duplikasi. Permission-only DI SINI doang (tanpa ngubah 8+ titik
     * write lainnya) cuma bikin owner ke-route ke halaman kolektor terus
     * 403 pas submit — lebih buruk dari sebelumnya.
     *
     * Putaran 2 (final): balik ke `hasRole('kolektor') && hasPermission(...)`
     * — konsisten sama SELURUH sisa modul. Ticketing TETAP permission-only
     * (`tickets.qr.create` doang) karena tiket gak punya pembukuan per-role
     * yang terikat identitas — `created_by` ticket bukan kunci laporan
     * keuangan siapa pun, jadi gak butuh lapis identitas tambahan.
     *
     * @return array{kolektor: bool, tickets: bool, canKolektorRole: bool}
     */
    private function resolveEligibility(User $user, Customer $customer): array
    {
        $canKolektorRole = $user->hasRole('kolektor') && $user->hasPermission('kolektor.qr.pay');

        $kolektorEligible = $canKolektorRole && $this->worklist->dueInvoices($user, $user)
            ->whereHas('customer', fn ($q) => $q->where('customers.id', $customer->id))
            ->exists();

        return [
            'kolektor' => $kolektorEligible,
            'tickets' => $user->hasPermission('tickets.qr.create'),
            'canKolektorRole' => $canKolektorRole,
        ];
    }

    /**
     * Resolve ulang QR + customer + eligibility dari `$code` — dipakai
     * `choose()`/`chooseConfirm()`, yang cuma menerima `$code` di URL, BUKAN
     * state dari `dispatch()` (request terpisah, gak ada yang dibawa lewat
     * session). Auth & POP scope juga dicek ULANG di sini — route ini di
     * belakang middleware `auth` (defense-in-depth sama pola `qr.ticket.create`),
     * tapi POP scope tetap bisa berubah di antara scan & konfirmasi pilihan.
     *
     * @return array{0: User, 1: Customer, 2: array{kolektor: bool, tickets: bool, canKolektorRole: bool}}
     */
    private function resolveChooserContext(Request $request, string $code): array
    {
        $qrToken = $this->resolveQrTokenOrFail($request, $code);
        $customer = $qrToken->customer;

        /** @var User $user */
        $user = auth()->user();

        $access = app(EffectiveAccessService::class);
        if (! $access->hasAllPopAccess($user) && ! in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true)) {
            $this->logScan($request, $qrToken->id, $customer->id, 'ticketing', 'out_of_scope');

            abort(403, 'Anda tidak memiliki akses ke pelanggan di POP ini.');
        }

        return [$user, $customer, $this->resolveEligibility($user, $customer)];
    }

    private function resolveQrTokenOrFail(Request $request, string $code): CustomerQrToken
    {
        [$token, $signature] = array_pad(explode('.', $code, 2), 2, '');
        $resolution = $this->qrTokens->resolve($token, $signature);

        if ($resolution['status'] !== 'success') {
            $this->logScan($request, $resolution['qrToken']?->id, $resolution['qrToken']?->customer_id, 'unknown', $resolution['status']);

            abort(404);
        }

        return $resolution['qrToken'];
    }

    private function finalizeKolektor(Request $request, CustomerQrToken $qrToken, Customer $customer, User $user, string $code): RedirectResponse
    {
        $token = $this->staffTokens->issue($user, $customer, StaffPortalTokenService::PURPOSE_KOLEKTOR, $request->ip());

        $this->logScan($request, $qrToken->id, $customer->id, 'payment', 'success');

        return $this->redirectToPortal("/staff/kolektor?code={$code}&staff_token={$token['plaintext']}");
    }

    private function finalizeTicketing(Request $request, CustomerQrToken $qrToken, Customer $customer, User $user, string $code): RedirectResponse
    {
        $token = $this->staffTokens->issue($user, $customer, StaffPortalTokenService::PURPOSE_TICKETS, $request->ip());

        $this->logScan($request, $qrToken->id, $customer->id, 'ticketing', 'success');

        return $this->redirectToPortal("/staff/tickets?code={$code}&staff_token={$token['plaintext']}");
    }

    /**
     * Redirect ke Portal — SATU tempat yang menegakkan guard
     * `PORTAL_BASE_URL` (§ komentar di cabang tamu) buat SEMUA cabang yang
     * mengarah ke Portal (tamu, staf, kolektor). `$pathAndQuery` sudah
     * termasuk leading slash + query string, caller yang menyusunnya.
     */
    private function redirectToPortal(string $pathAndQuery): RedirectResponse
    {
        $portalBaseUrl = rtrim((string) config('qr.portal_base_url'), '/');

        if ($portalBaseUrl === '') {
            // Guard SENGAJA — tanpa ini, pemindai 404 diam-diam (redirect ke
            // URL kosong) kalau PORTAL_BASE_URL lupa diisi. 500 eksplisit
            // lebih gampang ketauan & ditambal daripada 404 yang keliatan
            // kayak QR-nya sendiri yang rusak.
            abort(500, 'PORTAL_BASE_URL belum dikonfigurasi — QR tidak bisa diarahkan ke Portal.');
        }

        return redirect()->away("{$portalBaseUrl}{$pathAndQuery}");
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
