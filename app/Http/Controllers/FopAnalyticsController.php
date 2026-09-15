<?php

namespace App\Http\Controllers;

use App\Enums\InventoryTransactionType;
use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\TaskTeam;
use App\Models\TaskWorkTool;
use App\Models\User;
use App\Models\WorkTool;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard Analitik FOP — agregat lintas periode (docs/plan/analisa-dashboard-analitik-fop.md).
 * Terpisah dari `/fop` (FopDashboardController, operasional harian, dibuka
 * tiap shift) — halaman ini dibuka mingguan/bulanan buat evaluasi pola &
 * performa: alat kerja terpakai, wilayah pemasangan/komplain/gagal, beban
 * & solving teknisi, backlog, dan durasi pengerjaan terlama.
 *
 * Read-only murni, mayoritas method aggregator MENGEMBALIKAN PLAIN ARRAY
 * (bukan Collection) — pola sama WarehouseReportController, aman dipakai
 * view & kalau nanti perlu di-cache (Eloquent Collection korup lewat cache
 * store, lihat komentar FopDashboardController::index()). Pengecualian:
 * `buildBacklog()` balikin `LengthAwarePaginator` (section itu di-paginate,
 * bukan plain array) — JANGAN di-cache langsung kalau nanti section lain
 * butuh caching, paginator bukan value object polos.
 *
 * Proksi metrik (disepakati user, lihat docs/plan/analisa-dashboard-analitik-fop.md):
 * - "Task Gagal" = status DIBATALKAN (bukan status "gagal" tersendiri — TaskStatus
 *   enum tidak punya itu, dan `cancel_reason` masih bebas teks).
 * - "Komplain" = task_type MAINTENANCE/MTN (tidak ada tabel komplain terpisah).
 * Label UI WAJIB eksplisit ("Task Dibatalkan"/"Task Maintenance"), bukan klaim
 * "Gagal"/"Komplain" murni — supaya tidak menyesatkan pembaca dashboard.
 */
class FopAnalyticsController extends Controller
{
    /**
     * Tanggal mulai kolom `completed_by` reliable (commit f6a2f77, 2026-08-07
     * — tracking completed_by pada task). Leaderboard "solving terbanyak"
     * kasih badge peringatan kalau rentang filter menjangkau sebelum tanggal
     * ini, karena data historisnya mungkin belum lengkap.
     */
    private const COMPLETED_BY_RELIABLE_SINCE = '2026-08-07';

    public function __construct(private readonly EffectiveAccessService $accessService) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $hasAllPopAccess = $this->accessService->hasAllPopAccess($user);
        $allowedPopIds = $this->accessService->getAllowedPopIds($user);

        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();

        $granularity = in_array($request->query('granularity'), ['harian', 'mingguan', 'bulanan', 'tahunan'], true)
            ? $request->query('granularity')
            : 'bulanan';
        $popFilter = $request->integer('pop_id') ?: null;

        [$periodStart, $periodEnd] = $this->resolvePeriod($request, $granularity);

        $toolRanking = $this->buildToolUsageRanking($user, $popFilter, $periodStart, $periodEnd);
        $materialRanking = $this->buildMaterialUsageRanking($user, $popFilter, $periodStart, $periodEnd);
        $materialTrend = $this->buildMaterialUsageTrend($user, $popFilter, $periodStart, $periodEnd, $granularity);
        $modemRanking = $this->buildModemUsageRanking($user, $popFilter, $periodStart, $periodEnd);
        $modemInstalasiTotal = collect($modemRanking)->sum('instalasi');
        $modemMaintenanceTotal = collect($modemRanking)->sum('maintenance');
        $materialTrendChart = $this->buildTrendChartRows($materialTrend, $materialRanking, 'item_name');
        $toolTypesUsed = count($toolRanking);
        $toolTypesActive = WorkTool::where('is_active', true)->count();
        $toolTotal = collect($toolRanking)->sum('total');

        $regionInstallation = $this->buildRegionRanking($hasAllPopAccess, $allowedPopIds, $popFilter, $periodStart, $periodEnd, TaskType::PEMASANGAN, TaskStatus::SELESAI);
        $regionMaintenance = $this->buildRegionRanking($hasAllPopAccess, $allowedPopIds, $popFilter, $periodStart, $periodEnd, TaskType::MAINTENANCE, null);
        $regionCancelled = $this->buildRegionRanking($hasAllPopAccess, $allowedPopIds, $popFilter, $periodStart, $periodEnd, null, TaskStatus::DIBATALKAN);
        // KPI "Task Dibatalkan" WAJIB scalar total terpisah — bukan
        // `sum($regionCancelled['rows'])`, itu udah dipotong top 20 kecamatan
        // (lihat buildRegionRanking()), jadi undercount kalau kecamatan
        // dengan pembatalan lebih dari 20.
        $cancelledTotal = $this->buildCancelledTotal($hasAllPopAccess, $allowedPopIds, $popFilter, $periodStart, $periodEnd);

