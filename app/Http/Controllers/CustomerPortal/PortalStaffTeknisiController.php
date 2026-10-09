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
