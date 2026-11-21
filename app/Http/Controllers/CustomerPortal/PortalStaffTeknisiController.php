<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\CollectorRole;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
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
 * `GET /customer-portal/teknisi/worklist/{code}`,
 * `POST /customer-portal/teknisi/payments` — teknisi yang scan QR pelanggan
 * di dalam app Operasional, di-redirect ke Portal bawa `StaffPortalToken`
 * purpose `teknisi_bayar` (ADHOC-122, rancangan-pembayaran-teknisi §5).
 *
 * Beda dengan `PortalStaffKolektorController`: TIDAK ada cek kepemilikan
 * `collector_id` (teknisi mencatat pelanggan mana pun di POP scope-nya).
 * Batas POP scope ditegakkan di `applyUserScope()` + `validateRows()`.
 *
 * Seluruh pengamanan jalur Portal (kunci token, izin & POP scope ulang, invoice
 * milik pelanggan token, periode berjalan, notifikasi setelah commit) ada di
 * `RecordsCollectorBatch::recordStaffPortalBatch()` dan dipakai bersama dengan
 * jalur kolektor.
 */
#[Group('Portal Pelanggan — Staf', 'Endpoint yang dipanggil Portal atas nama STAF/teknisi (bukan pelanggan), setelah scan QR di dalam app Operasional. Butuh header `X-Portal-Client` + `Authorization: Bearer <staff_portal_token>` (one-shot, TTL 15 menit, purpose `teknisi_bayar`).')]
class PortalStaffTeknisiController extends Controller
{
    use RecordsCollectorBatch;

    public function __construct(
        private readonly CollectorWorklistService $worklist,
    ) {}

    /**
     * Tagihan belum lunas pelanggan ini dalam POP scope teknisi. Tidak
     * mengonsumsi token (boleh dibaca berkali-kali dalam TTL).
     */
    #[ScrambleResponse(200, description: 'Tagihan belum lunas pelanggan ini (bisa kosong kalau semua sudah lunas).', examples: [[
        'data' => [
            'customer' => ['customer_code' => 'RQ000123', 'full_name' => 'Budi Santoso', 'balance' => '0.00'],
            'bank_accounts' => [['id' => 1, 'bank_name' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'Whusnet Operasional']],
            'invoices' => [[
                'id' => 45, 'invoice_number' => 'INV-2026-000123', 'billing_period' => '2026-08',
                'due_date' => '2026-08-15', 'remaining_amount' => '150000.00',
            ]],
        ],
    ]])]
    #[ScrambleResponse(401, description: 'Token staf tidak ada/tidak valid/kedaluwarsa, atau bukan purpose `teknisi_bayar`.')]
    #[ScrambleResponse(403, description: 'User pemilik token bukan teknisi, atau pelanggan di luar POP scope teknisi ini.')]
    #[ScrambleResponse(404, description: '`code` di URL tidak cocok dengan pelanggan yang tertaut ke token (dicegah "pinjam" token pelanggan lain).')]
    public function worklist(Request $request, string $code): JsonResponse
    {
        /** @var StaffPortalToken $token */
        $token = $request->attributes->get('staff_portal_token');

        /** @var User $technician */
        $technician = User::findOrFail($token->user_id);
        abort_unless($technician->isTechnician(), 403, 'Hanya teknisi yang bisa memakai jalur ini.');

        $customer = $this->resolveStaffPortalCustomer($token, $code);
        $this->assertStaffPortalAuthorized($technician, $customer);

        $invoices = Invoice::query()
            ->applyUserScope($technician)
            ->whereIn('invoice_status', CollectorWorklistService::OUTSTANDING_STATUSES)
            ->where('billing_period', '<=', now()->format('Y-m'))
            ->where('customer_id', $customer->id)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'customer' => [
                    'customer_code' => $customer->customer_code,
                    'full_name' => $customer->full_name,
                    'balance' => (string) app(CustomerBalanceService::class)->balance($customer),
                ],
                'bank_accounts' => BankAccount::activeOptions(),
                'invoices' => $invoices->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'billing_period' => $invoice->billing_period,
                    'due_date' => $invoice->due_date,
                    'remaining_amount' => (string) $invoice->remaining_amount,
                ])->all(),
            ],
        ]);
    }

    /**
     * Catat pembayaran. Token dikonsumsi HANYA kalau batch benar-benar
     * diproses (bukan replay idempotency key, bukan validasi gagal).
     */
    #[ScrambleResponse(200, description: 'Batch pembayaran berhasil dicatat (atau idempotency key sudah pernah diproses).', examples: [[
        'success' => true, 'message' => '1 pembayaran berhasil dicatat untuk teknisi Budi.', 'processed' => 1,
    ]])]
    #[ScrambleResponse(401, description: 'Token staf tidak ada/tidak valid/kedaluwarsa, atau bukan purpose `teknisi_bayar`.')]
    #[ScrambleResponse(403, description: 'User pemilik token bukan teknisi, atau sudah berperan kolektor (jalur ini khusus teknisi).')]
    #[ScrambleResponse(422, description: 'Baris tidak valid — invoice di luar scope, sudah lunas, atau nominal melebihi sisa.', examples: [[
        'success' => false, 'message' => 'Batch ditolak — ada baris tidak valid. Tidak ada payment yang tersimpan.',
        'failures' => [['invoice_id' => 45, 'reason' => 'INV-2026-000123: sudah lunas.']],
    ]])]
    public function payments(Request $request): JsonResponse
    {
        /** @var StaffPortalToken $token */
        $token = $request->attributes->get('staff_portal_token');

        /** @var User $technician */
        $technician = User::findOrFail($token->user_id);

        abort_unless($technician->isTechnician() && ! $technician->hasRole('kolektor'), 403, 'Hanya teknisi yang bisa mencatat pembayaran lewat jalur ini.');

        $this->normalizeBatchAmounts($request);

        $validated = $request->validate($this->batchValidationRules());

        return $this->recordStaffPortalBatch($token, $technician, $validated, CollectorRole::TEKNISI);
    }
}