        $workloadLeaderboard = $this->buildWorkloadLeaderboard($popFilter, $allowedPopIds, $hasAllPopAccess, $periodStart, $periodEnd);
        $solvingLeaderboard = $this->buildSolvingLeaderboard($user, $popFilter, $periodStart, $periodEnd);
        $completedByWarning = $periodStart->lt(Carbon::parse(self::COMPLETED_BY_RELIABLE_SINCE));

        $backlog = $this->buildBacklog($user, $popFilter);
        $backlogStats = $this->buildBacklogStats($user, $popFilter);

        $durationRanking = $this->buildLongestDurationRanking($user, $popFilter, $periodStart, $periodEnd);

        // Komparasi periode sebelumnya (WoW/MoM/dst tergantung granularitas)
        // — cuma 3 metrik periode-scoped ini (bukan backlog, itu snapshot
        // live independen dari filter periode, lihat catatan di view).
        $periodComparison = $this->buildPeriodComparison(
            $user, $popFilter, $hasAllPopAccess, $allowedPopIds, $periodStart, $periodEnd,
            $toolTotal, $durationRanking['average'], $cancelledTotal
        );

        return view('fop.analytics', [
            'pops' => $pops,
            'popFilter' => $popFilter,
            'granularity' => $granularity,
            'periodFrom' => $periodStart->format('Y-m-d'),
            'periodTo' => $periodEnd->format('Y-m-d'),
            'materialRanking' => $materialRanking,
            'toolTotal' => $toolTotal,
            'materialTrendChart' => $materialTrendChart,
            'modemRanking' => $modemRanking,
            'modemInstalasiTotal' => $modemInstalasiTotal,
            'modemMaintenanceTotal' => $modemMaintenanceTotal,
            'toolTypesUsed' => $toolTypesUsed,
            'toolTypesActive' => $toolTypesActive,
            'regionInstallation' => $regionInstallation['rows'],
            'regionInstallationTotal' => $regionInstallation['total'],
            'regionMaintenance' => $regionMaintenance['rows'],
            'regionMaintenanceTotal' => $regionMaintenance['total'],
            'regionCancelled' => $regionCancelled['rows'],
            'regionCancelledTotal' => $regionCancelled['total'],
            'cancelledTotal' => $cancelledTotal,
            'workloadLeaderboard' => $workloadLeaderboard['rows'],
            'workloadLeaderboardTotal' => $workloadLeaderboard['total'],
            'solvingLeaderboard' => $solvingLeaderboard['rows'],
            'solvingLeaderboardTotal' => $solvingLeaderboard['total'],
            'completedByWarning' => $completedByWarning,
            'backlog' => $backlog,
            'backlogStats' => $backlogStats,
            'durationRanking' => $durationRanking['rows'],
            'durationAverage' => $durationRanking['average'],
            'periodComparison' => $periodComparison,
        ]);
    }

    /**
     * Rentang periode default per granularitas, atau dari query `from`/`to`
     * kalau user isi eksplisit (format `Y-m-d`, divalidasi try/catch — beda
     * dari WarehouseReportController yang gak validasi eksplisit, dashboard
     * analitik ini punya lebih banyak kombinasi filter jadi lebih rawan
     * ketik URL manual).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Request $request, string $granularity): array
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if ($from && $to) {
            try {
                return [
                    Carbon::createFromFormat('Y-m-d', $from)->startOfDay(),
                    Carbon::createFromFormat('Y-m-d', $to)->endOfDay(),
                ];
            } catch (\Exception) {
                // Format tidak valid — abaikan, jatuh ke default granularitas di bawah.
            }
        }

        $now = Carbon::now();

        return match ($granularity) {
            'harian' => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            'mingguan' => [$now->copy()->subWeeks(12)->startOfDay(), $now->copy()->endOfDay()],
            'tahunan' => [$now->copy()->subYears(5)->startOfDay(), $now->copy()->endOfDay()],
            default => [$now->copy()->subMonths(12)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /**
     * Section 1a — Ranking alat kerja terpakai. GROUP BY `tool_name`
     * (snapshot string), bukan `work_tool_id` — FK boleh null kalau master
     * alat sudah dihapus, `tool_name` aman dipakai historis (migrasi
     * 2026_08_01_000004).
     *
     * @return array<int, array{tool_name: string, total: int}>
     */
    private function buildToolUsageRanking(User $user, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        return TaskWorkTool::query()
            ->join('fop_tasks', 'fop_tasks.id', '=', 'task_work_tools.fop_task_id')
            ->tap(fn ($q) => $this->scopeFopTaskJoin($q, $user, $popFilter))
            ->whereBetween('task_work_tools.created_at', [$start, $end])
            ->selectRaw('task_work_tools.tool_name as tool_name, COUNT(*) as total')
            ->groupBy('task_work_tools.tool_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['tool_name' => $row->tool_name, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * Section 1b — Tren pemakaian alat per hari dalam rentang (view yang
     * re-bucket ke minggu/bulan/tahun di sisi PHP kalau perlu) — agregasi
     * per DATE() portabel lintas SQLite (test) & MySQL (prod), beda dari
     * pola `work_date` di FopDashboardController yang butuh whereBetween
     * buat alasan yang sama.
     *
     * @return array<int, array{bucket: string, tool_name: string, total: int}>
     */
    private function buildToolUsageTrend(User $user, ?int $popFilter, Carbon $start, Carbon $end, string $granularity): array
    {
        $rows = TaskWorkTool::query()
            ->join('fop_tasks', 'fop_tasks.id', '=', 'task_work_tools.fop_task_id')
            ->tap(fn ($q) => $this->scopeFopTaskJoin($q, $user, $popFilter))
            ->whereBetween('task_work_tools.created_at', [$start, $end])
            ->select('task_work_tools.tool_name', 'task_work_tools.created_at')
            ->get();

        $bucketFormat = match ($granularity) {
            'harian' => 'Y-m-d',
            'mingguan' => 'Y-\WW',
            'tahunan' => 'Y',
            default => 'Y-m',
        };

        return $rows
            ->groupBy(fn ($row) => Carbon::parse($row->created_at)->format($bucketFormat).'|'.$row->tool_name)
            ->map(function (Collection $group) use ($bucketFormat) {
                $first = $group->first();

                return [
                    'bucket' => Carbon::parse($first->created_at)->format($bucketFormat),
                    'tool_name' => $first->tool_name,
                    'total' => $group->count(),
                ];
            })
            ->sortBy('bucket')
            ->values()
            ->all();
    }

    /**
     * Section 1c — Ranking barang GUDANG terpakai (bukan alat kerja —
     * `task_materials` kind=TERPAKAI, hasil `InventoryService::consumeFromCustody()`/
     * `installSerial()`, ADHOC-54). GROUP BY `item_name` (snapshot string,
     * pola sama `buildToolUsageRanking()`) dan SUM `qty` — beda dari alat
     * kerja yang cukup COUNT baris, barang gudang punya satuan (meter,
     * pcs) yang jumlahnya sendiri lebih berarti daripada jumlah baris catat.
     *
     * @return array<int, array{item_name: string, total: float}>
     */
    private function buildMaterialUsageRanking(User $user, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        return TaskMaterial::query()
            ->join('fop_tasks', 'fop_tasks.id', '=', 'task_materials.fop_task_id')
            ->tap(fn ($q) => $this->scopeFopTaskJoin($q, $user, $popFilter))
            ->where('task_materials.kind', MaterialKind::TERPAKAI->value)
            ->whereBetween('task_materials.created_at', [$start, $end])
            ->selectRaw('task_materials.item_name as item_name, SUM(task_materials.qty) as total')
            ->groupBy('task_materials.item_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['item_name' => $row->item_name, 'total' => (float) $row->total])
            ->all();
    }

    /**
     * Section 1d — Tren pemakaian barang gudang per periode, pola persis
     * `buildToolUsageTrend()` (bucket per granularitas filter) tapi SUM qty,
     * bukan COUNT baris — lihat alasan di `buildMaterialUsageRanking()`.
     *
     * @return array<int, array{bucket: string, item_name: string, total: float}>
     */
    private function buildMaterialUsageTrend(User $user, ?int $popFilter, Carbon $start, Carbon $end, string $granularity): array
    {
        $rows = TaskMaterial::query()
            ->join('fop_tasks', 'fop_tasks.id', '=', 'task_materials.fop_task_id')
            ->tap(fn ($q) => $this->scopeFopTaskJoin($q, $user, $popFilter))
            ->where('task_materials.kind', MaterialKind::TERPAKAI->value)
            ->whereBetween('task_materials.created_at', [$start, $end])
            ->select('task_materials.item_name', 'task_materials.qty', 'task_materials.created_at')
            ->get();

        $bucketFormat = match ($granularity) {
            'harian' => 'Y-m-d',
            'mingguan' => 'Y-\WW',
            'tahunan' => 'Y',
            default => 'Y-m',
        };

        return $rows
            ->groupBy(fn ($row) => Carbon::parse($row->created_at)->format($bucketFormat).'|'.$row->item_name)
            ->map(function (Collection $group) use ($bucketFormat) {
                $first = $group->first();

                return [
                    'bucket' => Carbon::parse($first->created_at)->format($bucketFormat),
                    'item_name' => $first->item_name,
                    'total' => (float) $group->sum('qty'),
                ];
            })
            ->sortBy('bucket')
            ->values()
            ->all();
    }

    /**
     * Section 1e — Modem/ONT TERPASANG ke pelanggan, dipecah Instalasi vs
     * Maintenance (ADHOC, 2026-09-12, permintaan eksplisit user).
     *
     * BEDA JALUR dari `buildMaterialUsageRanking()` — modem itemnya
     * `tracking_type=SERIALIZED` (per-unit, py SN pabrik), jadi gak pernah
     * lewat `TaskMaterialService`/`task_materials` sama sekali.
     * `InventoryService::consumeFromCustody()` bahkan MENOLAK item
     * SERIALIZED secara eksplisit (lihat komentar class-nya) — SN
     * dicatat lewat `installSerial()` ke `inventory_transactions` type
     * INSTALL. Makanya modem HARUS diquery terpisah dari ranking barang
     * gudang biasa, bukan sekadar filter kategori di query yang sama.
     *
     * "Instalasi" vs "Maintenance" dibaca dari `fop_tasks.category` pada
     * task yang memicu INSTALL itu (TaskType::PEMASANGAN/MAINTENANCE) —
     * BUKAN kolom terpisah di ledger, karena `inventory_transactions`
     * gak punya kolom "alasan pasang".
     *
     * PENTING (temuan investigasi 2026-09-12): `installSerial()` SAAT INI
     * cuma dipanggil dari `CustomerInstallationController::storeSpeedtest()`
     * (jalur Instalasi/PSB). `TaskMaintenanceController` BELUM py jalur
     * ganti modem yang menulis SN — jadi kolom "maintenance" di sini akan
     * SELALU 0 sampai fitur ganti-modem-saat-maintenance dibangun (di luar
     * scope Dashboard Analitik, JANGAN dibangun di sini). Ini bukan bug
     * query, itu memang belum ada datanya di sistem.
     *
     * @return array<int, array{item_name: string, instalasi: int, maintenance: int}>
     */
    private function buildModemUsageRanking(User $user, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $rows = InventoryTransaction::query()
            ->join('fop_tasks', 'fop_tasks.id', '=', 'inventory_transactions.fop_task_id')
            ->join('items', 'items.id', '=', 'inventory_transactions.item_id')
            ->join('item_categories', 'item_categories.id', '=', 'items.item_category_id')
            ->tap(fn ($q) => $this->scopeFopTaskJoin($q, $user, $popFilter))
            ->where('inventory_transactions.type', InventoryTransactionType::INSTALL->value)
            ->where('item_categories.code', 'modem_ont')
            ->whereBetween('inventory_transactions.created_at', [$start, $end])
            ->selectRaw('items.name as item_name, fop_tasks.category as category, COUNT(*) as total')
            ->groupBy('items.name', 'fop_tasks.category')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->item_name] ??= ['item_name' => $row->item_name, 'instalasi' => 0, 'maintenance' => 0];
            $bucket = $row->category === TaskType::MAINTENANCE->value ? 'maintenance' : 'instalasi';
            $grouped[$row->item_name][$bucket] += (int) $row->total;
        }

        return collect($grouped)
            ->sortByDesc(fn (array $r) => $r['instalasi'] + $r['maintenance'])
            ->values()
            ->all();
    }

    /**
     * Section 2 — Ranking kecamatan (district) per varian: pemasangan (PSB
     * selesai), komplain (MTN, proksi), task gagal (dibatalkan, proksi).
     * Panggil dengan `$type=null` buat abaikan filter tipe (dipakai varian
     * "task gagal", yang menyertakan semua tipe task dibatalkan) atau
     * `$status=null` buat abaikan filter status (varian komplain — MTN
     * dihitung apapun statusnya, sesuai keputusan proksi dokumen analisa).
     *
     * Balikin SEMUA kecamatan (bukan `limit(20)` mentah) lalu potong top 20
     * di PHP — beda dari backlog (bisa ribuan baris, layak paginate), jumlah
     * kecamatan realistis dalam skala puluhan per POP, jadi cukup 1 query
     * lalu `take(20)` + hitung total di sisi PHP tanpa query kedua.
     *
     * @return array{rows: array<int, array{district_name: string, total: int}>, total: int}
     */
    private function buildRegionRanking(bool $hasAllPopAccess, array $allowedPopIds, ?int $popFilter, Carbon $start, Carbon $end, ?TaskType $type, ?TaskStatus $status): array
    {
        // Filter POP manual pakai `tasks.pop_id` QUALIFIED (bukan
        // `Task::applyUserScope()`) — scope itu nulis kondisi `pop_id` TANPA
        // prefix tabel, dan begitu di-join ke `customers` (yang juga punya
        // kolom `pop_id`), SQLite/MySQL nolak dengan "ambiguous column name".
        // Pola sama `Customer::when(!$hasAllPopAccess, ...)` di FopDashboardController.
        $all = Task::query()
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('tasks.pop_id', $allowedPopIds))
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->join('customers', 'customers.id', '=', 'tasks.customer_id')
            ->join('districts', 'districts.id', '=', 'customers.district_id')
            ->when($type, fn ($q) => $q->where('tasks.task_type', $type->value))
            ->when($status, fn ($q) => $q->where('tasks.status', $status->value))
            ->whereBetween('tasks.scheduled_at', [$start, $end])
            ->selectRaw('districts.name as district_name, COUNT(*) as total')
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['district_name' => $row->district_name, 'total' => (int) $row->total]);

        return ['rows' => $all->take(20)->values()->all(), 'total' => $all->count()];
    }

    /**
     * Section 3a — Beban tugas per teknisi, dihitung per baris `TaskTeam`
     * (assignment individual — tim 2 orang = 2 beban, bukan dobel-hitung
     * per task atau 0.5 per orang), semua status task (keputusan user:
     * gambaran beban historis lengkap, bukan cuma yang aktif).
     * `TaskTeam` (bukan `FopTask::technicians()`) — sumber kebenaran
     * assignment eksekusi aktual, lihat komentar kelas.
     *
     * Balikin SEMUA teknisi (bukan `limit(20)` mentah), potong top 20 di PHP
     * — jumlah teknisi realistis puluhan-ratusan, aman 1 query.
     *
     * @return array{rows: array<int, array{user_id: int, name: string, total: int}>, total: int}
     */
    private function buildWorkloadLeaderboard(?int $popFilter, array $allowedPopIds, bool $hasAllPopAccess, Carbon $start, Carbon $end): array
    {
        $all = TaskTeam::query()
            ->join('tasks', 'tasks.id', '=', 'task_teams.task_id')
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('tasks.pop_id', $allowedPopIds))
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->join('users', 'users.id', '=', 'task_teams.user_id')
            ->whereBetween('tasks.scheduled_at', [$start, $end])
            ->selectRaw('users.id as user_id, users.name as name, COUNT(*) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['user_id' => $row->user_id, 'name' => $row->name, 'total' => (int) $row->total]);

        return ['rows' => $all->take(20)->values()->all(), 'total' => $all->count()];
    }

    /**
     * Section 3b — Leaderboard "solving terbanyak" (task selesai per
     * `completed_by`). Kolom `completed_by` baru ditambah (commit f6a2f77,
     * 2026-08-07) — lihat `$completedByWarning` di index() buat badge
     * peringatan kalau filter menjangkau sebelum tanggal itu.
     *
     * Terima `$popFilter` EKSPLISIT (bukan cuma `applyUserScope()`) — sebelum
     * ini section-nya diam-diam ignore dropdown POP di filter, cuma ikut
     * scope keseluruhan user, jadi pilih 1 POP di dropdown gak ngefek ke sini.
     * Balikin SEMUA teknisi, potong top 20 di PHP (sama pola leaderboard beban).
     *
     * @return array{rows: array<int, array{user_id: int, name: string, total: int}>, total: int}
     */
    private function buildSolvingLeaderboard(User $user, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $all = Task::query()
            ->applyUserScope($user)
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->where('tasks.status', TaskStatus::SELESAI->value)
            ->whereNotNull('tasks.completed_by')
            ->whereBetween('tasks.completed_at', [$start, $end])
            ->join('users', 'users.id', '=', 'tasks.completed_by')
            ->selectRaw('users.id as user_id, users.name as name, COUNT(*) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['user_id' => $row->user_id, 'name' => $row->name, 'total' => (int) $row->total]);

        return ['rows' => $all->take(20)->values()->all(), 'total' => $all->count()];
    }

    /**
     * Section 4 — Backlog task belum dikerjakan (draft/terjadwal/pending),
     * DI-PAGINATE (bukan `limit(100)` mentah) — antrean backlog bisa jauh
     * lebih dari 100 baris di POP dengan banyak cabang, dan versi lama diam-
     * diam memotong sisanya tanpa kasih tahu ada berapa total. Cakupan Task
     * saja (keputusan user), tidak termasuk FopTask draft standalone yang
     * belum jadi Task nyata. `over_sla`/`sla_deadline` reuse
     * `Task::isOverSla()`/`Task::slaDeadline()` — JANGAN reimplement, itu
     * single source of truth yang sama dipakai badge SLA breach di `/fop`.
     *
     * Balikannya BUKAN plain array (beda dari section lain) — `LengthAwarePaginator`
     * biar view bisa render `->links()` sambil query string filter (period/
     * pop_id/granularity) ikut kebawa lewat `withQueryString()`.
     *
     * Terima `$popFilter` EKSPLISIT — sebelum ini section backlog diam-diam
     * ignore dropdown POP di filter (cuma ikut scope keseluruhan user).
     *
     * @return LengthAwarePaginator<int, array{id: int, task_number: ?string, umur_jam: int, sla_deadline: ?string, over_sla: bool, status_label: string}>
     */
    private function buildBacklog(User $user, ?int $popFilter): LengthAwarePaginator
    {
        // `->with('customer')` WAJIB — `$t->customer?->full_name` di bawah
        // lazy-load relasi itu, dan `Model::preventLazyLoading()` aktif
        // non-production (meledak `LazyLoadingViolationException`).
        return Task::query()
            ->with('customer')
            ->applyUserScope($user)
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->whereIn('status', [TaskStatus::DRAFT->value, TaskStatus::TERJADWAL->value, TaskStatus::PENDING->value])
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Task $t) => [
                'id' => $t->id,
                'task_number' => $t->task_number,
                'customer_name' => $t->customer?->full_name ?? '—',
                'task_type_label' => $t->task_type->label(),
                'umur_jam' => (int) $t->created_at->diffInHours(now()),
                'sla_deadline' => $t->slaDeadline()?->toIso8601String(),
                'over_sla' => $t->isOverSla(),
                'report_deferred' => (bool) $t->report_deferred,
                'status_label' => $t->status->displayLabel($t->report_deferred),
            ]);
    }

    /**
     * KPI ringkasan backlog (total + jumlah lewat SLA) — DIHITUNG TERPISAH
     * dari `buildBacklog()` (bukan `$backlog->total()`/hitung dari halaman
     * aktif), karena begitu backlog di-paginate, koleksi yang dibaca view
     * cuma 15 baris per halaman — gak bisa dipakai buat KPI "total backlog"
     * atau "berapa yang over SLA" di seluruh antrean. `over_sla` bukan kolom
     * DB (dihitung `Task::isOverSla()` dari `task_type`/`scheduled_at`/
     * `started_at`/`sla_minutes`), jadi query cuma ambil kolom itu — bukan
     * `SELECT *` — biar ringan walau baris backlog-nya ribuan.
     *
     * @return array{total: int, over_sla: int}
     */
    private function buildBacklogStats(User $user, ?int $popFilter): array
    {
        $rows = Task::query()
            ->applyUserScope($user)
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->whereIn('status', [TaskStatus::DRAFT->value, TaskStatus::TERJADWAL->value, TaskStatus::PENDING->value])
            ->get(['id', 'task_type', 'scheduled_at', 'started_at', 'completed_at', 'sla_minutes']);

        return [
            'total' => $rows->count(),
            'over_sla' => $rows->filter(fn (Task $t) => $t->isOverSla())->count(),
        ];
    }

    /**
     * Section 5 — Top 20 task durasi pengerjaan terlama (`started_at` →
     * `completed_at`), reuse `Task::actualDurationMinutes()` (bukan hitung
     * manual) biar konsisten sama definisi model. Baseline rata-rata dihitung
     * dari SELURUH task selesai di periode (sebelum dipotong top 20), bukan
     * cuma dari 20 baris teratas.
     *
     * Terima `$popFilter` EKSPLISIT — sebelum ini section durasi terlama
     * diam-diam ignore dropdown POP di filter (cuma ikut scope keseluruhan user).
     *
     * @return array{rows: array<int, array{task_number: ?string, durasi_menit: int, teknisi: ?string, task_type: string}>, average: ?float}
     */
    private function buildLongestDurationRanking(User $user, ?int $popFilter, Carbon $start, Carbon $end): array
    {
        $tasks = Task::with('completedBy')
            ->applyUserScope($user)
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->where('status', TaskStatus::SELESAI->value)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->get()
            ->map(fn (Task $t) => [
                'task_number' => $t->task_number,
                'durasi_menit' => $t->actualDurationMinutes(),
                'teknisi' => $t->completedBy?->name,
                'task_type' => $t->task_type->label(),
            ])
            ->filter(fn ($row) => $row['durasi_menit'] !== null);

        $average = $tasks->isNotEmpty() ? round($tasks->avg('durasi_menit'), 1) : null;

        return [
            'rows' => $tasks->sortByDesc('durasi_menit')->take(20)->values()->all(),
            'average' => $average,
        ];
    }

    /**
     * Reshape `$toolTrend` (baris {bucket, tool_name, total}) jadi baris per
     * bucket dengan tiap top-5 alat sebagai kolom/series — bentuk yang
     * dipahami `drawGroupedBar()` di view (pola sama `WarehouseReportController
     * ::buildAdjustmentSummary()` reshape ke `$lossChartData`). Dibatasi top 5
     * alat (bukan semua) — lebih dari itu chart batang berkelompok jadi
     * gak terbaca & warnanya abis (palet kategorikal cuma 5 warna, lihat
     * dataviz skill § color-formula).
     *
     * @param  array<int, array{tool_name: string, total: int}>  $toolRanking  urutan desc, dipakai nentuin top 5
     * @return array{rows: array<int, array<string, int|string>>, series: array<int, array{key: string, label: string}>}
     */
    /**
     * Generik: susun bar chart per-bucket × top-5 label dari pasangan
     * ranking (buat nentuin top 5) + trend (angka per bucket). Awalnya
     * khusus alat kerja (`tool_name`); dipakai lagi buat barang gudang
     * (`item_name`) — bentuk datanya identik, cuma nama kolom groupnya
     * beda, jadi diparameterkan lewat `$labelKey` daripada duplikasi method.
     *
     * @param  array<int, array{bucket: string, total: int|float}>  $trend  tiap baris juga punya key $labelKey
     * @param  array<int, array<string, mixed>>  $ranking  tiap baris juga punya key $labelKey, diurut desc buat nentuin top 5
     */
    private function buildTrendChartRows(array $trend, array $ranking, string $labelKey): array
    {
        $topLabels = collect($ranking)->take(5)->pluck($labelKey)->all();

        if (empty($topLabels)) {
            return ['rows' => [], 'series' => []];
        }

        $lookup = collect($trend)->keyBy(fn ($row) => $row['bucket'].'|'.$row[$labelKey]);
        $buckets = collect($trend)->pluck('bucket')->unique()->sort()->values();

        $rows = $buckets->map(function (string $bucket) use ($lookup, $topLabels) {
            $row = ['bucket' => $bucket];
            foreach ($topLabels as $label) {
                $row[$label] = $lookup->get($bucket.'|'.$label)['total'] ?? 0;
            }

            return $row;
        })->values()->all();

        $series = collect($topLabels)->map(fn ($label) => ['key' => $label, 'label' => $label])->all();

        return ['rows' => $rows, 'series' => $series];
    }

    /**
     * Total task dibatalkan (proksi "task gagal") — scalar, TERPISAH dari
     * `buildRegionRanking()` (yang rows-nya dipotong top 20 kecamatan).
     * Dipakai KPI card & baseline komparasi periode, JANGAN diganti balik ke
     * `sum($regionCancelled['rows'])` — itu undercount begitu kecamatan
     * dengan pembatalan lebih dari 20.
     */
    private function buildCancelledTotal(bool $hasAllPopAccess, array $allowedPopIds, ?int $popFilter, Carbon $start, Carbon $end): int
    {
        return Task::query()
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('tasks.pop_id', $allowedPopIds))
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->where('status', TaskStatus::DIBATALKAN->value)
            ->whereBetween('scheduled_at', [$start, $end])
            ->count();
    }

    /**
     * Komparasi 3 metrik periode-scoped (pemakaian alat, rata-rata durasi
     * solving, task dibatalkan) terhadap periode SEBELUMNYA — jendela sama
     * panjang, langsung menempel sebelum `$start` (mis. filter Bulanan 12
     * bulan → dibandingkan ke 12 bulan sebelum itu; custom `from`/`to` 10
     * hari → dibandingkan ke 10 hari sebelumnya). Backlog SENGAJA gak ikut
     * dibandingkan — itu snapshot antrean live, independen dari filter
     * periode (lihat catatan subtitle backlog di view).
     *
     * @return array<string, array{current: int|float|null, previous: int|float|null, delta_pct: ?float}>
     */
    private function buildPeriodComparison(
        User $user,
        ?int $popFilter,
        bool $hasAllPopAccess,
        array $allowedPopIds,
        Carbon $start,
        Carbon $end,
        int $toolTotalCurrent,
        ?float $durationAverageCurrent,
        int $cancelledTotalCurrent
    ): array {
        $windowSeconds = $start->diffInSeconds($end);
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($windowSeconds);

        $toolTotalPrevious = TaskWorkTool::query()
            ->join('fop_tasks', 'fop_tasks.id', '=', 'task_work_tools.fop_task_id')
            ->tap(fn ($q) => $this->scopeFopTaskJoin($q, $user, $popFilter))
            ->whereBetween('task_work_tools.created_at', [$prevStart, $prevEnd])
            ->count();

        $durationRowsPrevious = Task::query()
            ->applyUserScope($user)
            ->when($popFilter, fn ($q) => $q->where('tasks.pop_id', $popFilter))
            ->where('status', TaskStatus::SELESAI->value)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$prevStart, $prevEnd])
            ->get(['started_at', 'completed_at']);
        $durationAveragePrevious = $durationRowsPrevious->isNotEmpty()
            ? round($durationRowsPrevious->avg(fn (Task $t) => $t->actualDurationMinutes()), 1)
            : null;

        $cancelledTotalPrevious = $this->buildCancelledTotal($hasAllPopAccess, $allowedPopIds, $popFilter, $prevStart, $prevEnd);

        return [
            'tool_total' => [
                'current' => $toolTotalCurrent,
                'previous' => $toolTotalPrevious,
                'delta_pct' => $this->deltaPercent($toolTotalCurrent, $toolTotalPrevious),
            ],
            'duration_average' => [
                'current' => $durationAverageCurrent,
                'previous' => $durationAveragePrevious,
                'delta_pct' => $this->deltaPercent($durationAverageCurrent, $durationAveragePrevious),
            ],
            'cancelled_total' => [
                'current' => $cancelledTotalCurrent,
                'previous' => $cancelledTotalPrevious,
                'delta_pct' => $this->deltaPercent($cancelledTotalCurrent, $cancelledTotalPrevious),
            ],
        ];
    }

    /**
     * Persentase perubahan `$current` terhadap `$previous`. `null` kalau
     * salah satu null (data gak lengkap buat dibandingkan) ATAU
     * `$previous` == 0 (pembagian nol tak terdefinisi — bukan berarti
     * "naik tak terhingga", biarkan view render "Baru" bukan angka ngawur).
     */
    private function deltaPercent(int|float|null $current, int|float|null $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Terapkan POP scope ke query yang di-join lewat `fop_tasks` (buat
     * TaskWorkTool, yang gak punya `pop_id` langsung) — pola sama
     * `Task::applyUserScope()`, tapi disalin manual di sini karena builder-nya
     * sudah di-join lintas tabel (scope `FopTask::applyUserScope()` gak bisa
     * langsung dipakai atas builder `TaskWorkTool`).
     */
    private function scopeFopTaskJoin($query, User $user, ?int $popFilter): void
    {
        $hasAllPopAccess = $this->accessService->hasAllPopAccess($user);
        $allowedPopIds = $this->accessService->getAllowedPopIds($user);

        $query->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('fop_tasks.pop_id', $allowedPopIds))
            ->when($popFilter, fn ($q) => $q->where('fop_tasks.pop_id', $popFilter));
    }
}
