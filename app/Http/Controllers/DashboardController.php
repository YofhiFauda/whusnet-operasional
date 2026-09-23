<?php

namespace App\Http\Controllers;

use App\Enums\CashDepositStatus;
use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketBucket;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\Customer;
use App\Models\CustomerStatusLog;
use App\Models\InventoryBalance;
use App\Models\Invoice;
use App\Models\PackageCategory;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Task;
use App\Models\Ticket;
use App\Services\EffectiveAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Display the operational dashboard.
     */
    public function index(Request $request, EffectiveAccessService $access)
    {
        if (auth()->user()->hasRole('fop')) {
            return redirect()->route('fop.dashboard');
        }

        if (! auth()->user()->hasPermission('dashboard.view')) {
            if (auth()->user()->hasPermission('task.view.own')) {
                return redirect()->route('tasks.own');
            }
            if (auth()->user()->hasPermission('customers.view')) {
                return redirect()->route('customers.index');
            }
            // Kolektor bisa punya HANYA kolektor.view (worklist read-only,
            // §B-8 no. 5 — sengaja tanpa dashboard.view/customers.view sama
            // sekali). Tanpa fallback ini, login sukses tapi redirect default
            // ke '/' (dashboard) langsung abort 403 — kelihatan kayak "gagal
            // login" padahal auth-nya sah, cuma landing page-nya salah.
            if (auth()->user()->hasPermission('kolektor.view')) {
                return redirect()->route('collector-worklist.index');
            }
            abort(403, 'Unauthorized action.');
        }

        $popId = $request->query('pop_id', '');
        $periodFrom = $this->normalizePeriod($request->query('period_from')) ?? now()->format('Y-m');
        $periodTo = $this->normalizePeriod($request->query('period_to')) ?? $periodFrom;

        if ($periodFrom > $periodTo) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $periodStartDate = Carbon::createFromFormat('Y-m', $periodFrom)->startOfMonth();
        $periodEndDate = Carbon::createFromFormat('Y-m', $periodTo)->endOfMonth();

        $customerQuery = $this->scopedCustomerQuery($popId);
        $invoiceQuery = $this->scopedInvoiceQuery($popId);
        $paymentQuery = $this->scopedPaymentQuery($popId);

        $periodInvoiceQuery = (clone $invoiceQuery)
            ->whereBetween('billing_period', [$periodFrom, $periodTo]);

        $periodPaymentQuery = (clone $paymentQuery)
            ->whereBetween('payment_date', [$periodStartDate->toDateString(), $periodEndDate->toDateString()]);

        // Cache 60 detik per user+filter — HANYA angka stat cards (nilai
        // skalar, aman diserialize cache store apa pun). Koleksi model
        // (customersByPop/dueInvoices/incompleteCustomers/pops) SENGAJA tidak
        // ikut di-cache: round-trip Eloquent Collection lewat cache store
        // (file/redis) di environment ini korup jadi __PHP_Incomplete_Class
        // (kena reproduksi juga di luar app, langsung lewat Cache::put pada
        // collect([1,2,3]) polos — bukan spesifik ke query dashboard ini).
        // Jangan cache object/Collection sampai bug itu ditelusuri terpisah.
        $cacheKey = sprintf(
            'dashboard:main:stats:%d:%s:%s:%s',
            auth()->id(),
            $popId === null ? '' : $popId,
            $periodFrom,
            $periodTo
        );

        // cash_deposit.view = pandangan PEMERIKSA lintas-admin (lihat gerbang
        // yang sama di routes/web.php pada grup Setoran Kas Admin). Blok Posisi
        // Kas & Arus Kas cuma bermakna buat role yang memang berwenang melihat
        // kas lintas admin (Owner/Atasan) — bukan sekadar dashboard.view.
        $canViewCash = auth()->user()->hasPermission('cash_deposit.view');

        // tickets.history.view = superset lintas-bucket ("isinya lintas-bucket
        // dan bisa diekspor", lihat komentar route-nya) — dipakai sebagai
        // gerbang blok Ticketing & SLA di dashboard biar konsisten sama
        // filosofi "satu ringkasan, bukan worksheet operasional ke-4".
        $canViewTickets = auth()->user()->hasPermission('tickets.history.view');

        // warehouse.view = gerbang halaman Dashboard Gudang & Management Stock
        // (routes/web.php grup Gudang/Inventory) — sama gerbangnya dipakai di
        // sini biar konsisten, jangan bikin permission baru khusus ringkasan.
        $canViewWarehouse = auth()->user()->hasPermission('warehouse.view');
        $warehousePopIds = $canViewWarehouse ? $this->warehousePopIds($access, $popId) : collect();

        // task.view.all = audiens dashboard operasional `/fop` (lihat komentar
        // di config/rbac.php) — dipakai sebagai gerbang blok Efisiensi Delivery
        // Lapangan biar konsisten sama siapa yang boleh lihat data Task FOP.
        $canViewFopDelivery = auth()->user()->hasPermission('task.view.all');

        $stats = Cache::remember(
            $cacheKey,
            60,
            function () use (
                $customerQuery,
                $periodInvoiceQuery,
                $periodPaymentQuery,
                $invoiceQuery,
                $periodStartDate,
                $periodEndDate,
                $popId,
                $canViewCash,
                $canViewTickets,
                $canViewWarehouse,
                $warehousePopIds,
                $canViewFopDelivery
            ) {
                $totalInvoiceAmount = (float) (clone $periodInvoiceQuery)->sum('total_amount');
                $totalPaymentAmount = (float) (clone $periodPaymentQuery)
                    ->where('payment_status', PaymentStatus::VALID->value)
                    ->sum('amount');

                return [
                    'total_customers' => (clone $customerQuery)->count(),
                    'active_customers' => (clone $customerQuery)->where('status', 'active')->count(),
                    'incomplete_customers' => (clone $customerQuery)
                        ->whereIn('data_completeness_status', ['draft', 'perlu_dilengkapi'])
                        ->count(),
                    'ready_billing_customers' => (clone $customerQuery)
                        ->where('data_completeness_status', 'siap_billing')
                        ->count(),
                    'total_invoices_amount' => $totalInvoiceAmount,
                    'total_payments_amount' => $totalPaymentAmount,
                    // "Tunggakan" = piutang = sisa tagihan periode SEBELUM bulan
                    // berjalan (Invoice::scopePiutang). Sengaja TIDAK ikut filter
                    // periode dashboard: piutang adalah saldo posisi saat ini,
                    // bukan arus periode. Tagihan bulan ini yang belum dibayar
                    // belum tunggakan.
                    'total_unpaid_amount' => (float) (clone $invoiceQuery)
                        ->piutang()
                        ->sum('remaining_amount'),
                    // Piutang = tagihan periode lalu yang belum lunas, BUKAN
                    // `due_date <= hari ini` (tanggal 10 cuma label UI; batas riil
                    // akhir bulan). Lihat Invoice::scopePiutang().
                    'due_invoices_count' => (clone $invoiceQuery)->piutang()->count(),

                    // Collection Rate = efektivitas penagihan periode berjalan
                    // (docs/plan/analisa-dashboard-owner-statistik.md Pilar 1).
                    // Guard pembagi nol: belum ada tagihan terbit periode ini ≠
                    // penagihan 0%, jadi ditampilkan null (view merender "-").
                    'collection_rate' => $totalInvoiceAmount > 0
                        ? round(($totalPaymentAmount / $totalInvoiceAmount) * 100, 1)
                        : null,

                    ...$this->omsetStats($popId),
                    ...$this->growthStats($popId, $periodStartDate, $periodEndDate),
                    ...($canViewCash ? $this->cashPositionStats($popId) : []),
                    ...$this->funnelAndRetentionStats($popId, $periodStartDate, $periodEndDate),
                    ...($canViewTickets ? $this->ticketingStats($popId) : []),
                    ...($canViewWarehouse ? [
                        'low_stock_count' => $warehousePopIds->isNotEmpty()
                            ? InventoryBalance::query()->whereIn('pop_id', $warehousePopIds)->lowStock()->count()
                            : 0,
                    ] : []),
                    ...($canViewFopDelivery ? $this->fopDeliveryStats($popId, $periodStartDate, $periodEndDate) : []),
                ];
            }
        );

        $customersByPop = (clone $customerQuery)
            ->with('pop')
            ->selectRaw('pop_id, count(*) as total')
            ->groupBy('pop_id')
            ->orderByDesc('total')
            ->get();

        $dueInvoices = (clone $invoiceQuery)
            ->with(['customer', 'pop'])
            ->piutang()
            ->orderBy('billing_period')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $incompleteCustomers = (clone $customerQuery)
            ->with('pop')
            ->whereIn('data_completeness_status', ['draft', 'perlu_dilengkapi'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Action Center (Pilar 6) — daftar RINGKAS setoran yang butuh keputusan
        // Owner saat itu juga. Bukan stat angka, jadi TIDAK ikut Cache::remember
        // di atas (sama alasan customersByPop/dueInvoices/incompleteCustomers:
        // Eloquent Collection lewat cache store korup di environment ini).
        $pendingCashDeposits = $canViewCash
            ? CashDeposit::query()
                ->applyUserScope()
                ->realDeposits()
                ->with(['depositor', 'pop'])
                ->when($popId !== null && $popId !== '', fn ($q) => $q->where('pop_id', $popId))
                ->where('status', CashDepositStatus::MENUNGGU_VERIFIKASI->value)
                ->orderBy('submitted_at')
                ->limit(5)
                ->get()
            : collect();

        // Top 5 Kategori Gangguan (Pilar 4) — Collection hasil groupBy, sama
        // alasan gak di-cache seperti koleksi lain di atas. Difilter periode
        // (created_at tiket) karena ini insight tren, bukan snapshot kondisi
        // sekarang seperti distribusi bucket/breach SLA di ticketingStats().
        $topIssueCategories = $canViewTickets
            ? Ticket::query()
                ->applyUserScope()
                ->when($popId !== null && $popId !== '', fn ($q) => $q->where('pop_id', $popId))
                ->whereBetween('created_at', [$periodStartDate, $periodEndDate])
                ->whereNotNull('issue_category_id')
                ->with('issueCategory:id,name')
                ->selectRaw('issue_category_id, count(*) as total')
                ->groupBy('issue_category_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
            : collect();

        // Alert Stok Kritis POP (Pilar 6) — Collection, TIDAK ikut cache (sama
        // alasan koleksi lain di atas). Limit 10 baris item paling kritis
        // (rasio qty/minimum_stock terkecil), bukan sekadar "10 pertama".
        $lowStockItems = $canViewWarehouse && $warehousePopIds->isNotEmpty()
            ? InventoryBalance::query()
                ->whereIn('pop_id', $warehousePopIds)
                ->lowStock()
                ->with(['item', 'pop'])
                ->orderByRaw('qty / NULLIF(minimum_stock, 0)')
                ->limit(10)
                ->get()
            : collect();

        $pops = Pop::forUser()->orderBy('name')->get();

        $filters = [
            'pop_id' => $popId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'period_label' => $periodFrom === $periodTo ? $periodFrom : "{$periodFrom} s/d {$periodTo}",
        ];

        return view('dashboard', compact(
            'stats',
            'customersByPop',
            'dueInvoices',
            'incompleteCustomers',
            'pendingCashDeposits',
            'canViewCash',
            'topIssueCategories',
            'canViewTickets',
            'lowStockItems',
            'canViewWarehouse',
            'canViewFopDelivery',
            'pops',
            'filters'
        ));
    }

    /**
     * Omset per Segmen (Sales, Teknisi, Bisnis).
     *
     * @return array{
     *     omset_sales_amount: float,
     *     omset_sales_count: int,
     *     omset_teknisi_amount: float,
     *     omset_teknisi_count: int,
     *     omset_bisnis_amount: float,
     *     omset_bisnis_count: int
     * }
     */
    private function omsetStats(string|int|null $popId): array
    {
        $activeCustomers = (clone $this->scopedCustomerQuery($popId))
            ->where('status', 'active')
            ->with(['salesUser.role', 'customerService.internetPackage'])
            ->get();

        $salesCustomers = $activeCustomers->filter(function ($c) {
            $roleCode = strtolower($c->salesUser?->role?->code ?? '');

            return $roleCode === 'sales';
        });

        $teknisiCustomers = $activeCustomers->filter(function ($c) {
            $roleCode = strtolower($c->salesUser?->role?->code ?? '');

            return $roleCode === 'teknisi';
        });

        $bisnisCategoryNames = PackageCategory::query()
            ->whereNotNull('installation_fee_approval_role_id')
            ->orWhere('name', 'LIKE', '%bisnis%')
            ->orWhere('name', 'LIKE', '%business%')
            ->pluck('name')
            ->map(fn ($n) => strtolower((string) $n))
            ->toArray();

        $bisnisCustomers = $activeCustomers->filter(function ($c) use ($bisnisCategoryNames) {
            $catName = strtolower($c->customerService?->internetPackage?->category ?? '');
            if ($catName === '') {
                return false;
            }
            if (str_contains($catName, 'bisnis') || str_contains($catName, 'business')) {
                return true;
            }

            return in_array($catName, $bisnisCategoryNames, true);
        });

        return [
            'omset_sales_amount' => (float) $salesCustomers->sum(fn ($c) => $c->customerService?->total_monthly_bill ?? 0),
            'omset_sales_count' => $salesCustomers->count(),
            'omset_teknisi_amount' => (float) $teknisiCustomers->sum(fn ($c) => $c->customerService?->total_monthly_bill ?? 0),
            'omset_teknisi_count' => $teknisiCustomers->count(),
            'omset_bisnis_amount' => (float) $bisnisCustomers->sum(fn ($c) => $c->customerService?->total_monthly_bill ?? 0),
            'omset_bisnis_count' => $bisnisCustomers->count(),
        ];
    }

    /**
     * Net Customer Growth periode filter (Pilar 1).
     *
     * "Aktif baru" TIDAK dihitung dari `customers.created_at` (itu tanggal
     * input data, bisa jauh sebelum pelanggan benar-benar aktif) atau kolom
     * timestamp baru (customers TIDAK punya `activated_at` — sengaja tidak
     * ditambah, lihat alasan di bawah). Sumbernya `customer_status_logs`:
     * tabel riwayat transisi status yang SUDAH ditulis oleh
     * `CustomerWorkflowService::transition()` di setiap perubahan status,
     * termasuk saat masuk `active`. Menambah kolom `activated_at` baru di
     * `customers` akan jadi sumber kebenaran kedua untuk info yang sudah
     * tercatat lengkap di sini — dua sumber yang gampang menyimpang begitu
     * salah satu lupa disentuh (persis alasan aturan "satu penulis per sisi"
     * di Ticket↔FopTask, CLAUDE.md).
     *
     * `customer_status_logs` tidak punya kolom `pop_id` sendiri (lihat
     * migration-nya) — scope POP & filter POP dropdown diterapkan lewat
     * `whereHas('customer', ...)`.
     *
     * @return array{new_active_customers: int, terminated_customers: int, net_customer_growth: int}
     */
    private function growthStats(string|int|null $popId, Carbon $periodStartDate, Carbon $periodEndDate): array
    {
        $newActiveCustomers = CustomerStatusLog::query()
            ->where('to_status', 'active')
            ->whereBetween('created_at', [$periodStartDate, $periodEndDate])
            ->whereHas('customer', fn ($query) => $query
                ->applyUserScope()
                ->when($popId !== null && $popId !== '', fn ($q) => $q->where('pop_id', $popId)))
            ->count();

        $terminatedCustomers = (clone $this->scopedCustomerQuery($popId))
            ->whereBetween('terminated_at', [$periodStartDate, $periodEndDate])
            ->count();

        return [
            'new_active_customers' => $newActiveCustomers,
            'terminated_customers' => $terminatedCustomers,
            'net_customer_growth' => $newActiveCustomers - $terminatedCustomers,
        ];
    }

    /**
     * Posisi Kas & Arus Kas (Pilar 2) — HANYA dipanggil kalau pemanggil sudah
     * pegang `cash_deposit.view` (dicek di index(), bukan di sini, supaya
     * satu titik gerbang saja).
     *
     * Tiga posisi uang, tiga query terpisah, JANGAN digabung jadi satu sum:
     * - Kas Kasir POP     : pembayaran manual kantor (`collected_by` NULL,
     *                       lihat docblock `Payment::cashDeposit()`) yang
     *                       belum ikut sesi setoran kas admin manapun
     *                       (`cash_deposit_id` NULL).
     * - Uang di Kolektor  : pembayaran yang ditagih kolektor (`collected_by`
     *                       terisi) yang belum ikut sesi setoran kolektor
     *                       manapun (`collector_deposit_id` NULL) — definisi
     *                       saldo kolektor persis dari docblock
     *                       `Payment::collectorDeposit()`.
     * - Setoran Pending   : sesi `CashDeposit` yang sudah diserahkan admin
     *                       tapi belum diperiksa Owner.
     *
     * `scopeRealDeposits()` WAJIB dipakai di query CashDeposit — nyaring
     * baris sentinel `SALDO_AWAL` (titik nol pencatatan) supaya gak nongol
     * di angka Owner (lihat docblock `CashDepositStatus::SALDO_AWAL`).
     *
     * Ditambah risiko SATU TINGKAT DI BAWAH (kolektor → admin, bukan
     * admin → owner): `DepositStatus::SELISIH` (label "Kurang Setor") pada
     * `CollectorDeposit` — SENGAJA bukan status terminal (lihat docblock
     * enum-nya), jadi selama masih `SELISIH` itu piutang perusahaan ke
     * kolektor yang belum ketutup (`SELISIH_LUNAS`) atau belum diakui rugi
     * (`DIHAPUS_BUKU`). Nominalnya pakai `outstandingShortfall()` — method,
     * BUKAN kolom — karena sisa kewajiban berkurang tiap ada setoran susulan
     * (`settled_amount`), dihitung di PHP per baris (jumlah baris kecil,
     * gak layak diterjemahkan ke SQL).
     *
     * @return array{
     *     cash_at_office_amount: float,
     *     cash_with_collector_amount: float,
     *     cash_deposit_pending_count: int,
     *     cash_deposit_pending_amount: float,
     *     cash_deposit_open_difference_count: int,
     *     collector_shortfall_count: int,
     *     collector_shortfall_amount: float
     * }
     */
    private function cashPositionStats(string|int|null $popId): array
    {
        $paymentQuery = fn () => $this->scopedPaymentQuery($popId)
            ->where('payment_status', PaymentStatus::VALID->value);

        $cashAtOffice = (float) $paymentQuery()
            ->whereNull('collected_by')
            ->whereNull('cash_deposit_id')
            ->sum('amount');

        $cashWithCollector = (float) $paymentQuery()
            ->whereNotNull('collected_by')
            ->whereNull('collector_deposit_id')
            ->sum('amount');

        $cashDepositQuery = fn () => CashDeposit::query()
            ->applyUserScope()
            ->realDeposits()
            ->when($popId !== null && $popId !== '', fn ($q) => $q->where('pop_id', $popId));

        $pendingDeposits = $cashDepositQuery()
            ->where('status', CashDepositStatus::MENUNGGU_VERIFIKASI->value);

        $openDifferenceCount = $cashDepositQuery()
            ->whereIn('status', [CashDepositStatus::SELISIH_KURANG->value, CashDepositStatus::SELISIH_LEBIH->value])
            ->count();

        $collectorShortfalls = CollectorDeposit::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($q) => $q->where('pop_id', $popId))
            ->where('status', DepositStatus::SELISIH->value)
            ->get(['difference', 'settled_amount', 'status']);

        return [
            'cash_at_office_amount' => $cashAtOffice,
            'cash_with_collector_amount' => $cashWithCollector,
            'cash_deposit_pending_count' => (clone $pendingDeposits)->count(),
            'cash_deposit_pending_amount' => (float) (clone $pendingDeposits)->sum('declared_amount'),
            'cash_deposit_open_difference_count' => $openDifferenceCount,
            'collector_shortfall_count' => $collectorShortfalls->count(),
            'collector_shortfall_amount' => (float) $collectorShortfalls->sum(
                fn (CollectorDeposit $d) => $d->outstandingShortfall()
            ),
        ];
    }

    /**
     * Funnel Akuisisi & Retensi (Pilar 3) — SELALU dihitung (gak digerbang
     * permission tambahan), sama level trust-nya dengan stat pelanggan lain
     * yang sudah tampil di dashboard ini sejak awal (total/aktif/incomplete).
     *
     * Grouping status pakai nilai `WorkflowTransition` yang BENERAN ada di
     * enum (`registered`, `waiting_survey`, dst) — rancangan awal doc sempat
     * menyebut `calon_pelanggan` yang TIDAK ADA di enum, jadi tidak dipakai
     * di sini. `waiting_installation_group` sengaja menggabung status
     * "sedang proses pemasangan" (installation_in_progress/installed/
     * revision_installation) ke satu angka — detail per-status itu kerjaan
     * `/fop` (FopDashboardController), bukan Dashboard Owner (lihat §1
     * "Prinsip Utama" di docs/plan/analisa-dashboard-owner-statistik.md).
     *
     * `terminated_customers` (Churn) SENGAJA tidak dihitung ulang di sini —
     * sudah ada dari `growthStats()`, dipanggil bareng di array yang sama.
     * Menghitungnya dua kali cuma menambah query tanpa menambah kebenaran.
     *
     * @return array{
     *     funnel_waiting_survey: int,
     *     funnel_waiting_acc: int,
     *     funnel_waiting_installation: int,
     *     funnel_verification_admin: int,
     *     rejected_customers: int,
     *     suspended_customers: int
     * }
     */
    private function funnelAndRetentionStats(string|int|null $popId, Carbon $periodStartDate, Carbon $periodEndDate): array
    {
        $customerQuery = fn () => $this->scopedCustomerQuery($popId);

        return [
            'funnel_waiting_survey' => (clone $customerQuery())
                ->whereIn('status', ['registered', 'waiting_survey'])
                ->count(),
            'funnel_waiting_acc' => (clone $customerQuery())
                ->whereIn('status', ['survey_in_progress', 'surveyed', 'waiting_acc'])
                ->count(),
            'funnel_waiting_installation' => (clone $customerQuery())
                ->whereIn('status', ['waiting_installation', 'installation_in_progress', 'installed', 'revision_installation'])
                ->count(),
            'funnel_verification_admin' => (clone $customerQuery())
                ->where('status', 'verification_admin')
                ->count(),
            'rejected_customers' => (clone $customerQuery())
                ->whereBetween('rejected_at', [$periodStartDate, $periodEndDate])
                ->count(),
            'suspended_customers' => (clone $customerQuery())
                ->where('status', 'suspended')
                ->count(),
        ];
    }

    /**
     * Kualitas Layanan & Gangguan (Pilar 4) — HANYA dipanggil kalau pemanggil
     * pegang `tickets.history.view` (dicek di index()).
     *
     * Distribusi bucket & breach SLA sengaja TIDAK difilter periode (`now()`
     * sebagai acuan) — sama seperti "Tagihan Jatuh Tempo" yang sudah ada:
     * ini snapshot kondisi SEKARANG, bukan agregat historis periode filter.
     *
     * Breach SLA pakai definisi PERSIS `Ticket::isSlaBreached()` (resolved
     * telat ATAU masih jalan & sudah lewat deadline) tapi ditulis sebagai
     * query, bukan iterasi per-baris — memanggil method itu di loop atas
     * seluruh tiket scope bakal narik seluruh kolom tiket ke memori PHP
     * tanpa perlu.
     *
     * @return array{
     *     ticket_bucket_masuk: int,
     *     ticket_bucket_diproses: int,
     *     ticket_bucket_selesai: int,
     *     ticket_bucket_dibatalkan: int,
     *     ticket_sla_breach_count: int
     * }
     */
    private function ticketingStats(string|int|null $popId): array
    {
        $ticketQuery = fn () => Ticket::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($q) => $q->where('pop_id', $popId));

        // Dua cabang breach WAJIB di dalam SATU grup ->where(closure) — kalau
        // ->orWhere() dipanggil langsung di root builder, dia OR terhadap
        // SELURUH klausa sebelumnya (termasuk applyUserScope()/pop_id), bukan
        // cuma terhadap syarat "resolved telat"-nya saja. Itu jebakan klasik
        // orWhere setelah where majemuk — bisa bocor lintas POP scope.
        $breachCount = $ticketQuery()
            ->whereNotNull('sla_deadline_at')
            ->where(function ($q) {
                $q->where(function ($resolvedLate) {
                    $resolvedLate->whereNotNull('resolved_at')->whereColumn('resolved_at', '>', 'sla_deadline_at');
                })->orWhere(function ($stillOpenOverdue) {
                    $stillOpenOverdue->whereNull('resolved_at')->where('sla_deadline_at', '<', now());
                });
            })
            ->count();

        return [
            'ticket_bucket_masuk' => $ticketQuery()->inBucket(TicketBucket::MASUK)->count(),
            'ticket_bucket_diproses' => $ticketQuery()->inBucket(TicketBucket::DIPROSES)->count(),
            'ticket_bucket_selesai' => $ticketQuery()->inBucket(TicketBucket::SELESAI)->count(),
            'ticket_bucket_dibatalkan' => $ticketQuery()->inBucket(TicketBucket::DIBATALKAN)->count(),
            'ticket_sla_breach_count' => $breachCount,
        ];
    }

    /**
     * Efisiensi Delivery Lapangan (Pilar 5) — HANYA dipanggil kalau pemanggil
     * pegang `task.view.all` (dicek di index()).
     *
     * `Task` (bukan `Customer`) yang jadi sumber di sini: SURVEY & PEMASANGAN
     * (value `PSB`) auto-dibuat oleh `CustomerWorkflowService::transition()`
     * PERSIS di titik pelanggan masuk antrean (`waiting_survey`/
     * `waiting_installation`) — jadi `Task.created_at` SUDAH `=` "kapan masuk
     * antrean", gak perlu join balik ke `customer_status_logs` buat cari
     * titik itu lagi.
     *
     * Lead time PSB dihitung di PHP (`diffInMinutes` per baris), BUKAN
     * `AVG(julianday(...))`/`TIMESTAMPDIFF` mentah di query — driver DB projek
     * ini bisa sqlite (test) atau lain di produksi, dan dataset "PSB selesai
     * satu periode filter" kecil, jadi gak ada alasan performa buat pindah ke
     * SQL vendor-specific.
     *
     * @return array{
     *     fop_survey_completed_count: int,
     *     fop_psb_completed_count: int,
     *     fop_psb_avg_lead_time_days: float|null,
     *     fop_overdue_survey_count: int,
     *     fop_overdue_psb_count: int
     * }
     */
    private function fopDeliveryStats(string|int|null $popId, Carbon $periodStartDate, Carbon $periodEndDate): array
    {
        $taskQuery = fn () => Task::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($q) => $q->where('pop_id', $popId));

        // Status "masih di antrean" — belum selesai ATAU dibatalkan. Dipakai
        // dua kali (overdue survey & overdue PSB), sengaja ditulis eksplisit
        // (bukan whereNotIn selesai/dibatalkan) biar kalau `TaskStatus` nambah
        // case baru, developer WAJIB mutuskan sadar dia masuk antrean atau
        // enggak — bukan ke-include diam-diam lewat NOT IN.
        $queuedStatuses = [
            TaskStatus::DRAFT->value,
            TaskStatus::TERJADWAL->value,
            TaskStatus::IN_PROGRESS->value,
            TaskStatus::PENDING->value,
        ];

        $completedPsbTasks = $taskQuery()
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->where('status', TaskStatus::SELESAI->value)
            ->whereBetween('completed_at', [$periodStartDate, $periodEndDate])
            ->get(['created_at', 'completed_at']);

        $avgLeadTimeDays = $completedPsbTasks->isNotEmpty()
            ? round($completedPsbTasks->avg(
                fn (Task $t) => $t->created_at->diffInMinutes($t->completed_at)
            ) / 1440, 1)
            : null;

        return [
            'fop_survey_completed_count' => $taskQuery()
                ->where('task_type', TaskType::SURVEY->value)
                ->where('status', TaskStatus::SELESAI->value)
                ->whereBetween('completed_at', [$periodStartDate, $periodEndDate])
                ->count(),
            'fop_psb_completed_count' => $completedPsbTasks->count(),
            'fop_psb_avg_lead_time_days' => $avgLeadTimeDays,
            'fop_overdue_survey_count' => $taskQuery()
                ->where('task_type', TaskType::SURVEY->value)
                ->whereIn('status', $queuedStatuses)
                ->where('created_at', '<', now()->subDay())
                ->count(),
            'fop_overdue_psb_count' => $taskQuery()
                ->where('task_type', TaskType::PEMASANGAN->value)
                ->whereIn('status', $queuedStatuses)
                ->where('created_at', '<', now()->subDays(3))
                ->count(),
        ];
    }

    /**
     * POP gudang (`pusat`/`cabang`, TIDAK PERNAH `mini_pop` — lihat
     * `Pop::scopeWarehouse()`) yang boleh dibaca user, disilangkan dengan
     * filter dropdown POP dashboard. Pola query PERSIS `WarehouseController::index()`
     * — pakai `EffectiveAccessService` langsung (bukan `applyUserScope()`
     * generik / `$user->pops()`), karena `InventoryBalance` TIDAK punya trait
     * `HasPopScope` dan gudang butuh gabung scope user DENGAN `scopeWarehouse()`.
     *
     * @return Collection<int, int>
     */
    private function warehousePopIds(EffectiveAccessService $access, string|int|null $popId): Collection
    {
        $user = auth()->user();

        $allowedPopIds = Pop::query()
            ->warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->pluck('id');

        if ($popId !== null && $popId !== '') {
            return $allowedPopIds->contains((int) $popId) ? collect([(int) $popId]) : collect();
        }

        return $allowedPopIds;
    }

    private function scopedCustomerQuery(string|int|null $popId)
    {
        return Customer::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function scopedInvoiceQuery(string|int|null $popId)
    {
        return Invoice::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function scopedPaymentQuery(string|int|null $popId)
    {
        return Payment::query()
            ->applyUserScope()
            ->when($popId !== null && $popId !== '', fn ($query) => $query->where('pop_id', $popId));
    }

    private function normalizePeriod(mixed $period): ?string
    {
        if (! is_string($period) || ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return null;
        }

        return $period;
    }
}
