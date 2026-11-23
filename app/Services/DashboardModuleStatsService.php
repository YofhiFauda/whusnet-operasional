<?php

namespace App\Services;

use App\Enums\CableCrossingStatus;
use App\Enums\CReqVerificationStatus;
use App\Enums\IsolationCaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PengajuanBarangStatus;
use App\Enums\StockRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\VisitResult;
use App\Enums\WorkflowTransition;
use App\Models\CollectorVisit;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerBillingWaiver;
use App\Models\CustomerCableCrossing;
use App\Models\CustomerPackageChange;
use App\Models\CustomerService;
use App\Models\InfrastructureRecovery;
use App\Models\Payment;
use App\Models\PengajuanBarang;
use App\Models\StockRequest;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ringkasan per-modul Dashboard Owner Fase 2 (ADHOC-313,
 * docs/plan/analisa-dashboard-owner-fase-2.md). Dipisah dari
 * DashboardController supaya controller gak makin gemuk — controller cuma
 * memutuskan GERBANG (permission halaman modul), service ini cuma menghitung.
 *
 * Aturan main yang wajib dijaga:
 * - Tiap method mengembalikan array SKALAR (angka/string/array polos), bukan
 *   Eloquent Collection: hasilnya masuk Cache::remember dashboard, dan
 *   Collection Eloquent lewat cache store korup di environment ini (lihat
 *   catatan di DashboardController::index()).
 * - Tiap query wajib lewat POP scope user + filter dropdown POP dashboard.
 *   Kalau modulnya sudah punya query scoped sendiri (antrean isolir, review
 *   penarikan, antrean BD, rekonsiliasi modem, okupansi ODP), query ITU yang
 *   dipakai — jangan menulis ulang aturannya di sini, nanti angka dashboard
 *   dan isi halaman modul pelan-pelan beda.
 */
class DashboardModuleStatsService
{
    /**
     * Status Task "masih di antrean" — sama persis dengan
     * DashboardController::fopDeliveryStats(). Ditulis eksplisit (bukan NOT
     * IN selesai/dibatalkan) supaya TaskStatus baru wajib diputuskan sadar.
     */
    private const QUEUED_TASK_STATUSES = [
        TaskStatus::DRAFT,
        TaskStatus::TERJADWAL,
        TaskStatus::IN_PROGRESS,
        TaskStatus::PENDING,
        TaskStatus::LAPOR_NANTI,
    ];

    public function __construct(
        private readonly EffectiveAccessService $access,
        private readonly IsolationCaseService $isolationCases,
        private readonly DeviceRetrievalReviewService $retrievalReviews,
        private readonly BusinessDevelopmentVerificationQueue $bdQueue,
        private readonly ModemSaleReconciliationService $modemReconciliation,
        private readonly FopUnifiedDashboardService $fopUnified,
    ) {}

    // ── P1 ──────────────────────────────────────────────────────────────

