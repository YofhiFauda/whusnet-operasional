<?php

namespace App\Services;

use App\Enums\MaterialKind;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\TaskWorkTool;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FopUnifiedDashboardService
{
    /**
     * Hitung ringkasan funnel onboarding pelanggan lapangan (Pipeline Funnel).
     *
     * @return array<string, int>
     */
    public function getCustomerPipelineSummary(bool $hasAllPopAccess, array $allowedPopIds): array
    {
        $query = Customer::query()
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds));

        $counts = (clone $query)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->whereIn('status', [
                'registered',
                'calon_pelanggan',
                'waiting_survey',
                'waiting_acc',
                'surveyed',
                'waiting_installation',
                'installation_in_progress',
                'verification_admin',
                'active',
            ])
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $registered = (int) ($counts['registered'] ?? 0);
        $waitingSurvey = (int) (($counts['calon_pelanggan'] ?? 0) + ($counts['waiting_survey'] ?? 0));
        $surveyReview = (int) (($counts['waiting_acc'] ?? 0) + ($counts['surveyed'] ?? 0));
        $waitingInstallation = (int) ($counts['waiting_installation'] ?? 0);
        $installationInProgress = (int) ($counts['installation_in_progress'] ?? 0);
        $verificationAdmin = (int) ($counts['verification_admin'] ?? 0);
        $active = (int) ($counts['active'] ?? 0);

        $totalInDelivery = $waitingSurvey + $surveyReview + $waitingInstallation + $installationInProgress + $verificationAdmin;

        return [
            'registered' => $registered,
            'waiting_survey' => $waitingSurvey,
            'survey_review' => $surveyReview,
            'waiting_installation' => $waitingInstallation,
            'installation_in_progress' => $installationInProgress,
            'verification_admin' => $verificationAdmin,
            'active' => $active,
            'total_in_delivery' => $totalInDelivery,
        ];
    }

    /**
     * Kompilasi 6 Hero Metrics untuk zona kartu atas.
     *
     * @param  array<string, mixed>  $stats  Nilai skalar dari FopDashboardController
     * @param  Collection<int, array<string, mixed>>  $teknisiList
     * @return array<string, mixed>
     */
    public function getHeroMetrics(
        User $user,
        bool $hasAllPopAccess,
        array $allowedPopIds,
        Carbon $today,
        array $stats,
        $teknisiList = []
    ): array {
        $totalToday = (int) ($stats['total_hari_ini'] ?? 0);
        $selesaiToday = (int) ($stats['selesai_hari_ini'] ?? 0);
        $berjalanToday = (int) ($stats['berjalan'] ?? 0);

        $progressPercent = $totalToday > 0 ? (int) round(($selesaiToday / $totalToday) * 100) : 0;

        // Antrean overdue (dipakai badge di Pipeline Funnel) — TETAP dihitung
        // dari $stats seperti sebelumnya, cuma TIDAK lagi dipakai buat
        // "SLA Compliance %" di bawah (lihat alasan di sana).
        $overdueSurvey = (int) ($stats['overdue_survey'] ?? 0);
        $overdueInstall = (int) ($stats['overdue_installation'] ?? 0);

        // Tiket Lapangan Terbuka
        $ticketQuery = Ticket::query()
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereNotIn('status', ['closed', 'resolved']);

        $openTicketsCount = (clone $ticketQuery)->count();
        $emergencyTicketsCount = (clone $ticketQuery)
            ->where(function ($q) {
                $q->whereIn('priority', ['urgent', 'emergency', 'high'])
                    ->orWhereHas('issueCategory', fn ($c) => $c->whereIn('name', ['Backbone CUT', 'ODP LOS']));
            })
            ->count();

        // SLA Health Index — dulu rasio buatan yang mencampur 3 angka
        // antrean beda makna (survey queue + perlu aksi + task hari ini) jadi
        // satu pembagi, gak punya arti dunia nyata (ADHOC, 2026-10-12, temuan
        // audit dashboard). Sekarang breach rate SUNGGUHAN: tiket terbuka yang
        // `isSlaBreached()` + task hari ini yang `isOverSla()`, reuse method
        // resmi di model (satu sumber kebenaran, sama yang dipakai badge SLA
        // di tempat lain) — BUKAN query SQL terpisah yang bisa menyimpang.
        $slaTickets = (clone $ticketQuery)->get();
        $breachedTickets = $slaTickets->filter(fn (Ticket $t) => $t->isSlaBreached())->count();

        $todayTasks = Task::applyUserScope($user)
            ->whereIn('status', [
                TaskStatus::TERJADWAL->value,
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::LAPOR_NANTI->value,
                TaskStatus::SELESAI->value,
            ])
            ->whereBetween('scheduled_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->get();
        $breachedTasks = $todayTasks->filter(fn (Task $t) => $t->isOverSla())->count();

        $slaPopulationTotal = $slaTickets->count() + $todayTasks->count();
        $slaBreachTotal = $breachedTickets + $breachedTasks;
        $complianceRate = $slaPopulationTotal > 0
            ? max(0, min(100, (int) round((($slaPopulationTotal - $slaBreachTotal) / $slaPopulationTotal) * 100)))
            : 100;

        // Modem Retur Transit
        $returnedTransitCount = InventorySerial::query()
            ->where('status', SerialStatus::RETURNED->value)
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->count();

        // Armada Teknisi — "Utilization%" dulu ikut hitung teknisi yang CUMA
        // terjadwal (task-nya belum mulai dikerjakan) sebagai "utilized",
        // jadi menggelembungkan angka (ADHOC, 2026-10-12, temuan audit
        // dashboard: 3 aktif + 2 terjadwal dari 10 teknisi tampil "50%
        // utilisasi" padahal cuma 3 yang beneran kerja). Sekarang murni
        // aktif/total — hanya teknisi yang sedang mengerjakan task dihitung.
        $totalTech = count($teknisiList);
        $activeTech = collect($teknisiList)->where('status', 'aktif')->count();
        $scheduledTech = collect($teknisiList)->where('status', 'terjadwal')->count();
        $standbyTech = collect($teknisiList)->where('status', 'standby')->count();
        $techUtilization = $totalTech > 0 ? (int) round(($activeTech / $totalTech) * 100) : 0;

        return [
            'delivery_task' => [
                'total' => $totalToday,
                'selesai' => $selesaiToday,
                'berjalan' => $berjalanToday,
                'pending' => max(0, $totalToday - $selesaiToday - $berjalanToday),
                'progress_percent' => $progressPercent,
            ],
            'pipeline_delivery' => [
                'antrean_survey' => (int) ($stats['antrian_survey'] ?? 0),
                'perlu_aksi_fop' => (int) ($stats['perlu_aksi_fop'] ?? 0),
                'overdue_survey' => $overdueSurvey,
                'overdue_installation' => $overdueInstall,
            ],
            'tickets' => [
                'total_open' => $openTicketsCount,
                'emergency_count' => $emergencyTicketsCount,
            ],
            'sla_health' => [
                'compliance_rate' => $complianceRate,
                'total_overdue' => $slaBreachTotal,
                'breached_tickets' => $breachedTickets,
                'breached_tasks' => $breachedTasks,
                'total_evaluated' => $slaPopulationTotal,
            ],
            'material_transit' => [
                'returned_modems_transit' => $returnedTransitCount,
            ],
            'fleet_tech' => [
                'total' => $totalTech,
                'active' => $activeTech,
                'scheduled' => $scheduledTech,
                'standby' => $standbyTech,
                'utilization_percent' => $techUtilization,
            ],
        ];
    }

    /**
     * Ambil tiket lapangan aktif yang butuh visibilitas FOP (MTN, C-REQ, INFR-REQ).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getActiveFieldTickets(bool $hasAllPopAccess, array $allowedPopIds): Collection
    {
        return Ticket::with(['customer.pop', 'issueCategory', 'fopTask'])
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereNotIn('status', ['closed', 'resolved'])
            ->orderByRaw("CASE 
                WHEN priority IN ('urgent', 'emergency') THEN 1 
                WHEN priority = 'high' THEN 2 
                WHEN priority = 'medium' THEN 3 
                ELSE 4 END")
            ->orderBy('created_at', 'asc')
            ->limit(15)
            ->get()
            ->map(function (Ticket $t) {
                return [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'customer_id' => $t->customer_id,
                    'customer_name' => $t->customer?->full_name ?? '—',
                    'customer_code' => $t->customer?->display_id ?? $t->customer?->customer_code ?? '—',
                    'pop_name' => $t->customer?->pop?->name ?? '—',
                    'issue' => $t->issueCategory?->name ?? $t->subject ?? 'Gangguan Layanan',
                    'priority' => $t->priority->value,
                    'type' => $t->type?->value ?? 'mtn',
                    'type_label' => $t->type?->label() ?? 'Maintenance',
                    'status' => $t->status->value,
                    'has_fop_task' => $t->fop_task_id !== null,
                    'fop_task_id' => $t->fop_task_id,
                    'created_at_human' => $t->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * Ambil telemetri gudang lapangan (modem retur transit, material terpakai hari ini, stok kritis).
     *
     * @return array<string, mixed>
     */
    public function getWarehouseTelemetry(bool $hasAllPopAccess, array $allowedPopIds, Carbon $today): array
    {
        $startOfToday = $today->copy()->startOfDay();
        $endOfToday = $today->copy()->endOfDay();

        // 1. Modem yang dicabut dan transit di tangan teknisi (belum masuk gudang cabang)
        $returnedSerials = InventorySerial::with(['item', 'currentTechnician', 'customer', 'customer.pop'])
            ->where('status', SerialStatus::RETURNED->value)
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('issued_from_pop_id', $allowedPopIds))
            ->orderBy('updated_at', 'desc')
            ->limit(8)
            ->get()
            ->map(fn (InventorySerial $s) => [
                'id' => $s->id,
                'serial_number' => $s->serial_number,
                'item_name' => $s->item?->name ?? 'Modem ONT',
                'technician_name' => $s->currentTechnician?->name ?? 'Teknisi Lapangan',
                'customer_name' => $s->customer?->full_name ?? '—',
                'pop_name' => $s->customer?->pop?->name ?? '—',
                'since_human' => $s->updated_at->diffForHumans(),
            ]);

        // 2. Material kabel & fast connector terpakai hari ini
        $dropCoreUsed = (float) TaskMaterial::where('kind', MaterialKind::TERPAKAI->value)
            ->whereBetween('task_materials.created_at', [$startOfToday, $endOfToday])
            ->whereHas('fopTask', fn ($q) => $q->when(! $hasAllPopAccess, fn ($qq) => $qq->whereIn('pop_id', $allowedPopIds)))
            ->whereHas('item', fn ($q) => $q->where('name', 'like', '%Drop Core%')->orWhere('name', 'like', '%Kabel%'))
            ->sum('qty');

        // 3. Stok kritis ONT/Modem di gudang POP (Modul 5 rancangan) — hanya
        // POP gudang (pusat/cabang, bukan mini_pop, lihat Pop::scopeWarehouse())
        // yang disilangkan scope user, pola sama DashboardController::warehousePopIds().
        $warehousePopIds = Pop::query()
            ->warehouse()
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('id', $allowedPopIds))
            ->pluck('id');

        $criticalStock = $warehousePopIds->isNotEmpty()
            ? InventoryBalance::query()
                ->whereIn('pop_id', $warehousePopIds)
                ->whereHas('item.category', fn ($q) => $q->where('code', 'modem_ont'))
                ->lowStock()
                ->with(['item', 'pop'])
                ->orderByRaw('qty / NULLIF(minimum_stock, 0)')
                ->limit(5)
                ->get()
                ->map(fn (InventoryBalance $b) => [
                    'item_name' => $b->item?->name ?? '—',
                    'pop_name' => $b->pop?->name ?? '—',
                    'qty' => (float) $b->qty,
                    'minimum_stock' => (float) $b->minimum_stock,
                ])
            : collect();

        return [
            'returned_serials_in_transit' => $returnedSerials,
            'drop_core_used_meters' => $dropCoreUsed,
            'returned_count' => $returnedSerials->count(),
            'critical_stock_items' => $criticalStock,
        ];
    }

    /**
     * Rincian pemakaian material & alat kerja HARI INI — jawab "barang apa,
     * dipakai siapa, dipakai pada pelanggan atau infrastruktur" (permintaan
     * eksplisit user, 2026-10-10). Beda dari `getWarehouseTelemetry()` (cuma
     * angka agregat) dan Dashboard Analitik (`FopAnalyticsController`, agregat
     * PERIODE) — ini daftar baris transaksi individual hari ini.
     *
     * `customer_id` NULL pada `task_materials`/`task_work_tools` = task tanpa
     * pelanggan (`TaskType::INFR`, perbaikan infrastruktur ODP/backbone) —
     * itu satu-satunya sumber kebenaran "dipakai pada infrastruktur", BUKAN
     * kolom terpisah (skema gak punya kolom "target" sendiri).
     *
     * Teknisi pelaksana dibaca dari `fopTask.technicians` (siapa YANG
     * MENGERJAKAN), bukan `recorded_by` (siapa yang INPUT laporan — bisa beda
     * orang kalau admin yang entri manual).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getMaterialUsageDetail(bool $hasAllPopAccess, array $allowedPopIds, Carbon $today): Collection
    {
        $startOfToday = $today->copy()->startOfDay();
        $endOfToday = $today->copy()->endOfDay();

        $popScope = fn ($q) => $q->whereHas(
            'fopTask',
            fn ($qq) => $qq->when(! $hasAllPopAccess, fn ($qqq) => $qqq->whereIn('pop_id', $allowedPopIds))
        );

        $materials = TaskMaterial::with(['fopTask.technicians', 'fopTask.customer', 'fopTask.task.creqDetail', 'customer'])
            ->where('kind', MaterialKind::TERPAKAI->value)
            ->whereBetween('created_at', [$startOfToday, $endOfToday])
            ->tap($popScope)
            ->get()
            ->map(fn (TaskMaterial $m) => $this->mapUsageRow(
                $m->created_at, 'Material', $m->item_name.' ('.rtrim(rtrim(number_format((float) $m->qty, 2, ',', '.'), '0'), ',').' '.$m->unit.')',
                $m->fopTask, $m->customer
            ));

        $tools = TaskWorkTool::with(['fopTask.technicians', 'fopTask.customer', 'fopTask.task.creqDetail', 'customer'])
            ->whereBetween('created_at', [$startOfToday, $endOfToday])
            ->tap($popScope)
            ->get()
            ->map(fn (TaskWorkTool $t) => $this->mapUsageRow(
                $t->created_at, 'Alat Kerja', $t->tool_name,
                $t->fopTask, $t->customer
            ));

        return $materials->concat($tools)
            ->sortByDesc('created_at_raw')
            ->take(30)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUsageRow(Carbon $createdAt, string $kind, string $label, ?FopTask $fopTask, ?Customer $customer): array
    {
        $badge = FopTask::resolveUsageBadgeForTask($fopTask);

        return [
            'created_at_raw' => $createdAt,
            'time' => $createdAt->format('H:i'),
            'kind' => $kind,
            'label' => $label,
            'technicians' => $fopTask?->technicians->pluck('name')->values()->all() ?? [],
            'target_type' => $customer !== null ? 'pelanggan' : 'infrastruktur',
            'target_label' => $customer?->full_name ?? ($fopTask?->tugas ?? 'Infrastruktur'),
            'task_number' => $fopTask?->task_number,
            'task_type' => $badge['type'],
            'task_detail' => $badge['detail'],
            'task_category' => $badge['label'],
            'fop_task_id' => $fopTask?->id,
        ];
    }

    /**
     * Kapasitas node ber-port (ODC/ODP/dst) berasal dari port child, terpisah
     * dari label Distribusi CID. Yang dihitung cuma node yang tipenya
     * `has_ports` + `monitors_capacity`; ambang Red Zone per tipe
     * (`red_zone_threshold`, persen 0-100). Nama method & key output
     * (`total_odps`, dst) sengaja dipertahankan — dibaca widget dashboard FOP.
     *
     * @return array<string, mixed>
     */
    public function getOdpSummary(bool $hasAllPopAccess, array $allowedPopIds): array
    {
        $nodes = NetworkNode::query()
            ->whereHas('nodeType', fn ($query) => $query->where('has_ports', true)->where('monitors_capacity', true))
            ->when(! $hasAllPopAccess, fn ($query) => $query->inPops($allowedPopIds))
            ->with('nodeType')
            ->withCount(['ports', 'ports as used_ports_count' => fn ($query) => $query->where('is_used', true)])
            ->get();

        $redZoneNodes = $nodes->filter(fn (NetworkNode $node) => $this->isInRedZone($node));

        $redZone = $redZoneNodes
            ->sortByDesc(fn (NetworkNode $node) => $node->used_ports_count / $node->ports_count)
            ->take(5)
            ->map(fn (NetworkNode $node) => [
                'code' => $node->code,
                'used' => $node->used_ports_count,
                'capacity' => $node->ports_count,
                'percent' => (int) round($node->used_ports_count / $node->ports_count * 100),
            ])
            ->values()
            ->all();

        return [
            'total_odps' => $nodes->count(),
            'red_zone_count' => $redZoneNodes->count(),
            'red_zone' => $redZone,
        ];
    }

    /**
     * Ambang disimpan sebagai PERSEN (80 = 80%), rasio pemakaian pecahan —
     * jangan bandingkan langsung. `used/ports >= threshold/100` dikali silang
     * jadi `used*100 >= threshold*ports`: aritmetika integer murni, tanpa
     * risiko float di tepat ambang (mis. 7/10 vs 70%). Tipe tanpa ambang
     * (null) tidak pernah Red Zone.
     */
    private function isInRedZone(NetworkNode $node): bool
    {
        $threshold = $node->nodeType?->red_zone_threshold;

        if ($threshold === null || $node->ports_count === 0) {
            return false;
        }

        return $node->used_ports_count * 100 >= $threshold * $node->ports_count;
    }

    /**
     * Kumpulkan peringatan darurat real-time untuk ticker berjalan di bagian atas.
     *
     * @return array<int, array<string, string>>
     */
    public function getEmergencyAlerts(bool $hasAllPopAccess, array $allowedPopIds): array
    {
        $alerts = [];

        // 1. Alert Tiket Emergency / Massal
        $emergencyTickets = Ticket::with(['customer', 'issueCategory'])
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereNotIn('status', ['closed', 'resolved'])
            ->where(function ($q) {
                $q->whereIn('priority', ['urgent', 'emergency'])
                    ->orWhereHas('issueCategory', fn ($c) => $c->whereIn('name', ['Backbone CUT', 'ODP LOS']));
            })
            ->limit(3)
            ->get();

        foreach ($emergencyTickets as $t) {
            $alerts[] = [
                'type' => 'emergency',
                'icon' => 'exclamation-triangle',
                'title' => 'Gangguan Kritis: '.$t->ticket_number,
                'message' => ($t->issueCategory?->name ?? $t->subject).' ('.($t->customer?->full_name ?? 'Pelanggan').')',
                'link' => route('tickets.show', $t->id),
            ];
        }

        // 2. Alert Overdue Survey (> 1 hari)
        $overdueSurveyCount = Customer::query()
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['calon_pelanggan', 'waiting_survey'])
            ->where('created_at', '<', now()->subDay())
            ->count();

        if ($overdueSurveyCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'clock',
                'title' => 'SLA Survey Terlampaui',
                'message' => "Ada {$overdueSurveyCount} antrean survey melewati batas 1x24 jam.",
                'link' => route('fop.dashboard').'#antrian-survey-container',
            ];
        }

        return $alerts;
    }
}
