<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Models\CollectorDeposit;
use App\Models\CollectorVisit;
use App\Services\CollectorBalanceService;
use App\Services\CollectorWorklistService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Worklist Kolektor — "siapa yang harus saya datangi hari ini, dan berapa".
 *
 * Sejak kolektor-2.0 halaman ini TIDAK LAGI read-only: kolektor mencatat
 * sendiri pembayaran yang dia terima, 1-by-1 maupun massal (§8 revisi atas
 * §B-8 no. 4 dokumen lama). Yang tetap: dia tak punya `payments.create`, tak
 * bisa membuka halaman admin, dan tak bisa menyentuh pelanggan di luar
 * `collector_id`-nya sendiri.
 *
 * Dua batasan query yang dua-duanya wajib, bukan salah satu:
 *   - `collector_id = auth()->id()` — pelanggan tanggung jawabnya;
 *   - `applyUserScope()` (di dalam service) — POP scope efektifnya SEKARANG.
 * Assign yang benar menjamin keduanya sejalan, tapi scope POP bisa dipersempit
 * belakangan (kolektor dipindah cabang) tanpa assign lamanya dibersihkan —
 * tanpa lapis kedua, pelanggan di luar cabang barunya tetap muncul.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9, §10, §14.2.
 */
class CollectorWorklistController extends Controller
{
    public function __construct(
        private readonly CollectorWorklistService $worklist,
        private readonly CollectorBalanceService $balance,
    ) {}

    public function index(Request $request): View
    {
        $collector = $request->user();

        $search = trim((string) $request->query('search', ''));

        $query = $this->worklist->dueInvoices($collector, $collector);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query
            ->paginate(150)
            ->withQueryString();

        // Diurutkan per pelanggan (di service) supaya tagihan pelanggan yang
        // sama berdempet: sekali datang, seluruh tunggakannya selesai — bukan
        // daftar invoice acak yang memaksa kolektor bolak-balik (§10 no. 2).
        $canPay = $collector->hasPermission('kolektor.pay') && $collector->hasRole('kolektor');
        $canDeposit = $collector->hasPermission('kolektor.deposit') && $collector->hasRole('kolektor');
        $dueWindowDays = $this->worklist->dueWindowDays();

        // Saldo = Σ pembayaran yang belum ikut setoran. Angka TURUNAN, bukan
        // kolom — lihat CollectorBalanceService.
        $balance = $this->balance->balance($collector);
        $unsettledCount = $this->balance->unsettledPaymentsQuery($collector)->count();
        $outstandingShortfall = $this->balance->outstandingShortfall($collector);

        // Pelanggan yang bisa dicatat kunjungannya = yang muncul di worklist
        // hari ini. Diambil dari halaman yang sama supaya kolektor tak bisa
        // mencatat kunjungan ke pelanggan yang bahkan tidak dia datangi.
        $visitCandidates = $invoices->getCollection()
            ->map(fn ($invoice) => $invoice->customer)
            ->filter()
            ->unique('id')
            ->sortBy('full_name')
            ->values();

        $canLogVisit = $collector->hasPermission('kolektor.visit') && $collector->hasRole('kolektor');

        $todayVisits = CollectorVisit::query()
            ->where('collector_id', $collector->id)
            ->whereDate('visited_at', now()->toDateString())
            ->with('customer:id,full_name')
            ->orderByDesc('id')
            ->get();

        $pendingDeposits = CollectorDeposit::query()
            ->where('collector_id', $collector->id)
            ->whereIn('status', [DepositStatus::MENUNGGU_VERIFIKASI->value, DepositStatus::SELISIH->value])
            ->orderByDesc('submitted_at')
            ->get();

        return view('collector-worklist.index', compact(
            'invoices', 'canPay', 'canDeposit', 'canLogVisit', 'dueWindowDays',
            'balance', 'unsettledCount', 'outstandingShortfall', 'pendingDeposits',
            'visitCandidates', 'todayVisits', 'search',
        ));
    }
}