    /**
     * Kasus isolir per tahap antrean. Grup status diambil dari
     * IsolationCaseStatus::*Group() — grup yang sama dipakai tab halaman
     * Antrean Isolir, jadi angka di sini = angka chip tab di sana.
     *
     * @return array{waiting: int, ready: int, suspended: int, restoration: int, failed: int}
     */
    public function isolation(User $user, string|int|null $popId): array
    {
        $counts = $this->isolationCases->casesQuery($user)
            ->when($this->hasPop($popId), fn (Builder $q) => $q->where('pop_id', $popId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sum = fn (array $statuses): int => (int) collect($statuses)
            ->sum(fn (IsolationCaseStatus $status) => (int) ($counts[$status->value] ?? 0));

        return [
            'waiting' => $sum(IsolationCaseStatus::waitingIsolationGroup()),
            'ready' => $sum(IsolationCaseStatus::readyForIsolationGroup()),
            'suspended' => $sum([IsolationCaseStatus::SUSPENDED]),
            'restoration' => $sum(IsolationCaseStatus::restorationGroup()),
            // Gagal isolir = pelanggan nunggak tetap jalan; gagal pulih =
            // pelanggan sudah bayar tapi masih terputus. Dua-duanya Action Center.
            'failed' => $sum([IsolationCaseStatus::ISOLATION_FAILED, IsolationCaseStatus::RESTORATION_FAILED]),
        ];
    }

    /**
     * Top 5 alasan putus langganan dalam periode (pelanggan `terminated_at`
     * di rentang periode dashboard).
     *
     * @return list<array{name: string, total: int}>
     */
    public function terminationReasons(string|int|null $popId, Carbon $start, Carbon $end): array
    {
        return $this->customerQuery($popId)
            ->whereBetween('terminated_at', [$start, $end])
            ->leftJoin('customer_termination_reasons', 'customer_termination_reasons.id', '=', 'customers.termination_reason_id')
            ->selectRaw("COALESCE(customer_termination_reasons.name, 'Tanpa Alasan') as reason_name, count(*) as total")
            ->groupBy('reason_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->reason_name, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * Laporan penarikan alat GAGAL yang menunggu keputusan FOP — query satu
     * sumber dengan halaman Review Penarikan & badge sidebar.
     */
    public function pendingRetrievalReviews(User $user, string|int|null $popId): int
    {
        return $this->retrievalReviews->pendingForUser($user)
            ->when($this->hasPop($popId), fn (Builder $q) => $q->whereHas('task', fn (Builder $t) => $t->where('pop_id', $popId)))
            ->count();
    }

    /**
     * Task DEAC (ambil alat) yang belum tuntas — alat masih di rumah
     * pelanggan yang sudah putus.
     */
    public function deacQueue(string|int|null $popId): int
    {
        return $this->taskQuery($popId)
            ->where('task_type', TaskType::AMBIL_MODEM->value)
            ->whereIn('status', $this->queuedStatusValues())
            ->count();
    }

    /** Pelanggan baru yang belum diverifikasi pendaftarannya. */
    public function pendingRegistrationVerifications(string|int|null $popId): int
    {
        return $this->customerQuery($popId)
            ->where('status', WorkflowTransition::REGISTERED->value)
            ->count();
    }

    /**
     * Antrean "Menunggu Verifikasi BD" — query satu sumber dengan halaman BD
     * (termasuk pembatasan kategori paket per role approver).
     */
    public function pendingBusinessVerifications(User $user, string|int|null $popId): int
    {
        return $this->bdQueue->forUser($user)
            ->when($this->hasPop($popId), fn (Builder $q) => $q->where('pop_id', $popId))
            ->count();
    }

    /**
     * Task C-REQ / MTN berbayar yang tagihannya belum diverifikasi. Dua
     * cabang OR wajib cek task_type DULU sebelum whereHas ke tabel detail
     * masing-masing (pola TaskCreqBillingController::index(), ADHOC-144).
     */
    public function pendingCreqBillingVerifications(string|int|null $popId): int
    {
        $pending = fn (Builder $detail) => $detail
            ->where('is_billable', true)
            ->where('verification_status', CReqVerificationStatus::PENDING->value);

        return $this->taskQuery($popId)
            ->where(function (Builder $q) use ($pending) {
                $q->where(fn (Builder $c) => $c->where('task_type', TaskType::CREQ->value)->whereHas('creqDetail', $pending))
                    ->orWhere(fn (Builder $m) => $m->where('task_type', TaskType::MAINTENANCE->value)->whereHas('maintenanceBillingDetail', $pending));
            })
            ->count();
    }

    /**
     * Periode yang dibebaskan dari tagihan (putus / cuti) dalam rentang
     * periode dashboard. Tabel waiver TIDAK menyimpan nominal, jadi yang
     * ditampilkan jumlah periode per sumber — bukan rupiah.
     *
     * @return array<string, int> source => jumlah periode
     */
    public function billingWaivers(string|int|null $popId, string $periodFrom, string $periodTo): array
    {
        return CustomerBillingWaiver::query()
            ->whereBetween('billing_period', [$periodFrom, $periodTo])
            ->whereHas('customer', fn (Builder $q) => $q->applyUserScope()
                ->when($this->hasPop($popId), fn (Builder $c) => $c->where('pop_id', $popId)))
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Modem terjual yang belum tertagih — memakai rows()/summary() halaman
     * Rekonsiliasi Jual Modem apa adanya (status anomali vs menunggu
     * ditentukan di service itu, bukan di sini).
     *
     * @return array{total: int, anomaly: int, waiting: int, unbilled_amount: float}
     */
    public function modemReconciliation(User $user, string|int|null $popId): array
    {
        $filters = $this->hasPop($popId) ? ['pop_id' => $popId] : [];

        return $this->modemReconciliation->summary($this->modemReconciliation->rows($user, $filters));
    }

    // ── P2 ──────────────────────────────────────────────────────────────

    /**
     * MRR, ARPU, churn %.
     *
     * MRR difilter dari `customers.status = active`, BUKAN
     * `customer_services.service_status` — kolom itu di kode dipakai dengan
     * nilai 'active' DAN 'aktif' sekaligus, jadi gak bisa diandalkan.
     *
     * Churn % = putus periode / pelanggan aktif di awal periode. Aktif awal
     * periode didekati dari (aktif sekarang + putus periode − aktif baru
     * periode) — tanpa snapshot historis, ini pendekatan paling jujur dari
     * data yang ada. Pembagi ≤ 0 ⇒ null (view merender "-").
     *
     * @return array{mrr: float, arpu: float|null, churn_rate: float|null}
     */
    public function revenueQuality(string|int|null $popId, int $activeCustomers, int $terminatedInPeriod, int $newActiveInPeriod): array
    {
        $mrr = (float) CustomerService::query()
            ->whereHas('customer', fn (Builder $q) => $q->applyUserScope()
                ->where('status', WorkflowTransition::ACTIVE->value)
                ->when($this->hasPop($popId), fn (Builder $c) => $c->where('pop_id', $popId)))
            ->sum('total_monthly_bill');

        $activeAtStart = $activeCustomers + $terminatedInPeriod - $newActiveInPeriod;

        return [
            'mrr' => $mrr,
            'arpu' => $activeCustomers > 0 ? round($mrr / $activeCustomers) : null,
            'churn_rate' => $activeAtStart > 0 ? round($terminatedInPeriod / $activeAtStart * 100, 2) : null,
        ];
    }

    /**
     * Upgrade / downgrade paket dalam rentang periode tagihan. Selisih =
     * perubahan tagihan bulanan (bukan prorata sekali bayar).
     *
     * @return array{upgrade_count: int, downgrade_count: int, monthly_delta: float}
     */
    public function packageChanges(string|int|null $popId, string $periodFrom, string $periodTo): array
    {
        $row = CustomerPackageChange::query()
            ->whereBetween('billing_period', [$periodFrom, $periodTo])
            ->whereHas('customer', fn (Builder $q) => $q->applyUserScope()
                ->when($this->hasPop($popId), fn (Builder $c) => $c->where('pop_id', $popId)))
            ->selectRaw('
                SUM(CASE WHEN new_monthly_price > old_monthly_price THEN 1 ELSE 0 END) as upgrade_count,
                SUM(CASE WHEN new_monthly_price < old_monthly_price THEN 1 ELSE 0 END) as downgrade_count,
                COALESCE(SUM(new_monthly_price - old_monthly_price), 0) as monthly_delta
            ')
            ->first();

        return [
            'upgrade_count' => (int) ($row->upgrade_count ?? 0),
            'downgrade_count' => (int) ($row->downgrade_count ?? 0),
            'monthly_delta' => (float) ($row->monthly_delta ?? 0),
        ];
    }

    /**
     * Efektivitas kunjungan kolektor dalam periode.
     *
     * "Janji bayar lewat" = kunjungan berhasil janji, tanggal janjinya sudah
     * lewat, dan pelanggan itu belum punya pembayaran valid sejak tanggal
     * kunjungan (dari jalur mana pun — kasir, transfer, kolektor lain).
     *
     * @return array{total: int, by_result: array<string, int>, conversion_rate: float|null, broken_promises: int}
     */
    public function collectorPerformance(string|int|null $popId, Carbon $start, Carbon $end): array
    {
        $visitQuery = fn () => CollectorVisit::query()
            ->applyUserScope()
            ->when($this->hasPop($popId), fn (Builder $q) => $q->where('pop_id', $popId))
            // visited_at kolom DATE — batas wajib string tanggal. Batas datetime
            // ('2026-11-01 00:00:00') bikin kunjungan tanggal 1 lolos dari
            // filter di sqlite (perbandingan string, '2026-11-01' < '… 00:00:00').
            ->whereBetween('visited_at', [$start->toDateString(), $end->toDateString()]);

        $byResult = $visitQuery()
            ->selectRaw('result, count(*) as total')
            ->groupBy('result')
            ->pluck('total', 'result')
            ->map(fn ($total) => (int) $total);

        $total = (int) $byResult->sum();

        $brokenPromises = $visitQuery()
            ->where('result', VisitResult::JANJI_BAYAR->value)
            ->whereNotNull('promised_date')
            ->whereDate('promised_date', '<', today())
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from((new Payment)->getTable())
                    ->whereColumn('payments.customer_id', 'collector_visits.customer_id')
                    ->where('payments.payment_status', PaymentStatus::VALID->value)
                    // Dua-duanya kolom DATE: bayar di hari kunjungan yang sama
                    // dihitung sudah menepati janji.
                    ->whereColumn('payments.payment_date', '>=', 'collector_visits.visited_at');
            })
            ->count();

        return [
            'total' => $total,
            'by_result' => collect(VisitResult::cases())
                ->mapWithKeys(fn (VisitResult $r) => [$r->value => (int) ($byResult[$r->value] ?? 0)])
                ->all(),
            'conversion_rate' => $total > 0
                ? round(((int) ($byResult[VisitResult::BAYAR->value] ?? 0)) / $total * 100, 1)
                : null,
            'broken_promises' => $brokenPromises,
        ];
    }

    /**
     * Top 5 agen penyumbang pelanggan baru (baris CustomerAcquisition dibuat
     * CustomerObserver saat pelanggan jadi ACTIVE) dalam rentang periode.
     *
     * @return list<array{name: string, total: int}>
     */
    public function topAgents(string|int|null $popId, string $periodFrom, string $periodTo): array
    {
        return CustomerAcquisition::query()
            ->whereBetween('periode', [$periodFrom, $periodTo])
            ->join('customers', 'customers.id', '=', 'customer_acquisitions.customer_id')
            ->whereIn('customers.id', $this->customerQuery($popId)->select('customers.id'))
            ->leftJoin('agents', 'agents.id', '=', 'customers.agent_id')
            ->selectRaw("COALESCE(agents.name, 'Tanpa Agen') as agent_name, count(*) as total")
            ->groupBy('agent_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->agent_name, 'total' => (int) $row->total])
            ->all();
    }

    // ── P3 ──────────────────────────────────────────────────────────────

    /**
     * Okupansi port node ber-kapasitas — memakai getOdpSummary() dashboard
     * FOP (ambang Red Zone per tipe node satu sumber di NetworkNode).
     *
     * @return array{total_nodes: int, red_zone_count: int, total_ports: int, used_ports: int, occupancy_rate: float|null}
     */
    public function portOccupancy(User $user, string|int|null $popId): array
    {
        $hasAll = $this->access->hasAllPopAccess($user);
        $allowed = $hasAll ? [] : array_map('intval', $this->access->getAllowedPopIds($user));

        if ($this->hasPop($popId)) {
            // Filter dropdown dipersempit ke dalam scope user, gak pernah memperluas.
            $allowed = ($hasAll || in_array((int) $popId, $allowed, true)) ? [(int) $popId] : [];
            $hasAll = false;
        }

        $summary = $this->fopUnified->getOdpSummary($hasAll, $allowed);

        return [
            'total_nodes' => $summary['total_odps'],
            'red_zone_count' => $summary['red_zone_count'],
            'total_ports' => $summary['total_ports'],
            'used_ports' => $summary['used_ports'],
            'occupancy_rate' => $summary['total_ports'] > 0
                ? round($summary['used_ports'] / $summary['total_ports'] * 100, 1)
                : null,
        ];
    }

    /**
     * Kabel persilangan jalan yang masih terpasang di pelanggan yang sudah
     * putus (wajib ditarik) + yang dinyatakan tidak bisa ditarik.
     *
     * @return array{pending_removal: int, cannot_remove: int}
     */
    public function cableCrossings(string|int|null $popId): array
    {
        $base = fn () => CustomerCableCrossing::query()
            ->whereHas('customer', fn (Builder $q) => $q->applyUserScope()
                ->when($this->hasPop($popId), fn (Builder $c) => $c->where('pop_id', $popId)));

        return [
            'pending_removal' => $base()
                ->where('status', CableCrossingStatus::TERPASANG->value)
                ->whereHas('customer', fn (Builder $q) => $q->where('status', WorkflowTransition::TERMINATED->value))
                ->count(),
            'cannot_remove' => $base()
                ->where('status', CableCrossingStatus::TIDAK_BISA_DITARIK->value)
                ->count(),
        ];
    }

    /**
     * Material infrastruktur hasil bongkar yang belum diinspeksi gudang.
     * Scope persis InfrastructureRecoveryController::index() (POP tujuan
     * transaksi sumber).
     */
    public function pendingMaterialInspections(User $user, string|int|null $popId): int
    {
        return InfrastructureRecovery::query()
            ->where('status', 'pending_inspection')
            ->when(! $this->access->hasAllPopAccess($user), fn (Builder $q) => $q->whereHas(
                'sourceTransaction',
                fn (Builder $s) => $s->whereIn('to_pop_id', $this->access->getAllowedPopIds($user))
            ))
            ->when($this->hasPop($popId), fn (Builder $q) => $q->whereHas(
                'sourceTransaction',
                fn (Builder $s) => $s->where('to_pop_id', $popId)
            ))
            ->count();
    }

    /**
     * Task di luar Survey/PSB (yang sudah ada di blok Delivery Lapangan):
     * MTN, C-REQ, O-REQ, INFR REQ — selesai periode & masih antre per tipe,
     * plus total Pending (kerja berhenti, balik ke antrean FOP) dan Lapor
     * Nanti (kerja beres, laporan menyusul) lintas semua tipe.
     *
     * @return array{by_type: array<string, array{completed: int, queued: int}>, pending: int, lapor_nanti: int}
     */
    public function nonPsbTasks(string|int|null $popId, Carbon $start, Carbon $end): array
    {
        $types = [TaskType::MAINTENANCE, TaskType::CREQ, TaskType::OREQ, TaskType::INFR];
        $typeValues = array_map(fn (TaskType $t) => $t->value, $types);

        $completed = $this->taskQuery($popId)
            ->whereIn('task_type', $typeValues)
            ->where('status', TaskStatus::SELESAI->value)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('task_type, count(*) as total')
            ->groupBy('task_type')
            ->pluck('total', 'task_type');

        $queued = $this->taskQuery($popId)
            ->whereIn('task_type', $typeValues)
            ->whereIn('status', $this->queuedStatusValues())
            ->selectRaw('task_type, count(*) as total')
            ->groupBy('task_type')
            ->pluck('total', 'task_type');

        $byStatus = $this->taskQuery($popId)
            ->whereIn('status', [TaskStatus::PENDING->value, TaskStatus::LAPOR_NANTI->value])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'by_type' => collect($types)->mapWithKeys(fn (TaskType $t) => [$t->value => [
                'completed' => (int) ($completed[$t->value] ?? 0),
                'queued' => (int) ($queued[$t->value] ?? 0),
            ]])->all(),
            'pending' => (int) ($byStatus[TaskStatus::PENDING->value] ?? 0),
            'lapor_nanti' => (int) ($byStatus[TaskStatus::LAPOR_NANTI->value] ?? 0),
        ];
    }

    /**
     * Permintaan stok cabang yang belum terpenuhi. Status PENDING + PARTIAL
     * sama dengan badge sidebar (ActionQueueBadgeService::stockRequests()).
     *
     * @param  Collection<int, int>  $warehousePopIds  POP gudang dalam scope user ∩ filter dashboard
     */
    public function openStockRequests(Collection $warehousePopIds): int
    {
        if ($warehousePopIds->isEmpty()) {
            return 0;
        }

        return StockRequest::query()
            ->whereIn('status', [StockRequestStatus::PENDING->value, StockRequestStatus::PARTIAL->value])
            ->whereIn('cabang_pop_id', $warehousePopIds)
            ->count();
    }

    /**
     * Pengajuan barang yang menunggu keputusan FOP.
     *
     * @param  Collection<int, int>  $warehousePopIds  POP gudang dalam scope user ∩ filter dashboard
     */
    public function pendingPengajuanBarang(Collection $warehousePopIds): int
    {
        if ($warehousePopIds->isEmpty()) {
            return 0;
        }

        return PengajuanBarang::query()
            ->where('status', PengajuanBarangStatus::MENUNGGU_FOP->value)
            ->whereIn('pop_id', $warehousePopIds)
            ->count();
    }

    // ── Helper ──────────────────────────────────────────────────────────

    private function hasPop(string|int|null $popId): bool
    {
        return $popId !== null && $popId !== '';
    }

    /** @return Builder<Customer> */
    private function customerQuery(string|int|null $popId): Builder
    {
        return Customer::query()
            ->applyUserScope()
            ->when($this->hasPop($popId), fn (Builder $q) => $q->where('customers.pop_id', $popId));
    }

    /** @return Builder<Task> */
    private function taskQuery(string|int|null $popId): Builder
    {
        return Task::query()
            ->applyUserScope()
            ->when($this->hasPop($popId), fn (Builder $q) => $q->where('tasks.pop_id', $popId));
    }

    /** @return list<string> */
    private function queuedStatusValues(): array
    {
        return array_map(fn (TaskStatus $s) => $s->value, self::QUEUED_TASK_STATUSES);
    }
}
