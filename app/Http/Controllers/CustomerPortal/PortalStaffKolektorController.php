<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\CollectorRole;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\StaffPortalToken;
use App\Models\User;
use App\Services\CollectorWorklistService;
use App\Services\CustomerBalanceService;
use App\Traits\RecordsCollectorBatch;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /customer-portal/kolektor/worklist/{code}`,
 * `POST /customer-portal/kolektor/payments` (docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md §3) — kolektor yang masuk lewat scan
 * QR di dalam app Operasional, di-redirect ke Portal bawa `StaffPortalToken`
 * purpose `kolektor`. `worklist()` TIDAK mengonsumsi token (baca berkali-kali
 * dalam TTL); `payments()` REUSE PENUH `RecordsCollectorBatch` — trait yang
 * sama dipakai `CollectorPaymentController` (jalur dashboard). Endpoint ini
 * TIDAK menduplikasi business logic pencatatan pembayaran sama sekali,
 * cuma menerjemahkan identitas token → pemanggilan trait yang sudah ada.
 */
#[Group('Portal Pelanggan — Staf', 'Endpoint yang dipanggil Portal atas nama STAF/kolektor (bukan pelanggan), setelah scan QR di dalam app Operasional. Butuh header `X-Portal-Client` + `Authorization: Bearer <staff_portal_token>` (one-shot, TTL 15 menit, purpose-scoped).')]
class PortalStaffKolektorController extends Controller
{
    use RecordsCollectorBatch;

    public function __construct(
        private readonly CollectorWorklistService $worklist,
    ) {}

    /**
     * Worklist tersaring 1 pelanggan
     *
     * Pola sama `QrScanController::dispatch()` cabang kolektor
     * (`?search=customer_code` di dashboard), cuma di sini beneran di-scope
     * lewat query, bukan parameter pencarian teks bebas. TIDAK mengonsumsi
     * token — boleh dipanggil berkali-kali dalam TTL sebelum submit bayar.
     */
    #[ScrambleResponse(200, description: 'Daftar invoice due milik pelanggan (bisa kosong kalau semua sudah lunas).', examples: [[
        'data' => [
            'customer' => ['customer_code' => 'RQ000123', 'full_name' => 'Budi Santoso'],
            'invoices' => [[
                'id' => 45, 'invoice_number' => 'INV-2026-000123', 'billing_period' => '2026-08',
                'due_date' => '2026-08-15', 'remaining_amount' => '150000.00',
            ]],
        ],
    ]])]
    #[ScrambleResponse(401, description: 'Token staf tidak ada/tidak valid/kedaluwarsa, atau bukan purpose kolektor.')]
    #[ScrambleResponse(403, description: 'Pelanggan bukan tanggung jawab kolektor ini (di luar worklist — collector_id tidak cocok).')]
    #[ScrambleResponse(404, description: 'code di URL tidak cocok dengan pelanggan yang tertaut ke token (dicegah "pinjam" token pelanggan lain).')]
    public function worklist(Request $request, string $code): JsonResponse
    {
        /** @var StaffPortalToken $token */
        $token = $request->attributes->get('staff_portal_token');

        /** @var User $collector */
        $collector = User::findOrFail($token->user_id);
        $customer = $this->resolveStaffPortalCustomer($token, $code);

        $invoices = $this->worklist->dueInvoices($collector, $collector)
            ->whereHas('customer', fn ($q) => $q->where('customers.id', $customer->id))
            ->get();

        // Query kosong TIDAK OTOMATIS berarti "di luar worklist" — bisa juga
        // pelanggan itu memang worklist-nya sendiri tapi semua invoice-nya
        // kebetulan sudah lunas. Kepemilikan (collector_id) yang jadi gerbang
        // 403, bukan ada/tidaknya baris outstanding.
        if ((int) $customer->collector_id !== $collector->id) {
            abort(403, 'Pelanggan ini bukan tanggung jawab Anda.');
        }

        return response()->json([
            'data' => [
                'customer' => [
                    'customer_code' => $customer->customer_code,
                    'full_name' => $customer->full_name,
                    // Saldo ikut dikirim supaya form bisa memakainya (pakai saldo
                    // seperti Bayar admin). String desimal, sama konvensi response lain.
                    'balance' => (string) app(CustomerBalanceService::class)->balance($customer),
                ],
                // Rekening tujuan Transfer — master, bukan input bebas (ADHOC-95).
                'bank_accounts' => BankAccount::activeOptions(),
                'invoices' => $invoices->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'billing_period' => $invoice->billing_period,
                    'due_date' => $invoice->due_date,
                    // String desimal, BUKAN float — pola sama seluruh
                    // response Portal lain (`InvoiceListItem.total_amount`
                    // dst, docs/api/api-portal-pelanggan/business-logic.md)
                    // biar Next.js gak perlu parse float yang rawan presisi.
                    'remaining_amount' => (string) $invoice->remaining_amount,
                ])->all(),
            ],
        ]);
    }

    /**
     * Catat pembayaran
     *
     * Bentuk request & response IDENTIK `POST /collector-worklist/pay`
     * (dashboard, `CollectorPaymentController::store()` — trait
     * `RecordsCollectorBatch` yang sama persis dipakai APA ADANYA), cuma
     * `$collector` diambil dari token, bukan `auth()->user()`. Token
     * dikonsumsi HANYA kalau batch beneran diproses sukses (bukan replay
     * idempotency key, bukan kegagalan 422).
     */
    #[ScrambleResponse(200, description: 'Batch pembayaran berhasil dicatat (atau idempotency key sudah pernah diproses).', examples: [[
        'success' => true, 'message' => '1 pembayaran berhasil dicatat untuk kolektor Budi.', 'processed' => 1,
    ]])]
    #[ScrambleResponse(401, description: 'Token staf tidak ada/tidak valid/kedaluwarsa, atau bukan purpose kolektor.')]
    #[ScrambleResponse(403, description: 'User pemilik token bukan role kolektor (permission kolektor.qr.pay ke-assign ke role lain lewat Role Matrix).')]
    #[ScrambleResponse(422, description: 'Baris tidak valid — invoice di luar scope/worklist, sudah lunas, atau nominal melebihi sisa.', examples: [[
        'success' => false, 'message' => 'Batch ditolak — ada baris tidak valid. Tidak ada payment yang tersimpan.',
        'failures' => [['invoice_id' => 45, 'reason' => 'INV-2026-000123: pelanggan ini bukan milik kolektor Budi.']],
    ]])]
    public function payments(Request $request): JsonResponse
    {
        /** @var StaffPortalToken $token */
        $token = $request->attributes->get('staff_portal_token');

        /** @var User $collector */
        $collector = User::findOrFail($token->user_id);

        abort_unless($collector->hasRole('kolektor'), 403, 'Hanya kolektor yang bisa mencatat pembayaran dari worklist.');

        $this->normalizeBatchAmounts($request);

        $validated = $request->validate($this->batchValidationRules());

        // Kunci token, cek pelanggan token, konsumsi, dan notifikasi setelah commit:
        // lihat RecordsCollectorBatch::recordStaffPortalBatch() (sama dengan jalur teknisi).
        return $this->recordStaffPortalBatch($token, $collector, $validated, CollectorRole::KOLEKTOR);
    }
}
