<?php

namespace App\Http\Controllers;

use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Enums\TicketHistoryAction;
use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard NOC — pusat analisa dan pemantauan operasional tiket NOC.
 * Mendukung filter periode (default: 1 bulan berjalan) & POP scope,
 * kalkulasi durasi rata-rata di Ticketing, statistik daerah, issue category,
 * trend daerah x issue category, tren harian, SLA compliance, distribusi
 * aging, leaderboard performa individu Helpdesk/NOC, serta daftar antrean
 * tiket aktif terurut aging.
 *
 * docs/plan/noc-dashboard-analysis.md — revisi setelah modul Ticketing kelar,
 * fokus baru: alat ukur performa Helpdesk/NOC per individu (bukan cuma
 * monitoring snapshot).
 */
class NocDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('noc_dashboard.view'), 403);

        // Leaderboard performa individu punya permission SENDIRI, terpisah
        // dari noc_dashboard.view — lihat config/rbac.php. Data personal
        // (siapa nutup berapa tiket) sengaja bisa dimatikan independen dari
        // sekadar akses monitoring dashboard.
        $canViewPerformance = $user->hasPermission('noc_dashboard.performance.view');

        // Filter Param Handling (Default preset: month_to_date)
        $datePreset = $request->query('date_preset', 'month_to_date');
        $selectedPopId = $request->query('pop_id');
        $dateFromInput = $request->query('date_from');
        $dateToInput = $request->query('date_to');

        $now = Carbon::now();
        $dateFrom = null;
        $dateTo = null;

        switch ($datePreset) {
            case '7_days':
                $dateFrom = $now->copy()->subDays(6)->startOfDay();
                $dateTo = $now->copy()->endOfDay();
                break;
            case '30_days':
                $dateFrom = $now->copy()->subDays(29)->startOfDay();
                $dateTo = $now->copy()->endOfDay();
                break;
            case 'this_month':
                $dateFrom = $now->copy()->startOfMonth()->startOfDay();
                $dateTo = $now->copy()->endOfMonth()->endOfDay();
                break;
            case 'last_month':
                $dateFrom = $now->copy()->subMonth()->startOfMonth()->startOfDay();
                $dateTo = $now->copy()->subMonth()->endOfMonth()->endOfDay();
                break;
            case 'all_time':
                $dateFrom = null;
                $dateTo = null;
                break;
            case 'custom':
                if ($dateFromInput) {
                    $dateFrom = Carbon::parse($dateFromInput)->startOfDay();
                }
                if ($dateToInput) {
                    $dateTo = Carbon::parse($dateToInput)->endOfDay();
                }
                break;
            case 'month_to_date':
            default:
                $datePreset = 'month_to_date';
                $dateFrom = $now->copy()->startOfMonth()->startOfDay();
                $dateTo = $now->copy()->endOfDay();
                break;
        }

        // Base Query with User Scope (RBAC) & POP Filter
        $baseQuery = Ticket::query()->applyUserScope();

        if ($selectedPopId) {
            $baseQuery->where('pop_id', $selectedPopId);
        }

        // Filtered Query for Period Metrics
        $filteredQuery = (clone $baseQuery);
        if ($dateFrom) {
            $filteredQuery->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $filteredQuery->where('created_at', '<=', $dateTo);
        }

        // Cache 60 detik per user+filter — HANYA data array/skalar polos,
        // aman diserialize (lihat catatan korupsi Collection Eloquent di
        // bawah). $activeTickets, $activityFeed, dan leaderboard SENGAJA
        // tidak ikut cache tuple ini kalau butuh Collection Model mentah;
        // semua yang di sini SUDAH direduksi jadi array sebelum dikembalikan.
        $cacheKey = sprintf(
            'dashboard:noc:stats:%d:%s:%s:%s:%s',
            auth()->id(),
            $datePreset,
            $selectedPopId ?? '',
            $dateFromInput ?? '',
            $dateToInput ?? ''
        );

        [$stats, $issueStats, $regionStats, $trendMatrix, $deltaStats, $dailyTrend, $slaCompliance, $agingBuckets] = Cache::remember(
            $cacheKey,
            60,
            function () use ($filteredQuery, $baseQuery, $dateFrom, $dateTo) {
                // 1. Core Summary Metrics
                $totalTicket = (clone $filteredQuery)->count();

                $ticketSelesai = (clone $filteredQuery)
                    ->where('status', TicketHandlingStatus::CLOSED->value)
                    ->count();

                $ticketAssignFop = (clone $filteredQuery)
                    ->where('handler', TicketHandler::FOP->value)
                    ->count();

                $ticketDibatalkan = (clone $filteredQuery)
                    ->where('status', TicketHandlingStatus::CANCELLED->value)
                    ->count();

                $diprosesNoc = (clone $baseQuery)
                    ->where('handler', TicketHandler::NOC->value)
                    ->where('status', TicketHandlingStatus::OPEN->value)
                    ->count();

                $selesaiHariIni = (clone $baseQuery)
                    ->where('status', TicketHandlingStatus::CLOSED->value)
                    ->whereDate('updated_at', today())
                    ->count();

                $dibatalkanHariIni = (clone $baseQuery)
                    ->where('status', TicketHandlingStatus::CANCELLED->value)
                    ->whereDate('updated_at', today())
                    ->count();

                // 2. Average Handling Duration in Ticketing
                $resolvedTickets = (clone $filteredQuery)
                    ->where(function ($q) {
                        $q->whereNotNull('resolved_at')
                            ->orWhere('status', TicketHandlingStatus::CLOSED->value)
                            ->orWhere('handler', TicketHandler::FOP->value);
                    })
                    ->get();

                $avgMinutes = null;
                if ($resolvedTickets->isNotEmpty()) {
                    $durations = $resolvedTickets->map(function (Ticket $t) {
                        if ($t->resolved_at) {
                            return (int) $t->created_at->diffInMinutes($t->resolved_at);
                        }

                        return (int) $t->created_at->diffInMinutes($t->updated_at);
                    });
                    $avgMinutes = (int) round($durations->avg());
                }

                $avgDurationLabel = '-';
                if ($avgMinutes !== null) {
                    if ($avgMinutes < 60) {
                        $avgDurationLabel = "{$avgMinutes} Mns";
                    } else {
                        $h = intdiv($avgMinutes, 60);
                        $m = $avgMinutes % 60;
                        $avgDurationLabel = $m > 0 ? "{$h} Jam {$m} Mns" : "{$h} Jam";
                    }
                }

                $stats = [
                    'total_ticket' => $totalTicket,
                    'ticket_selesai' => $ticketSelesai,
                    'ticket_assign_fop' => $ticketAssignFop,
                    'ticket_dibatalkan' => $ticketDibatalkan,
                    'diproses_noc' => $diprosesNoc,
                    'selesai_hari_ini' => $selesaiHariIni,
                    'dibatalkan_hari_ini' => $dibatalkanHariIni,
                    'avg_duration_label' => $avgDurationLabel,
                    'avg_duration_minutes' => $avgMinutes,
                ];

                // 5. Statistik per Issue (Categorized & Percentage)
                $issueStatsRaw = (clone $filteredQuery)
                    ->with('issueCategory:id,name')
                    ->get()
                    ->groupBy(fn (Ticket $t) => $t->issueCategory?->name ?? 'Lainnya')
                    ->map->count()
                    ->sortDesc()
                    ->take(10);

                $issueStatsTotal = $issueStatsRaw->sum();
                $issueStats = $issueStatsRaw->map(function ($count) use ($issueStatsTotal) {
                    return [
                        'count' => $count,
                        'percentage' => $issueStatsTotal > 0 ? round(($count / $issueStatsTotal) * 100, 1) : 0,
                    ];
                });

                // 6. Statistik per Daerah (District/POP Categorized & Percentage)
                $regionStatsRaw = (clone $filteredQuery)
                    ->with(['customer.district:id,name', 'pop:id,name'])
                    ->get()
                    ->groupBy(function (Ticket $t) {
                        return $t->customer?->district?->name ?? $t->pop?->name ?? 'Tidak diketahui';
                    })
                    ->map->count()
                    ->sortDesc()
                    ->take(10);

                $regionStatsTotal = $regionStatsRaw->sum();
                $regionStats = $regionStatsRaw->map(function ($count) use ($regionStatsTotal) {
                    return [
                        'count' => $count,
                        'percentage' => $regionStatsTotal > 0 ? round(($count / $regionStatsTotal) * 100, 1) : 0,
                    ];
                });

                // 7. Trend Matrix: Daerah vs Issue Category
                $allFilteredTickets = (clone $filteredQuery)
                    ->with(['customer.district:id,name', 'pop:id,name', 'issueCategory:id,name'])
                    ->get();

                $topRegions = $regionStatsRaw->keys()->take(6)->toArray();
                $topIssues = $issueStatsRaw->keys()->take(6)->toArray();

                $matrix = [];
                $maxCellCount = 0;

                foreach ($topRegions as $regionName) {
                    $matrix[$regionName] = [];
                    foreach ($topIssues as $issueName) {
                        $count = $allFilteredTickets->filter(function (Ticket $t) use ($regionName, $issueName) {
                            $r = $t->customer?->district?->name ?? $t->pop?->name ?? 'Tidak diketahui';
                            $i = $t->issueCategory?->name ?? 'Lainnya';

                            return $r === $regionName && $i === $issueName;
                        })->count();

                        $matrix[$regionName][$issueName] = $count;
                        if ($count > $maxCellCount) {
                            $maxCellCount = $count;
                        }
                    }
                }

                $trendMatrix = [
                    'regions' => $topRegions,
                    'issues' => $topIssues,
                    'matrix' => $matrix,
                    'maxCount' => $maxCellCount,
                ];

                // 8. Delta vs periode sebelumnya (period-over-period), sama
                // panjang & langsung berhimpit sebelum $dateFrom. null kalau
                // filter "Semua Waktu" (gak ada window buat dibandingkan).
                $deltaStats = $this->buildDeltaStats($baseQuery, $dateFrom, $dateTo, $stats);

                // 9. Tren harian volume tiket (line chart) — masuk/selesai/dibatalkan.
                $dailyTrend = $this->buildDailyTrend($baseQuery, $dateFrom, $dateTo);

                // 10. SLA compliance (donut) — tiket resolved dalam periode yang punya sla_deadline_at.
                $slaCompliance = $this->buildSlaCompliance($filteredQuery);

                // 11. Distribusi aging tiket aktif NOC (bar chart) — TIDAK
                // dibatasi limit(30) seperti $activeTickets di bawah, biar
                // representatif buat seluruh antrean, bukan cuma yang tampil.
                $agingBuckets = $this->buildAgingBuckets($baseQuery);

                return [$stats, $issueStats->toArray(), $regionStats->toArray(), $trendMatrix, $deltaStats, $dailyTrend, $slaCompliance, $agingBuckets];
            }
        );

        // 3. Tiket Aktif NOC (Aging Queue: Paling lama nunggu di atas) — di
        // luar cache, lihat catatan di atas cache block.
        $activeTickets = (clone $baseQuery)
            ->where('handler', TicketHandler::NOC->value)
            ->where('status', TicketHandlingStatus::OPEN->value)
            ->with(['customer:id,full_name,cid,customer_code', 'creator:id,name', 'issueCategory:id,name', 'pop:id,name'])
            ->orderBy('created_at', 'asc')
            ->limit(30)
            ->get();

        // 4. Activity Feed — di luar cache, lihat catatan di atas cache block.
        $activityFeed = TicketHistory::query()
            ->whereHas('ticket', fn ($q) => $q->applyUserScope())
            ->with(['actor:id,name', 'ticket:id,ticket_number'])
            ->latest('happened_at')
            ->limit(20)
            ->get();

        // 12. Leaderboard performa individu — di luar cache (Collection Model
        // mentah, lihat catatan cache di atas), dan CUMA dihitung kalau user
        // punya permission-nya. Query murah dicegah buat user yang gak akan
        // pernah melihat hasilnya.
        $leaderboard = null;
        if ($canViewPerformance) {
            $leaderboard = $this->buildPerformanceLeaderboard($selectedPopId, $dateFrom, $dateTo);
        }

        // 13. Tren bulanan komplain pelanggan + performa per POP, SEMUA POP
        // dalam scope user — SENGAJA independen dari filter periode/pop_id
        // dashboard di atas: tujuannya justru membandingkan POP satu sama
        // lain buat cari daerah paling sering komplain, milih satu POP di
        // filter atas malah mematikan tujuan itu. Cache TERPISAH dari tuple
        // filter di atas (kuncinya cuma per-user, granularitas bulanan —
        // gak perlu ikut refresh 60 detik punya stat lain).
        //
        // Digabung SATU cache entry (bukan dua) — dua-duanya butuh koleksi
        // tiket 12 bulan yang SAMA persis (`loadComplaintWindow()`), jadi
        // gabung query-nya sekali di sini lebih murah daripada dua
        // Cache::remember terpisah yang masing-masing query ulang.
        [$monthlyComplaintTrend, $perPopAnalytics] = Cache::remember(
            sprintf('dashboard:noc:monthly-complaint:%d', auth()->id()),
            300,
            function () {
                $window = $this->loadComplaintWindow();

                return [
                    $this->buildMonthlyComplaintTrend($window),
                    $this->buildPerPopAnalytics($window),
                ];
            }
        );

        $userPops = Pop::forUser(auth()->user())->get(['id', 'name', 'code']);

        return view('noc.dashboard', [
            'stats' => $stats,
            'monthlyComplaintTrend' => $monthlyComplaintTrend,
            'perPopAnalytics' => $perPopAnalytics,
            'deltaStats' => $deltaStats,
            'dailyTrend' => $dailyTrend,
            'slaCompliance' => $slaCompliance,
            'agingBuckets' => $agingBuckets,
            'leaderboard' => $leaderboard,
            'canViewPerformance' => $canViewPerformance,
            'activeTickets' => $activeTickets,
            'activityFeed' => $activityFeed,
            'issueStats' => $issueStats,
            'regionStats' => $regionStats,
            'trendMatrix' => $trendMatrix,
            'allowedPops' => $userPops,
            'allowedPopIds' => $userPops->pluck('id'),
            'filters' => [
                'date_preset' => $datePreset,
                'pop_id' => $selectedPopId,
                'date_from' => $dateFrom ? $dateFrom->toDateString() : '',
                'date_to' => $dateTo ? $dateTo->toDateString() : '',
            ],
        ]);
    }

    /**
     * Delta % vs periode sebelumnya (period-over-period) buat 4 stat card
     * utama. Window pembanding sepanjang window terpilih, langsung
     * berhimpit sebelum `$dateFrom` (mis. filter "7 Hari Terakhir" →
     * dibanding 7 hari sebelum itu). null seluruh array kalau filter
     * "Semua Waktu" (`$dateFrom` null) — tidak ada window valid buat
     * dibandingkan.
     *
     * @return array{total_ticket: ?float, ticket_selesai: ?float, ticket_assign_fop: ?float, ticket_dibatalkan: ?float}|null
     */
    private function buildDeltaStats(Builder $baseQuery, ?Carbon $dateFrom, ?Carbon $dateTo, array $currentStats): ?array
    {
        if (! $dateFrom || ! $dateTo) {
            return null;
        }

        $periodSeconds = $dateFrom->diffInSeconds($dateTo);
        $prevTo = $dateFrom->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subSeconds($periodSeconds);

        $prevQuery = (clone $baseQuery)->whereBetween('created_at', [$prevFrom, $prevTo]);

        $prevTotal = (clone $prevQuery)->count();
        $prevSelesai = (clone $prevQuery)->where('status', TicketHandlingStatus::CLOSED->value)->count();
        $prevAssignFop = (clone $prevQuery)->where('handler', TicketHandler::FOP->value)->count();
        $prevDibatalkan = (clone $prevQuery)->where('status', TicketHandlingStatus::CANCELLED->value)->count();

        $pct = fn (int $current, int $prev) => match (true) {
            $prev > 0 => round((($current - $prev) / $prev) * 100, 1),
            $current > 0 => 100.0,
            default => 0.0,
        };

        return [
            'total_ticket' => $pct($currentStats['total_ticket'], $prevTotal),
            'ticket_selesai' => $pct($currentStats['ticket_selesai'], $prevSelesai),
            'ticket_assign_fop' => $pct($currentStats['ticket_assign_fop'], $prevAssignFop),
            'ticket_dibatalkan' => $pct($currentStats['ticket_dibatalkan'], $prevDibatalkan),
        ];
    }

    /**
     * Tren harian volume tiket masuk/selesai/dibatalkan (line chart).
     *
     * "Masuk" dihitung dari `created_at`; "selesai"/"dibatalkan" dari
     * `updated_at` + filter status — konvensi yang sama dengan
     * `selesai_hari_ini`/`dibatalkan_hari_ini` di atas.
     *
     * Rentang dibatasi 60 hari terakhir buat cegah chart meledak di filter
     * "Semua Waktu"/rentang custom panjang — kalau window terpilih lebih
     * pendek dari itu, dipakai apa adanya; kalau null (all_time) atau lebih
     * panjang dari 60 hari, dipotong ke 60 hari terakhir dari `$dateTo`
     * (atau hari ini kalau `$dateTo` juga null).
     *
     * @return array{labels: string[], masuk: int[], selesai: int[], dibatalkan: int[]}
     */
    private function buildDailyTrend(Builder $baseQuery, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $end = ($dateTo ?? now())->copy()->endOfDay();
        $start = $dateFrom ? $dateFrom->copy()->startOfDay() : $end->copy()->subDays(59)->startOfDay();

        if ($start->diffInDays($end) > 59) {
            $start = $end->copy()->subDays(59)->startOfDay();
        }

        $masukRows = (clone $baseQuery)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $selesaiRows = (clone $baseQuery)
            ->where('status', TicketHandlingStatus::CLOSED->value)
            ->whereBetween('updated_at', [$start, $end])
            ->selectRaw('DATE(updated_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $dibatalkanRows = (clone $baseQuery)
            ->where('status', TicketHandlingStatus::CANCELLED->value)
            ->whereBetween('updated_at', [$start, $end])
            ->selectRaw('DATE(updated_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $labels = [];
        $masuk = [];
        $selesai = [];
        $dibatalkan = [];

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $masuk[] = (int) ($masukRows[$key] ?? 0);
            $selesai[] = (int) ($selesaiRows[$key] ?? 0);
            $dibatalkan[] = (int) ($dibatalkanRows[$key] ?? 0);
            $cursor->addDay();
        }

        return compact('labels', 'masuk', 'selesai', 'dibatalkan');
    }

    /**
     * SLA compliance (donut chart) — on-time vs breach, dari tiket RESOLVED
     * dalam periode (`resolved_at` terisi) yang punya `sla_deadline_at`
     * (tiket lama sebelum kolom SLA ada tidak dihitung — `null`, bukan
     * "on-time palsu"). Tiket yang masih berjalan tidak dihitung — belum
     * final, menghakiminya sebelum `resolved_at` bikin angka menyesatkan.
     *
     * @return array{ontime: int, breach: int, ontime_pct: float, total: int}
     */
    private function buildSlaCompliance(Builder $filteredQuery): array
    {
        $resolved = (clone $filteredQuery)
            ->whereNotNull('resolved_at')
            ->whereNotNull('sla_deadline_at')
            ->get(['resolved_at', 'sla_deadline_at']);

        $total = $resolved->count();
        $breach = $resolved->filter(fn (Ticket $t) => $t->resolved_at->greaterThan($t->sla_deadline_at))->count();
        $ontime = $total - $breach;

        return [
            'ontime' => $ontime,
            'breach' => $breach,
            'ontime_pct' => $total > 0 ? round(($ontime / $total) * 100, 1) : 0,
            'total' => $total,
        ];
    }

    /**
     * Distribusi aging tiket aktif NOC (bar chart) — 0-8 jam / 8-24 jam /
     * >24 jam, dihitung dari SELURUH antrean `handler=NOC & status=open`
     * (bukan cuma 30 yang tampil di list Tiket Aktif).
     *
     * @return array{'0_8': int, '8_24': int, '24_plus': int}
     */
    private function buildAgingBuckets(Builder $baseQuery): array
    {
        $activeQuery = (clone $baseQuery)
            ->where('handler', TicketHandler::NOC->value)
            ->where('status', TicketHandlingStatus::OPEN->value);

        $now = now();
        $eightHoursAgo = $now->copy()->subHours(8);
        $twentyFourHoursAgo = $now->copy()->subHours(24);

        $bucket0_8 = (clone $activeQuery)->where('created_at', '>=', $eightHoursAgo)->count();
        $bucket8_24 = (clone $activeQuery)->where('created_at', '<', $eightHoursAgo)->where('created_at', '>=', $twentyFourHoursAgo)->count();
        $bucket24Plus = (clone $activeQuery)->where('created_at', '<', $twentyFourHoursAgo)->count();

        return [
            '0_8' => $bucket0_8,
            '8_24' => $bucket8_24,
            '24_plus' => $bucket24Plus,
        ];
    }

    /**
     * Leaderboard performa individu Helpdesk & NOC — sumbernya
     * `ticket_histories.actor_id`, BUKAN `tickets.status`. Satu tiket bisa
     * dipegang lebih dari satu aktor sepanjang hidupnya (Helpdesk buat →
     * NOC proses → NOC eskalasi FOP), jadi atribusi aksi wajib per-histori,
     * bukan per-tiket (docs/plan/noc-dashboard-analysis.md §2).
     *
     * Dikelompokkan per ROLE AKTOR SAAT INI (`users.role_id`), bukan
     * `handler` tiket saat aksi terjadi — tujuannya mengukur performa
     * personil, jadi yang relevan siapa dia SEKARANG, bukan tiket itu lagi
     * dipegang siapa waktu itu.
     *
     * @return array{helpdesk: array<int, array>, noc: array<int, array>}
     */
    private function buildPerformanceLeaderboard(?string $selectedPopId, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $historyBase = TicketHistory::query()
            ->whereHas('ticket', function ($q) use ($selectedPopId) {
                $q->applyUserScope();
                if ($selectedPopId) {
                    $q->where('pop_id', $selectedPopId);
                }
            })
            ->whereNotNull('actor_id');

        if ($dateFrom) {
            $historyBase->where('happened_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $historyBase->where('happened_at', '<=', $dateTo);
        }

        $selesaiRows = (clone $historyBase)
            ->where('action', TicketHistoryAction::DISELESAIKAN->value)
            ->with('ticket:id,created_at,resolved_at,sla_deadline_at')
            ->get()
            ->groupBy('actor_id');

        $eskalasiNocRows = (clone $historyBase)
            ->where('action', TicketHistoryAction::DIESKALASI->value)
            ->where('to_status', TicketHandler::NOC->value)
            ->get()
            ->groupBy('actor_id');

        $eskalasiFopRows = (clone $historyBase)
            ->where('action', TicketHistoryAction::DIESKALASI->value)
            ->where('to_status', TicketHandler::FOP->value)
            ->get()
            ->groupBy('actor_id');

        $dikembalikanRows = (clone $historyBase)
            ->where('action', TicketHistoryAction::DIKEMBALIKAN->value)
            ->get()
            ->groupBy('actor_id');

        $actorIds = $selesaiRows->keys()
            ->merge($eskalasiNocRows->keys())
            ->merge($eskalasiFopRows->keys())
            ->merge($dikembalikanRows->keys())
            ->unique();

        if ($actorIds->isEmpty()) {
            return ['helpdesk' => [], 'noc' => []];
        }

        $actors = User::query()
            ->whereIn('id', $actorIds)
            ->with('role:id,code')
            ->get(['id', 'name', 'role_id'])
            ->keyBy('id');

        $helpdesk = [];
        $noc = [];

        foreach ($actorIds as $actorId) {
            $actor = $actors->get($actorId);
            $roleCode = $actor?->role?->code;

            // Cuma dua role operasional yang relevan buat leaderboard ini —
            // aktor dengan role lain (mis. owner/admin iseng menutup tiket
            // lewat wildcard) tidak masuk leaderboard performa Helpdesk/NOC.
            if (! in_array($roleCode, ['helpdesk', 'noc'], true)) {
                continue;
            }

            $selesai = $selesaiRows->get($actorId, collect());
            $avgDurasiMenit = $selesai->isNotEmpty()
                ? (int) round($selesai->avg(fn (TicketHistory $h) => $h->ticket?->resolutionMinutes() ?? 0))
                : null;
            $slaBreachCount = $selesai->filter(fn (TicketHistory $h) => $h->ticket?->isSlaBreached())->count();

            $row = [
                'user_id' => $actorId,
                'name' => $actor->name,
                'jumlah_selesai' => $selesai->count(),
                'jumlah_dikembalikan' => $dikembalikanRows->get($actorId, collect())->count(),
                'avg_durasi_menit' => $avgDurasiMenit,
                'sla_breach_count' => $slaBreachCount,
            ];

            if ($roleCode === 'helpdesk') {
                $row['jumlah_eskalasi_noc'] = $eskalasiNocRows->get($actorId, collect())->count();
                $helpdesk[] = $row;
            } else {
                $row['jumlah_eskalasi_fop'] = $eskalasiFopRows->get($actorId, collect())->count();
                $noc[] = $row;
            }
        }

        usort($helpdesk, fn ($a, $b) => ($b['jumlah_selesai'] + $b['jumlah_eskalasi_noc']) <=> ($a['jumlah_selesai'] + $a['jumlah_eskalasi_noc']));
        usort($noc, fn ($a, $b) => ($b['jumlah_selesai'] + $b['jumlah_eskalasi_fop']) <=> ($a['jumlah_selesai'] + $a['jumlah_eskalasi_fop']));

        return ['helpdesk' => $helpdesk, 'noc' => $noc];
    }

    /**
     * Query SATU-SATUNYA sumber tiket "komplain" trailing 12 bulan, dipakai
     * bareng oleh `buildMonthlyComplaintTrend()` DAN `buildPerPopAnalytics()`
     * — dua-duanya butuh koleksi tiket yang identik (scope, window, tipe),
     * jadi query-nya disatukan di sini biar gak jalan dua kali per request
     * (lihat pemanggilan gabungan satu `Cache::remember` di index()).
     *
     * "Komplain" di sini PROKSI dari `type=MTN` (Maintenance) — Ticketing
     * tidak punya tabel komplain terpisah, dan `C-REQ` (Customer Request)
     * bukan keluhan (permintaan administratif). Konvensi sama dengan
     * `FopAnalyticsController` ("Komplain" = task_type MAINTENANCE) — label
     * UI WAJIB eksplisit "Ticket Maintenance", bukan klaim "Komplain" murni.
     *
     * @return array{tickets: Collection<int, Ticket>, monthKeys: string[], labels: string[]}
     */
    private function loadComplaintWindow(): array
    {
        $end = now()->endOfMonth();
        $start = now()->copy()->subMonths(11)->startOfMonth();

        $tickets = Ticket::query()
            ->applyUserScope()
            ->where('type', TaskType::MAINTENANCE->value)
            ->whereBetween('created_at', [$start, $end])
            ->with(['customer.district:id,name', 'pop:id,name', 'issueCategory:id,name'])
            ->get(['id', 'created_at', 'customer_id', 'pop_id', 'issue_category_id']);

        $labels = [];
        $monthKeys = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $monthKeys[] = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('M Y');
            $cursor->addMonth();
        }

        return compact('tickets', 'monthKeys', 'labels');
    }

    /**
     * Tren bulanan komplain lintas SEMUA POP dalam scope user — trailing 12
     * bulan, SENGAJA tidak terikat filter periode/pop_id dashboard (lihat
     * komentar pemanggilan di index()).
     *
     * Dipecah dua breakdown biar sekali chart jawab dua pertanyaan: daerah
     * mana yang sering komplain (`by_region`) DAN komplainnya soal apa
     * (`by_issue`) — masing-masing top 5 + "Lainnya", per bulan.
     *
     * @param  array{tickets: Collection<int, Ticket>, monthKeys: string[], labels: string[]}  $window  dari `loadComplaintWindow()`
     * @return array{
     *     labels: string[],
     *     total: int,
     *     by_region: array{series: array<string, int[]>, top: string[]},
     *     by_issue: array{series: array<string, int[]>, top: string[]}
     * }
     */
    private function buildMonthlyComplaintTrend(array $window): array
    {
        ['tickets' => $tickets, 'monthKeys' => $monthKeys, 'labels' => $labels] = $window;

        $regionOf = fn (Ticket $t) => $t->customer?->district?->name ?? $t->pop?->name ?? 'Tidak diketahui';
        $issueOf = fn (Ticket $t) => $t->issueCategory?->name ?? 'Lainnya';

        $topRegions = $tickets->groupBy($regionOf)->map->count()->sortDesc()->take(5)->keys()->toArray();
        $topIssues = $tickets->groupBy($issueOf)->map->count()->sortDesc()->take(5)->keys()->toArray();

        return [
            'labels' => $labels,
            'total' => $tickets->count(),
            'by_region' => [
                'series' => $this->buildStackedMonthlySeries($tickets, $monthKeys, $topRegions, $regionOf),
                'top' => array_merge($topRegions, ['Lainnya']),
            ],
            'by_issue' => [
                'series' => $this->buildStackedMonthlySeries($tickets, $monthKeys, $topIssues, $issueOf),
                'top' => array_merge($topIssues, ['Lainnya']),
            ],
        ];
    }

    /**
     * Card performa per POP — Bar (jumlah komplain/bulan) + Line (total
     * pelanggan/bulan, dual-axis) + analisa teks. POP yang gak pernah punya
     * pelanggan sepanjang window (mis. POP demo/gudang) di-SKIP dari hasil,
     * bukan ditampilkan kosong (keputusan produk, bukan asumsi diam-diam).
     *
     * "Total Pelanggan" = kumulatif customer NON-`rejected` yang
     * `registration_date` udah lewat di titik itu DAN belum `terminated_at`
     * di titik itu — proksi customer base aktif per akhir bulan. Bukan
     * snapshot histori presisi (gak ada tabel status-per-tanggal yang
     * reliable dipakai luas, `CustomerStatusLog` scope-nya beda & riskan
     * data legacy bolong), tapi cukup buat lihat ARAH tren pertumbuhan.
     *
     * @param  array{tickets: Collection<int, Ticket>, monthKeys: string[], labels: string[]}  $window  dari `loadComplaintWindow()`
     * @return array<int, array{pop_id: int, pop_name: string, labels: string[], complaints: int[], customers: int[], analysis: string}>
     */
    private function buildPerPopAnalytics(array $window): array
    {
        ['tickets' => $tickets, 'monthKeys' => $monthKeys, 'labels' => $labels] = $window;

        $pops = Pop::forUser(auth()->user())->where('status', 'active')->get(['id', 'name']);
        if ($pops->isEmpty()) {
            return [];
        }

        $customers = Customer::query()
            ->applyUserScope()
            ->whereNotNull('pop_id')
            ->whereIn('pop_id', $pops->pluck('id'))
            ->where('status', '!=', WorkflowTransition::REJECTED->value)
            ->get(['pop_id', 'registration_date', 'terminated_at']);

        $ticketsByPop = $tickets->groupBy('pop_id');
        $customersByPop = $customers->groupBy('pop_id');
        $monthEnds = array_map(fn (string $key) => Carbon::createFromFormat('Y-m', $key)->endOfMonth(), $monthKeys);

        $result = [];

        foreach ($pops as $pop) {
            $popTickets = $ticketsByPop->get($pop->id, collect());
            $popCustomers = $customersByPop->get($pop->id, collect());

            $complaints = array_fill(0, count($monthKeys), 0);
            foreach ($popTickets as $ticket) {
                $monthIndex = array_search($ticket->created_at->format('Y-m'), $monthKeys, true);
                if ($monthIndex !== false) {
                    $complaints[$monthIndex]++;
                }
            }

            $customerCounts = array_map(
                fn (Carbon $monthEnd) => $popCustomers->filter(
                    fn (Customer $c) => $c->registration_date && $c->registration_date->lte($monthEnd)
                        && (! $c->terminated_at || $c->terminated_at->greaterThan($monthEnd))
                )->count(),
                $monthEnds
            );

            // POP yang gak pernah punya pelanggan sepanjang window — skip,
            // bukan tampilkan card kosong (keputusan user).
            if (array_sum($customerCounts) === 0) {
                continue;
            }

            $topIssueName = $popTickets
                ->groupBy(fn (Ticket $t) => $t->issueCategory?->name ?? 'Lainnya')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first();

            $result[] = [
                'pop_id' => $pop->id,
                'pop_name' => $pop->name,
                'labels' => $labels,
                'complaints' => $complaints,
                'customers' => $customerCounts,
                'analysis' => $this->buildPopAnalysisText($complaints, $customerCounts, $topIssueName),
            ];
        }

        return $result;
    }

    /**
     * Insight rule-based (BUKAN AI) dari 2 bulan terakhir tren komplain vs
     * total pelanggan satu POP — delta % komplain, delta % pelanggan, dan
     * rasio komplain-per-pelanggan (indikator paling jujur: pelanggan naik
     * WAJAR komplain ikut naik, yang perlu diwaspadai rasio-nya, bukan
     * angka absolut). null-safe kalau sample kurang dari 2 bulan atau
     * pembagi 0 — jangan pernah render "NaN%"/exception ke user.
     */
    private function buildPopAnalysisText(array $complaints, array $customers, ?string $topIssueName): string
    {
        $months = count($complaints);
        if ($months < 2) {
            return 'Data belum cukup buat analisa tren (minimal 2 bulan data).';
        }

        $lastComplaint = $complaints[$months - 1];
        $prevComplaint = $complaints[$months - 2];
        $lastCustomer = $customers[$months - 1];
        $prevCustomer = $customers[$months - 2];

        $pctDelta = fn (int $current, int $prev) => match (true) {
            $prev > 0 => round((($current - $prev) / $prev) * 100, 1),
            $current > 0 => 100.0,
            default => 0.0,
        };

        $complaintDeltaPct = $pctDelta($lastComplaint, $prevComplaint);
        $customerDeltaPct = $pctDelta($lastCustomer, $prevCustomer);

        $complaintTrendLabel = match (true) {
            $complaintDeltaPct > 0 => "naik {$complaintDeltaPct}%",
            $complaintDeltaPct < 0 => 'turun '.abs($complaintDeltaPct).'%',
            default => 'stabil',
        };
        $customerTrendLabel = match (true) {
            $customerDeltaPct > 0 => "tumbuh {$customerDeltaPct}%",
            $customerDeltaPct < 0 => 'menyusut '.abs($customerDeltaPct).'%',
            default => 'stagnan',
        };

        $sentence1 = "Komplain bulan ini {$complaintTrendLabel} dari bulan lalu ({$prevComplaint} → {$lastComplaint} tiket), pelanggan {$customerTrendLabel} ({$prevCustomer} → {$lastCustomer}).";

        $sentence2 = '';
        $ratioLast = $lastCustomer > 0 ? round(($lastComplaint / $lastCustomer) * 100, 2) : null;
        $ratioPrev = $prevCustomer > 0 ? round(($prevComplaint / $prevCustomer) * 100, 2) : null;

        if ($ratioLast !== null && $ratioPrev !== null) {
            $sentence2 = match (true) {
                $ratioLast > $ratioPrev => " Rasio komplain per pelanggan MEMBURUK ({$ratioPrev}% → {$ratioLast}%) — perlu perhatian.",
                $ratioLast < $ratioPrev => " Rasio komplain per pelanggan membaik ({$ratioPrev}% → {$ratioLast}%).",
                default => " Rasio komplain per pelanggan stabil di {$ratioLast}%.",
            };
        }

        $sentence3 = $topIssueName ? " Isu terbanyak 12 bulan terakhir: {$topIssueName}." : '';

        return $sentence1.$sentence2.$sentence3;
    }

    /**
     * Series bulanan stacked buat satu dimensi breakdown (daerah ATAU
     * issue) — top-N ($topKeys) jadi series sendiri, sisanya digabung
     * "Lainnya". Dipakai dua kali oleh `buildMonthlyComplaintTrend()`
     * dengan classifier beda ($regionOf/$issueOf), biar logic loop bulan +
     * fallback "Lainnya"-nya gak diduplikasi.
     *
     * @param  Collection<int, Ticket>  $tickets
     * @param  string[]  $monthKeys  format 'Y-m', urut kronologis, dari `buildMonthlyComplaintTrend()`
     * @param  string[]  $topKeys  TANPA "Lainnya" — ditambahkan sebagai series fallback di sini
     * @return array<string, int[]>
     */
    private function buildStackedMonthlySeries(Collection $tickets, array $monthKeys, array $topKeys, Closure $classify): array
    {
        $series = [];
        foreach (array_merge($topKeys, ['Lainnya']) as $label) {
            $series[$label] = array_fill(0, count($monthKeys), 0);
        }

        foreach ($tickets as $ticket) {
            $monthIndex = array_search($ticket->created_at->format('Y-m'), $monthKeys, true);
            if ($monthIndex === false) {
                continue;
            }

            $classifiedAs = $classify($ticket);
            $label = in_array($classifiedAs, $topKeys, true) ? $classifiedAs : 'Lainnya';
            $series[$label][$monthIndex]++;
        }

        return $series;
    }
}
