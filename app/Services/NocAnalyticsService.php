<?php

namespace App\Services;

use App\Enums\FopTaskPriority;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Enums\TicketHistoryAction;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Service Analitik dan Operasional NOC Command Center.
 *
 * Mengagregasi metrik untuk:
 * 1. Live Command Center (tiket kritis, SLA at-risk, deteksi outage, beban kerja operator, MTTA live).
 * 2. Deep Analytics & Audit Solving (MTTR remote vs field, durasi per kategori keluhan,
 *    pelanggan kronis/repeat, rasio keluhan per basis pelanggan wilayah, dan ringkasan handover shift).
 */
class NocAnalyticsService
{
    /**
     * Data Telemetri Realtime untuk Tab 1 — Live Command Center.
     *
     * @return array{
     *     urgent_tickets: array<int, array>,
     *     priority_counts: array{urgent: int, high: int, medium: int, low: int},
     *     cluster_alerts: array<int, array>,
     *     operator_workloads: array<int, array>,
     *     mtta_minutes: ?int,
     *     remote_solve_rate_today: float,
     *     open_noc_count: int,
     *     overdue_sla_count: int,
     *     at_risk_sla_count: int
     * }
     */
    public function getLiveCommandCenterData(Builder $baseQuery, ?string $selectedPopId = null): array
    {
        $now = Carbon::now();
        $oneHourLater = $now->copy()->addHour();

        $activeNocQuery = (clone $baseQuery)
            ->where('handler', TicketHandler::NOC->value)
            ->where('status', TicketHandlingStatus::OPEN->value);

        $openNocCount = (clone $activeNocQuery)->count();

        // 1. Hitung Tiket Terbuka berdasarkan Prioritas
        $priorityRaw = (clone $activeNocQuery)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $priorityCounts = [
            'urgent' => (int) ($priorityRaw[FopTaskPriority::URGENT->value] ?? 0),
            'high' => (int) ($priorityRaw[FopTaskPriority::HIGH->value] ?? 0),
            'medium' => (int) ($priorityRaw[FopTaskPriority::MEDIUM->value] ?? 0),
            'low' => (int) ($priorityRaw[FopTaskPriority::LOW->value] ?? 0),
        ];

        // 2. Tiket Kritis & At-Risk SLA (SLA sudah lewat atau sisa waktu < 1 jam, atau prioritas Urgent/High)
        $urgentTicketModels = (clone $activeNocQuery)
            ->where(function ($q) use ($oneHourLater) {
                $q->whereIn('priority', [FopTaskPriority::URGENT->value, FopTaskPriority::HIGH->value])
                    ->orWhere(function ($sub) use ($oneHourLater) {
                        $sub->whereNotNull('sla_deadline_at')
                            ->where('sla_deadline_at', '<=', $oneHourLater);
                    });
            })
            ->with([
                'customer:id,full_name,cid,customer_code,address',
                'creator:id,name',
                'issueCategory:id,name',
                'pop:id,name',
            ])
            ->orderByRaw("
                CASE 
                    WHEN sla_deadline_at IS NOT NULL AND sla_deadline_at < '{$now->toDateTimeString()}' THEN 1
                    WHEN priority = '".FopTaskPriority::URGENT->value."' THEN 2
                    WHEN sla_deadline_at IS NOT NULL AND sla_deadline_at <= '{$oneHourLater->toDateTimeString()}' THEN 3
                    WHEN priority = '".FopTaskPriority::HIGH->value."' THEN 4
                    ELSE 5
                END ASC
            ")
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        $overdueCount = 0;
        $atRiskCount = 0;

        $urgentTickets = $urgentTicketModels->map(function (Ticket $t) use ($now, $oneHourLater, &$overdueCount, &$atRiskCount) {
            $isBreached = $t->sla_deadline_at && $t->sla_deadline_at->lessThan($now);
            $isAtRisk = $t->sla_deadline_at && ! $isBreached && $t->sla_deadline_at->lessThanOrEqualTo($oneHourLater);

            if ($isBreached) {
                $overdueCount++;
            } elseif ($isAtRisk) {
                $atRiskCount++;
            }

            $remainMinutes = $t->sla_deadline_at ? (int) $now->diffInMinutes($t->sla_deadline_at, false) : null;

            return [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'customer_name' => $t->customer?->full_name ?? $t->customer_name ?? '—',
                'customer_cid' => $t->customer?->cid ?? $t->customer?->customer_code,
                'issue' => $t->issueCategory?->name ?? 'Issue',
                'detail' => $t->detail_keluhan,
                'priority' => $t->priority?->value ?? 'normal',
                'pop_name' => $t->pop?->name ?? '—',
                'created_at' => $t->created_at->toDateTimeString(),
                'waiting_human' => $t->created_at->diffForHumans(null, true),
                'sla_deadline_at' => $t->sla_deadline_at?->toDateTimeString(),
                'is_breached' => $isBreached,
                'is_at_risk' => $isAtRisk,
                'remain_minutes' => $remainMinutes,
                'remain_label' => $this->formatRemainMinutes($remainMinutes),
            ];
        })->toArray();

        // Total seluruh yang overdue di antrean NOC (bukan hanya yang masuk limit 10)
        $totalOverdueInNoc = (clone $activeNocQuery)
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '<', $now)
            ->count();

        $totalAtRiskInNoc = (clone $activeNocQuery)
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '>=', $now)
            ->where('sla_deadline_at', '<=', $oneHourLater)
            ->count();

