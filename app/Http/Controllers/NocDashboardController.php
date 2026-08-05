<?php

namespace App\Http\Controllers;

use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard NOC — pusat analisa dan pemantauan operasional tiket NOC.
 * Mendukung filter periode (default: 1 bulan berjalan) & POP scope,
 * kalkulasi durasi rata-rata di Ticketing, statistik daerah, issue category,
 * trend daerah x issue category, serta daftar antrean tiket aktif terurut aging.
 */
class NocDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('noc_dashboard.view'), 403);

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

        // Cache 60 detik per user+filter — HANYA stats/issueStats/regionStats/
        // trendMatrix (semuanya array/skalar polos, aman diserialize). Query
        // aslinya ~9 count() + 4x get() terpisah atas filteredQuery yang sama
        // (rata durasi, issue stats, region stats, trend matrix), jadi ini
        // bagian paling berat di halaman ini.
        //
        // $activeTickets & $activityFeed SENGAJA tidak ikut cache — keduanya
        // Eloquent Collection utuh, dan round-trip Collection lewat cache
        // store (file MAUPUN redis) di environment ini korup jadi
        // __PHP_Incomplete_Class (reproduksi independen: Cache::put pada
        // collect([1,2,3]) polos pun korup). Sampai bug itu ditelusuri
        // terpisah, jangan cache Eloquent Collection/Model mentah.
        $cacheKey = sprintf(
            'dashboard:noc:stats:%d:%s:%s:%s:%s',
            auth()->id(),
            $datePreset,
            $selectedPopId ?? '',
            $dateFromInput ?? '',
            $dateToInput ?? ''
        );

        [$stats, $issueStats, $regionStats, $trendMatrix] = Cache::remember(
            $cacheKey,
            60,
            function () use ($filteredQuery, $baseQuery) {
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

                return [
                    $stats,
                    $issueStats->toArray(),
                    $regionStats->toArray(),
                    [
                        'regions' => $topRegions,
                        'issues' => $topIssues,
                        'matrix' => $matrix,
                        'maxCount' => $maxCellCount,
                    ],
                ];
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

        $userPops = Pop::forUser(auth()->user())->get(['id', 'name', 'code']);

        return view('noc.dashboard', [
            'stats' => $stats,
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
}
