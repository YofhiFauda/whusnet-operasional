<?php

namespace App\Http\Controllers;

use App\Enums\CollectorRole;
use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\CollectorDeposit;
use App\Models\Invoice;
use App\Services\CollectorBalanceService;
use App\Services\CollectorWorklistService;
use App\Services\CustomerBalanceService;
use App\Support\LikeSearch;
use App\Traits\RecordsCollectorBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Jalur TEKNISI mencatat pembayaran pelanggan di lapangan.
 *
 * Beda dengan kolektor: pelanggan TIDAK harus di-assign admin lebih dulu.
 * Batasnya cuma POP scope teknisi (`applyUserScope`), dicek server di
 * `CollectorPaymentService::validateRows()` â€” bukan hanya di UI.
 *
 * Seperti `CollectorPaymentController`, identitas pencatat diambil dari
 * `auth()->user()`, tak pernah dari URL/body. Uang yang tercatat masuk saldo
 * teknisi (`CollectorBalanceService`) dan disetor lewat jalur setoran kolektor
 * yang sama, dengan badge sumber "Teknisi" (`payments.collected_by_role`).
 *
 * docs/plan/kolektor/rancangan-pembayaran-teknisi.md Â§4â€“Â§6.
 */
class TechnicianPaymentController extends Controller
{
    use RecordsCollectorBatch;

    /**
     * Halaman input pembayaran teknisi. Saldo & status setor ditampilkan di
     * atas form supaya teknisi tahu uang yang masih dipegangnya (peringatan
     * "belum setor" â€” hanya informasi, tidak memblokir).
     */
    public function index(Request $request, CollectorBalanceService $balance): View
    {
        $technician = $request->user();

        abort_unless($technician->isTechnician(), 403, 'Halaman ini khusus teknisi.');

        $search = LikeSearch::sanitize((string) $request->query('search', ''));
        $invoices = Invoice::query()
            ->applyUserScope($technician)
            ->whereIn('invoice_status', CollectorWorklistService::OUTSTANDING_STATUSES)
            ->where('billing_period', '<=', now()->format('Y-m'))
            ->when(mb_strlen($search) < 3, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(mb_strlen($search) >= 3, fn ($query) => $query->where(function ($match) use ($search) {
                $match->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('cid', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%"));
            }))
            ->with('customer')
            ->orderBy('customer_id')
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(150)
            ->withQueryString();
        $customerBalances = app(CustomerBalanceService::class)->balancesForCustomers(
            collect($invoices->items())->pluck('customer_id')->all()
        );

        $status = $balance->technicianSettlementStatus($technician)->firstWhere(fn (array $row) => $row['user']->id === $technician->id);
        $tab = in_array($request->query('tab'), ['bayar', 'setoran'], true)
            ? $request->query('tab')
            : 'catat';
        $unsettledCount = $balance->unsettledPaymentsQuery($technician)->count();
        $unsettledPayments = $tab === 'bayar'
            ? $balance->unsettledPaymentsQuery($technician)
                ->with(['customer:id,full_name,cid,customer_code', 'invoice:id,billing_period'])
                ->orderByDesc('id')
                ->paginate(50, ['*'], 'unsettled_page')
                ->withQueryString()
            : null;

        $deposits = $tab === 'setoran'
            ? CollectorDeposit::query()
                ->where('collector_id', $technician->id)
                ->with(['payments' => fn ($query) => $query
                    ->where('payment_status', PaymentStatus::VALID->value)
                    ->with('customer:id,full_name,cid,customer_code')])
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->paginate(20, ['*'], 'deposit_page')
                ->withQueryString()
            : null;

        return view('technician-payments.index', [
            'search' => $search,
            'invoices' => $invoices,
            'customerBalances' => $customerBalances,
            'deposits' => $deposits,
            'tab' => $tab,
            'unsettledCount' => $unsettledCount,
            'unsettledPayments' => $unsettledPayments,
            'outstandingShortfall' => $balance->outstandingShortfall($technician),
            'pendingDeposits' => CollectorDeposit::query()
                ->where('collector_id', $technician->id)
                ->whereIn('status', [DepositStatus::MENUNGGU_VERIFIKASI->value, DepositStatus::SELISIH->value])
                ->orderByDesc('submitted_at')
                ->limit(5)
                ->get(),
            'bankAccounts' => BankAccount::activeOptions(),
            'saldo' => $balance->balance($technician),
            'belumSetor' => $status['overdue'] ?? false,
            'closeTime' => (string) config('billing.technician_close_time', '23:59'),
            'depositUrl' => route('collector-worklist.deposit'),
        ]);
    }

    /**
     * Pencarian pelanggan dengan tagihan belum lunas dalam POP scope teknisi.
     * Hanya menampilkan tagihan periode berjalan ke bawah (sama dengan aturan
     * worklist kolektor: tagihan yang periodenya belum dimulai tidak ditagih).
     */
    public function search(Request $request): JsonResponse
    {
        $technician = $request->user();

        abort_unless($technician->isTechnician(), 403, 'Hanya teknisi yang bisa mencari pelanggan untuk pencatatan pembayaran.');

        $validated = $request->validate([
            'q' => 'required|string|min:3|max:100',
        ]);

        // `%` dan `_` di input dibuang, bukan di-escape: tanpa itu keduanya jadi
        // wildcard dan hampir semua pelanggan POP ikut cocok. Escape LIKE tidak
        // portabel (SQLite tidak punya ESCAPE default), jadi dibuang saja.
        $search = str_replace(['%', '_'], '', $validated['q']);

        if (mb_strlen($search) < 3) {
            return response()->json(['customers' => []]);
        }

        $currentPeriod = now()->format('Y-m');

        $invoices = Invoice::query()
            ->applyUserScope($technician)
            ->whereIn('invoice_status', CollectorWorklistService::OUTSTANDING_STATUSES)
            ->where('billing_period', '<=', $currentPeriod)
            ->whereHas('customer', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('cid', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
            })
            ->with('customer:id,cid,full_name')
            ->orderBy('customer_id')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $customers = $invoices
            ->groupBy('customer_id')
            ->map(fn ($rows) => [
                'customer_id' => $rows->first()->customer_id,
                'cid' => $rows->first()->customer?->cid,
                'full_name' => $rows->first()->customer?->full_name,
                'invoices' => $rows->map(fn (Invoice $invoice) => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'billing_period' => $invoice->billing_period,
                    'remaining_amount' => (float) $invoice->remaining_amount,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $technician = $request->user();

        abort_unless($technician->isTechnician(), 403, 'Hanya teknisi yang bisa mencatat pembayaran pelanggan dari lapangan.');

        $this->normalizeBatchAmounts($request);

        $validated = $request->validate($this->batchValidationRules());

        return $this->recordBatch($technician, $technician, $validated, CollectorRole::TEKNISI);
    }
}