        // 3. Deteksi Lonjakan Gangguan Massal (Cluster Outage Alerts)
        // >= 4 tiket berstatus terbuka dibuat dalam 90 menit terakhir di area desa/ODP yang sama
        $clusterAlerts = $this->detectClusterOutages($baseQuery);

        // 4. Audit Beban Kerja Operator (Workload Matrix)
        $operatorWorkloads = $this->buildOperatorWorkloads($baseQuery);

        // 5. Mean Time to Acknowledge (MTTA) 7 hari terakhir
        $mttaMinutes = $this->calculateMttaMinutes($baseQuery);

        // 6. Remote Solve Rate Hari Ini
        $remoteSolveRateToday = $this->calculateRemoteSolveRateToday($baseQuery);

        return [
            'urgent_tickets' => $urgentTickets,
            'priority_counts' => $priorityCounts,
            'cluster_alerts' => $clusterAlerts,
            'operator_workloads' => $operatorWorkloads,
            'mtta_minutes' => $mttaMinutes,
            'remote_solve_rate_today' => $remoteSolveRateToday,
            'open_noc_count' => $openNocCount,
            'overdue_sla_count' => $totalOverdueInNoc,
            'at_risk_sla_count' => $totalAtRiskInNoc,
        ];
    }

    /**
     * Data Analitik Mendalam untuk Tab 2 — Deep Analytics & Audit Solving.
     *
     * @return array{
     *     mtta_minutes: ?int,
     *     mttr_remote_minutes: ?int,
     *     mttr_field_minutes: ?int,
     *     remote_solve_count: int,
     *     field_escalation_count: int,
     *     fcr_rate: float,
     *     mttr_by_category: array<int, array>,
     *     chronic_customers: array<int, array>,
     *     regional_complaint_rates: array<int, array>,
     *     shift_summary: array
     * }
     */
    public function getDeepAnalyticsData(
        Builder $filteredQuery,
        Builder $baseQuery,
        Collection $allowedPops
    ): array {
        // 1. Solving Performance: MTTR Remote vs Field
        $resolvedQuery = (clone $filteredQuery)
            ->where(function ($q) {
                $q->where('status', TicketHandlingStatus::CLOSED->value)
                    ->orWhere('handler', TicketHandler::FOP->value);
            });

        $resolvedTickets = $resolvedQuery
            ->with(['issueCategory:id,name'])
            ->get(['id', 'issue_category_id', 'handler', 'status', 'created_at', 'resolved_at', 'updated_at']);

        $remoteTickets = $resolvedTickets->filter(fn (Ticket $t) => $t->handler !== TicketHandler::FOP->value);
        $fieldTickets = $resolvedTickets->filter(fn (Ticket $t) => $t->handler === TicketHandler::FOP->value);

        $calcAvgMinutes = function (Collection $tickets): ?int {
            if ($tickets->isEmpty()) {
                return null;
            }
            $durations = $tickets->map(function (Ticket $t) {
                $end = $t->resolved_at ?? $t->updated_at;

                return (int) $t->created_at->diffInMinutes($end);
            });

            return (int) round($durations->avg());
        };

        $mttrRemote = $calcAvgMinutes($remoteTickets);
        $mttrField = $calcAvgMinutes($fieldTickets);

        $remoteCount = $remoteTickets->count();
        $fieldCount = $fieldTickets->count();
        $totalResolved = $remoteCount + $fieldCount;
        $fcrRate = $totalResolved > 0 ? round(($remoteCount / $totalResolved) * 100, 1) : 0.0;

        // MTTR Breakdown per Kategori Masalah
        $mttrByCategory = $resolvedTickets
            ->groupBy(fn (Ticket $t) => $t->issueCategory?->name ?? 'Lainnya')
            ->map(function (Collection $tickets, string $name) {
                $durations = $tickets->map(function (Ticket $t) {
                    $end = $t->resolved_at ?? $t->updated_at;

                    return (int) $t->created_at->diffInMinutes($end);
                });

                $avgMin = (int) round($durations->avg());

                return [
                    'name' => $name,
                    'count' => $tickets->count(),
                    'avg_minutes' => $avgMin,
                    'avg_label' => $this->formatDurationMinutes($avgMin),
                ];
            })
            ->sortByDesc('count')
            ->take(8)
            ->values()
            ->toArray();

        // 2. Pelanggan Kronis (Repeat Trouble Customers) 30 hari terakhir
        $chronicCustomers = $this->getChronicCustomers($baseQuery, 30, 2);

        // 3. Rasio Komplain per Basis Pelanggan POP
        $regionalRates = $this->getRegionalComplaintRates($filteredQuery, $allowedPops);

        // 4. Rekapitulasi Handover Shift Hari Ini
        $shiftSummary = $this->getShiftHandoverSummary($baseQuery);

        return [
            'mtta_minutes' => $this->calculateMttaMinutes($filteredQuery),
            'mttr_remote_minutes' => $mttrRemote,
            'mttr_field_minutes' => $mttrField,
            'remote_solve_count' => $remoteCount,
            'field_escalation_count' => $fieldCount,
            'fcr_rate' => $fcrRate,
            'mttr_by_category' => $mttrByCategory,
            'chronic_customers' => $chronicCustomers,
            'regional_complaint_rates' => $regionalRates,
            'shift_summary' => $shiftSummary,
        ];
    }

    /**
     * Deteksi lonjakan insiden pada desa atau ODP yang sama dalam 90 menit terakhir.
     *
     * @return array<int, array{location: string, count: int, latest_time: string, issues: string[]}>
     */
    private function detectClusterOutages(Builder $baseQuery): array
    {
        $windowStart = now()->subMinutes(90);

        $recentTickets = (clone $baseQuery)
            ->where('status', TicketHandlingStatus::OPEN->value)
            ->where('created_at', '>=', $windowStart)
            ->with(['issueCategory:id,name', 'customer.village:id,name', 'pop:id,name'])
            ->get();

        if ($recentTickets->count() < 4) {
            return [];
        }

        // Kelompokkan per lokasi: ODP jika terisi, atau nama desa, atau nama POP
        $grouped = $recentTickets->groupBy(function (Ticket $t) {
            if (! empty($t->customer_odp)) {
                return 'ODP: '.$t->customer_odp;
            }
            if ($t->customer?->village?->name) {
                return 'Desa '.$t->customer->village->name;
            }
            if (! empty($t->customer_village)) {
                return 'Desa '.$t->customer_village;
            }

            return $t->pop?->name ? 'POP '.$t->pop->name : 'Area Umum';
        });

        $clusters = [];
        foreach ($grouped as $location => $tickets) {
            if ($tickets->count() >= 4) {
                $latestTime = $tickets->max('created_at')?->format('H:i') ?? '—';
                $issues = $tickets->pluck('issueCategory.name')
                    ->filter()
                    ->unique()
                    ->take(3)
                    ->values()
                    ->toArray();

                $clusters[] = [
                    'location' => $location,
                    'count' => $tickets->count(),
                    'latest_time' => $latestTime,
                    'issues' => $issues,
                ];
            }
        }

        return $clusters;
    }

    /**
     * Audit Beban Kerja Operator NOC & Helpdesk (Tiket terbuka per individu & durasi).
     *
     * @return array<int, array{user_id: int, name: string, role: string, active_count: int, latest_ticket: ?string, latest_activity: ?string}>
     */
    private function buildOperatorWorkloads(Builder $baseQuery): array
    {
        // Ambil tiket yang saat ini OPEN di handler NOC atau HELPDESK
        $openTickets = (clone $baseQuery)
            ->where('status', TicketHandlingStatus::OPEN->value)
            ->whereIn('handler', [TicketHandler::NOC->value, TicketHandler::HELPDESK->value])
            ->get(['id', 'ticket_number', 'handler', 'created_by', 'created_at', 'updated_at']);

        if ($openTickets->isEmpty()) {
            return [];
        }

        // Cari riwayat interaksi terakhir per tiket untuk mengetahui siapa aktor yang terakhir memegang
        $ticketIds = $openTickets->pluck('id');
        $latestHistories = TicketHistory::query()
            ->whereIn('ticket_id', $ticketIds)
            ->whereNotNull('actor_id')
            ->orderBy('happened_at', 'desc')
            ->get(['ticket_id', 'actor_id', 'action', 'happened_at'])
            ->unique('ticket_id');

        $ticketsWithActor = $openTickets->map(function (Ticket $t) use ($latestHistories) {
            $history = $latestHistories->firstWhere('ticket_id', $t->id);
            $actorId = $history?->actor_id ?? $t->created_by;

            return [
                'ticket_id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'actor_id' => $actorId,
                'happened_at' => $history?->happened_at ?? $t->updated_at,
            ];
        });

        $actorIds = $ticketsWithActor->pluck('actor_id')->filter()->unique();
        if ($actorIds->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $actorIds)
            ->with('role:id,code,name')
            ->get(['id', 'name', 'role_id'])
            ->keyBy('id');

        $workloads = [];
        foreach ($ticketsWithActor->groupBy('actor_id') as $actorId => $items) {
            $user = $users->get($actorId);
            if (! $user) {
                continue;
            }

            $latestItem = $items->sortByDesc('happened_at')->first();

            $workloads[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'role' => strtoupper($user->role?->code ?? 'STAFF'),
                'active_count' => $items->count(),
                'latest_ticket' => $latestItem['ticket_number'] ?? null,
                'latest_activity' => $latestItem['happened_at'] ? Carbon::parse($latestItem['happened_at'])->diffForHumans() : null,
            ];
        }

        usort($workloads, fn ($a, $b) => $b['active_count'] <=> $a['active_count']);

        return array_slice($workloads, 0, 8);
    }

    /**
     * Menghitung Rata-rata Mean Time to Acknowledge (MTTA) dalam menit.
     * Dihitung dari waktu tiket dibuat (created_at) sampai interaksi pertama di ticket_histories.
     */
    private function calculateMttaMinutes(Builder $query): ?int
    {
        $sampleTickets = (clone $query)
            ->where('created_at', '>=', now()->subDays(14))
            ->limit(100)
            ->get(['id', 'created_at']);

        if ($sampleTickets->isEmpty()) {
            return null;
        }

        $ticketIds = $sampleTickets->pluck('id');
        $firstActions = TicketHistory::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where('action', '!=', TicketHistoryAction::DIBUAT->value)
            ->orderBy('happened_at', 'asc')
            ->get(['ticket_id', 'happened_at'])
            ->unique('ticket_id');

        $durations = [];
        foreach ($sampleTickets as $ticket) {
            $firstAction = $firstActions->firstWhere('ticket_id', $ticket->id);
            if ($firstAction) {
                $durations[] = (int) $ticket->created_at->diffInMinutes($firstAction->happened_at);
            }
        }

        if (empty($durations)) {
            return null;
        }

        return (int) round(array_sum($durations) / count($durations));
    }

    /**
     * Hitung Rasio Penyelesaian Remote Hari Ini (% tiket diselesaikan tanpa eskalasi FOP).
     */
    private function calculateRemoteSolveRateToday(Builder $baseQuery): float
    {
        $todayClosed = (clone $baseQuery)
            ->whereDate('updated_at', today())
            ->where('status', TicketHandlingStatus::CLOSED->value)
            ->get(['id', 'handler']);

        $totalToday = $todayClosed->count();
        if ($totalToday === 0) {
            return 100.0;
        }

        $remoteClosed = $todayClosed->filter(fn (Ticket $t) => $t->handler !== TicketHandler::FOP->value)->count();

        return round(($remoteClosed / $totalToday) * 100, 1);
    }

    /**
     * Deteksi Pelanggan Kronis (Repeat Complaints) $\ge N$ keluhan dalam X hari.
     *
     * @return array<int, array{customer_id: int, name: string, cid: ?string, count: int, address: ?string, pop_name: string, top_issue: string, last_ticket: string}>
     */
    public function getChronicCustomers(Builder $baseQuery, int $days = 30, int $minTickets = 2): array
    {
        $since = now()->subDays($days);

        $tickets = (clone $baseQuery)
            ->where('type', TaskType::MAINTENANCE->value)
            ->where('created_at', '>=', $since)
            ->whereNotNull('customer_id')
            ->with([
                'customer:id,full_name,cid,customer_code,address',
                'pop:id,name',
                'issueCategory:id,name',
            ])
            ->get(['id', 'ticket_number', 'customer_id', 'pop_id', 'issue_category_id', 'created_at']);

        if ($tickets->isEmpty()) {
            return [];
        }

        $chronic = [];
        foreach ($tickets->groupBy('customer_id') as $customerId => $custTickets) {
            if ($custTickets->count() >= $minTickets) {
                $firstTicket = $custTickets->first();
                $latestTicket = $custTickets->sortByDesc('created_at')->first();

                $topIssue = $custTickets
                    ->groupBy(fn (Ticket $t) => $t->issueCategory?->name ?? 'Lainnya')
                    ->map->count()
                    ->sortDesc()
                    ->keys()
                    ->first() ?? 'Gangguan';

                $chronic[] = [
                    'customer_id' => $customerId,
                    'name' => $firstTicket->customer?->full_name ?? 'Pelanggan #'.$customerId,
                    'cid' => $firstTicket->customer?->cid ?? $firstTicket->customer?->customer_code,
                    'count' => $custTickets->count(),
                    'address' => $firstTicket->customer?->address,
                    'pop_name' => $firstTicket->pop?->name ?? '—',
                    'top_issue' => $topIssue,
                    'last_ticket' => $latestTicket->ticket_number,
                ];
            }
        }

        usort($chronic, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($chronic, 0, 8);
    }

    /**
     * Rasio Komplain terhadap Basis Pelanggan per POP (% Insiden Kualitas Jaringan).
     *
     * @return array<int, array{pop_id: int, pop_name: string, complaints: int, customers: int, rate_pct: float}>
     */
    public function getRegionalComplaintRates(Builder $filteredQuery, Collection $allowedPops): array
    {
        if ($allowedPops->isEmpty()) {
            return [];
        }

        $popIds = $allowedPops->pluck('id')->toArray();

        // 1. Hitung total komplain di periode terpilih per POP
        $complaintsByPop = (clone $filteredQuery)
            ->whereIn('pop_id', $popIds)
            ->selectRaw('pop_id, COUNT(*) as total')
            ->groupBy('pop_id')
            ->pluck('total', 'pop_id')
            ->toArray();

        // 2. Hitung total pelanggan aktif saat ini per POP
        $customersByPop = Customer::query()
            ->applyUserScope()
            ->whereIn('pop_id', $popIds)
            ->where('status', '!=', 'rejected')
            ->selectRaw('pop_id, COUNT(*) as total')
            ->groupBy('pop_id')
            ->pluck('total', 'pop_id')
            ->toArray();

        $rates = [];
        foreach ($allowedPops as $pop) {
            $complaints = (int) ($complaintsByPop[$pop->id] ?? 0);
            $customers = (int) ($customersByPop[$pop->id] ?? 0);
            $ratePct = $customers > 0 ? round(($complaints / $customers) * 100, 2) : 0.0;

            $rates[] = [
                'pop_id' => $pop->id,
                'pop_name' => $pop->name,
                'complaints' => $complaints,
                'customers' => $customers,
                'rate_pct' => $ratePct,
            ];
        }

        // Urutkan berdasarkan complaint rate tertinggi (wilayah paling berisiko di atas)
        usort($rates, fn ($a, $b) => $b['rate_pct'] <=> $a['rate_pct'] ?: $b['complaints'] <=> $a['complaints']);

        return $rates;
    }

    /**
     * Ringkasan Handover Shift Hari Ini (Laporan pergantian piket NOC).
     *
     * @return array{
     *     masuk_hari_ini: int,
     *     selesai_hari_ini: int,
     *     eskalasi_fop_hari_ini: int,
     *     sisa_antrean_noc: int,
     *     breach_hari_ini: int,
     *     top_issue_today: string
     * }
     */
    public function getShiftHandoverSummary(Builder $baseQuery): array
    {
        $today = today();

        $masukHariIni = (clone $baseQuery)->whereDate('created_at', $today)->count();
        $selesaiHariIni = (clone $baseQuery)->whereDate('updated_at', $today)->where('status', TicketHandlingStatus::CLOSED->value)->count();
        $eskalasiFopHariIni = (clone $baseQuery)->whereDate('updated_at', $today)->where('handler', TicketHandler::FOP->value)->count();
        $sisaAntreanNoc = (clone $baseQuery)->where('handler', TicketHandler::NOC->value)->where('status', TicketHandlingStatus::OPEN->value)->count();

        $breachHariIni = (clone $baseQuery)
            ->whereDate('created_at', $today)
            ->whereNotNull('sla_deadline_at')
            ->where('sla_deadline_at', '<', now())
            ->count();

        $topIssue = (clone $baseQuery)
            ->whereDate('created_at', $today)
            ->with('issueCategory:id,name')
            ->get()
            ->groupBy(fn (Ticket $t) => $t->issueCategory?->name ?? 'Lainnya')
            ->map->count()
            ->sortDesc()
            ->keys()
            ->first() ?? 'Tidak ada issue';

        return [
            'masuk_hari_ini' => $masukHariIni,
            'selesai_hari_ini' => $selesaiHariIni,
            'eskalasi_fop_hari_ini' => $eskalasiFopHariIni,
            'sisa_antrean_noc' => $sisaAntreanNoc,
            'breach_hari_ini' => $breachHariIni,
            'top_issue_today' => $topIssue,
        ];
    }

    private function formatRemainMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        if ($minutes < 0) {
            $abs = abs($minutes);
            $h = intdiv($abs, 60);
            $m = $abs % 60;

            return $h > 0 ? "Lewat {$h}j {$m}m" : "Lewat {$m}m";
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? "Sisa {$h}j {$m}m" : "Sisa {$m}m";
    }

    private function formatDurationMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        if ($minutes < 60) {
            return "{$minutes} mnt";
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? "{$h}j {$m}m" : "{$h} jam";
    }
}
