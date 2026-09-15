<?php

namespace Database\Seeders;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskWorkTool;
use App\Models\User;
use App\Models\WorkTool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * DATA CONTOH — mengisi Dashboard Analitik FOP (`/fop/analitik`) dengan
 * volume Task/FopTask yang realistis lintas 12 bulan terakhir, biar tiap
 * section (alat kerja, wilayah, teknisi, backlog, durasi terlama) punya
 * sesuatu buat ditampilkan waktu didemokan/dites manual. TIDAK dipanggil
 * dari `DatabaseSeeder::run()` — jalankan manual:
 *
 *   php artisan db:seed --class=FopAnalyticsDummySeeder
 *
 * Prinsip (sama seperti `NocDashboardDummySeeder`):
 *  - Pelanggan REAL yang sudah ada di DB dipakai apa adanya (bukan bikin
 *    pelanggan palsu) — biar breakdown kecamatan di chart mencerminkan
 *    struktur data sungguhan.
 *  - Task/FopTask/TaskTeam/TaskWorkTool di-insert LANGSUNG lewat model
 *    (bukan `TaskService`) — beda dari Ticket (yang invariant SLA/riwayatnya
 *    berat), di sini gak ada state machine yang perlu dijaga ketat: data
 *    dummy cuma perlu kolom yang dibaca `FopAnalyticsController` konsisten.
 *    Pola construct-nya sama seperti `WorkToolChecklistTest`/`TaskRescheduleTest`.
 *  - `Carbon::setTestNow()` dipakai buat "time travel" `created_at`/`updated_at`
 *    Task/FopTask/TaskWorkTool mundur sampai 12 bulan — kolom lain
 *    (`scheduled_at`/`started_at`/`completed_at`/`cancelled_at`) tetap
 *    di-assign eksplisit karena bukan timestamp otomatis. WAJIB direset di
 *    `finally`.
 *  - `completed_by` SENGAJA dikosongkan untuk task selesai sebelum
 *    2026-08-07 (tanggal kolom ini mulai dilacak, lihat
 *    `FopAnalyticsController::COMPLETED_BY_RELIABLE_SINCE`) — biar badge
 *    peringatan "data historis mungkin belum lengkap" di leaderboard solving
 *    kelihatan gunanya, bukan cuma teoretis.
 */
class FopAnalyticsDummySeeder extends Seeder
{
    private Carbon $originalNow;

    /** @var Collection<int, Customer> */
    private Collection $customers;

    /** @var Collection<int, User> */
    private Collection $technicians;

    /** @var Collection<int, WorkTool> */
    private Collection $workTools;

    private User $actor;

    private int $taskSeq = 0;

    private int $fopTaskSeq = 0;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('FopAnalyticsDummySeeder cuma buat data contoh — JANGAN dijalankan di production.');

            return;
        }

        $this->originalNow = Carbon::now();
        $this->loadPools();

        if ($this->customers->isEmpty()) {
            $this->command?->error('Tidak ada pelanggan dengan pop_id+district_id terisi — jalankan import/CustomerSeeder pelanggan dulu.');

            return;
        }

        if ($this->technicians->count() < 3) {
            $this->command?->error('Kurang dari 3 user role teknisi — jalankan TechnicianSeeder dulu.');

            return;
        }

        $taskCount = 0;

        try {
            // 12 bulan terakhir, bulan berjalan termasuk — rentang default
            // filter "Bulanan" di FopAnalyticsController::resolvePeriod().
            for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
                $monthStart = $this->originalNow->copy()->subMonths($monthsAgo)->startOfMonth();
                $monthEnd = $monthsAgo === 0
                    ? $this->originalNow->copy()
                    : $this->originalNow->copy()->subMonths($monthsAgo)->endOfMonth();

                $tasksThisMonth = random_int(15, 28);

                for ($i = 0; $i < $tasksThisMonth; $i++) {
                    $createdAt = Carbon::createFromTimestamp(
                        random_int($monthStart->timestamp, max($monthStart->timestamp, $monthEnd->timestamp - 1))
                    );

                    $this->seedOneTask($createdAt);
                    $taskCount++;
                }
            }
        } finally {
            // WAJIB — lihat catatan class doc di atas.
            Carbon::setTestNow($this->originalNow);
        }

        // Distribusi acak murni kadang kebetulan gak nyentuh backlog (semua
        // task lahir bulan lampau) atau bikin durasi terlama datar — top-up
        // ini MENJAMIN section 4 & 5 selalu keisi dengan variasi yang jelas
        // kelihatan, dijalankan di waktu ASLI (bukan time-travel di atas).
        $this->topUpBacklog();
        $this->topUpLongestDuration();

        $this->command?->info("✅ FopAnalyticsDummySeeder: {$taskCount} task dummy dibuat lintas 12 bulan terakhir + top-up backlog & durasi terlama.");
    }

    private function loadPools(): void
    {
        $teknisiRoleId = Role::where('code', 'teknisi')->value('id');

        // POP kode 'DEMO-PUSAT' itu POP demo modul Gudang, bukan cabang
        // pelanggan sungguhan — dikecualikan biar chart per-kecamatan gak
        // nyampur data operasional dengan data demo gudang (pola sama
        // `NocDashboardDummySeeder::loadPools()`).
        $realPopIds = Pop::where('code', '!=', 'DEMO-PUSAT')->pluck('id');

        // `->with('pop')` WAJIB — `Customer::display_id` (dipakai di
        // `tugas` FopTask di bawah) lazy-load relasi `pop`, dan
        // `Model::preventLazyLoading()` aktif non-production (meledak
        // `LazyLoadingViolationException` tanpa eager load ini).
        $this->customers = Customer::whereNotNull('pop_id')
            ->whereNotNull('district_id')
            ->whereIn('pop_id', $realPopIds)
            ->with('pop')
            ->inRandomOrder()
            ->limit(300)
            ->get();

        $this->technicians = User::where('role_id', $teknisiRoleId)->get();

        $this->workTools = WorkTool::where('is_active', true)->get();
        if ($this->workTools->isEmpty()) {
            $this->call(WorkToolSeeder::class);
            $this->workTools = WorkTool::where('is_active', true)->get();
        }

        $this->actor = User::whereHas('role', fn ($q) => $q->where('code', 'owner'))->first()
            ?? User::first();

        // Idempotent numbering — kalau seeder ini pernah dijalankan
        // sebelumnya, lanjut dari urutan terakhir biar gak tabrakan unique
        // constraint `task_number`/`task_number` FopTask (bukan reset ke 1).
        $this->taskSeq = (int) Task::where('task_number', 'like', 'TASK-DEMO-%')->count();
        $this->fopTaskSeq = (int) FopTask::where('task_number', 'like', 'TFOP-DEMO-%')->count();
    }

    /**
     * Satu Task lengkap dengan turunannya (FopTask + TaskWorkTool + TaskTeam)
     * — proporsi tipe/status dipilih supaya kelima section dashboard analitik
     * sama-sama kebagian data, bukan cuma satu section dominan.
     */
    private function seedOneTask(Carbon $createdAt): void
    {
        Carbon::setTestNow($createdAt);

        $customer = $this->customers->random();
        $type = $this->weightedTaskType();
        $status = $this->weightedStatus();

        $scheduledAt = $createdAt->copy()->addHours(random_int(2, 72));

        [$startedAt, $completedAt, $cancelledAt, $completedBy] = $this->resolveExecutionWindow($status, $scheduledAt);

        $task = Task::create([
            'task_number' => $this->nextTaskNumber(),
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'task_type' => $type->value,
            'title' => sprintf('[Demo] %s — %s', $type->label(), $customer->full_name),
            'status' => $status->value,
            'scheduled_at' => $scheduledAt,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'completed_by' => $completedBy,
            'cancelled_at' => $cancelledAt,
            'cancel_reason' => $status === TaskStatus::DIBATALKAN ? 'Pelanggan batal (data contoh).' : null,
            'sla_minutes' => $type->slaMinutes(),
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);

        // Beban tugas — 1-3 teknisi per task, unique (task_teams punya unique
        // index task_id+user_id). Semua status ikut (keputusan user: gambaran
        // beban historis lengkap, lihat FopAnalyticsController::buildWorkloadLeaderboard()).
        $teamSize = random_int(1, 3);
        foreach ($this->technicians->random(min($teamSize, $this->technicians->count())) as $index => $tech) {
            $task->teamMembers()->create([
                'user_id' => $tech->id,
                'role_in_task' => $index === 0 ? 'lead' : 'member',
            ]);
        }

        // FopTask turunan — cuma buat tipe yang di produksi memang lewat
        // papan FOP (SURVEY/PSB/MTN), biar ranking alat kerja & wilayah
        // maintenance/pemasangan kebagian data yang menempel ke FopTask asli.
        if (in_array($type, [TaskType::SURVEY, TaskType::PEMASANGAN, TaskType::MAINTENANCE], true)) {
            $fopTask = FopTask::create([
                'task_number' => $this->nextFopTaskNumber(),
                'task_date' => $scheduledAt,
                'category' => $type->value,
                'task_id' => $task->id,
                'tugas' => sprintf('%s_%s', $customer->display_id ?? $customer->customer_code, $customer->full_name),
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'issue' => 'Data contoh — FopAnalyticsDummySeeder.',
                'status' => $status->value,
                'priority' => collect(FopTaskPriority::cases())->random()->value,
            ]);

            // ~65% FopTask kebagian checklist alat — sisanya sengaja kosong,
            // konsisten sama produksi (gak semua task nyatet alat).
            if (random_int(1, 100) <= 65) {
                $this->attachWorkTools($fopTask);
            }
        }
    }

    /**
     * Checklist alat kerja — 1-3 baris, sebagian sengaja `work_tool_id=null`
     * (simulasi alat di luar master / master sudah dihapus) supaya ranking
     * alat kerja teruji GROUP BY `tool_name` snapshot, bukan cuma FK.
     */
    private function attachWorkTools(FopTask $fopTask): void
    {
        $count = random_int(1, 3);
        $picked = $this->workTools->random(min($count, $this->workTools->count()));

        foreach ($picked as $tool) {
            $useSnapshotOnly = random_int(1, 100) <= 15;

            TaskWorkTool::create([
                'fop_task_id' => $fopTask->id,
                'customer_id' => $fopTask->customer_id,
                'work_tool_id' => $useSnapshotOnly ? null : $tool->id,
                'tool_name' => $tool->name,
                'recorded_by' => $this->actor->id,
            ]);
        }
    }

    /**
     * Kolom eksekusi (started_at/completed_at/cancelled_at/completed_by)
     * bergantung status — draft/terjadwal/pending sengaja dibiarkan kosong
     * (itu backlog, belum dieksekusi).
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: ?Carbon, 3: ?int}
     */
    private function resolveExecutionWindow(TaskStatus $status, Carbon $scheduledAt): array
    {
        if ($status === TaskStatus::SELESAI) {
            $startedAt = $scheduledAt->copy()->addMinutes(random_int(0, 30));
            // Durasi bervariasi lebar (15 menit - 6 jam) — outlier ekstra
            // ditambah lewat topUpLongestDuration() biar top-20 kelihatan jelas.
            $completedAt = $startedAt->copy()->addMinutes(random_int(15, 360));
            // completed_by cuma diisi kalau completed_at >= tanggal kolom ini
            // mulai reliable (lihat komentar class doc) — data lebih lama
            // sengaja dikosongkan buat nunjukkin badge peringatan di dashboard.
            $completedBy = $completedAt->greaterThanOrEqualTo(Carbon::parse('2026-08-07'))
                ? $this->technicians->random()->id
                : null;

            return [$startedAt, $completedAt, null, $completedBy];
        }

        if ($status === TaskStatus::DIBATALKAN) {
            return [null, null, $scheduledAt->copy()->addHours(random_int(1, 24)), null];
        }

        if ($status === TaskStatus::IN_PROGRESS) {
            return [$scheduledAt->copy()->addMinutes(random_int(0, 30)), null, null, null];
        }

        // DRAFT/TERJADWAL/PENDING — backlog, belum ada jejak eksekusi.
        return [null, null, null, null];
    }

    /**
     * Backlog dijamin keisi & bervariasi — beberapa lewat SLA (SURVEY
     * terjadwal di masa lalu, lihat `Task::slaDeadline()`: deadline SURVEY
     * = akhir hari jadwal), beberapa masih aman, beberapa draft baru.
     */
    private function topUpBacklog(): void
    {
        $entries = [
            // SURVEY terjadwal yang tanggalnya sudah lewat → over_sla=true.
            ['type' => TaskType::SURVEY, 'status' => TaskStatus::TERJADWAL, 'scheduled_offset_days' => -3],
            ['type' => TaskType::SURVEY, 'status' => TaskStatus::TERJADWAL, 'scheduled_offset_days' => -1],
            // SURVEY terjadwal hari ini/besok → masih dalam SLA.
            ['type' => TaskType::SURVEY, 'status' => TaskStatus::TERJADWAL, 'scheduled_offset_days' => 0],
            ['type' => TaskType::SURVEY, 'status' => TaskStatus::TERJADWAL, 'scheduled_offset_days' => 1],
            // Tipe lain draft/pending — belum ada started_at, jadi SLA "—"
            // (sesuai perilaku produksi, Task::slaDeadline() null tanpa started_at).
            ['type' => TaskType::PEMASANGAN, 'status' => TaskStatus::DRAFT, 'scheduled_offset_days' => 2],
            ['type' => TaskType::MAINTENANCE, 'status' => TaskStatus::PENDING, 'scheduled_offset_days' => 1, 'report_deferred' => true],
            ['type' => TaskType::MAINTENANCE, 'status' => TaskStatus::PENDING, 'scheduled_offset_days' => 1, 'report_deferred' => false],
            ['type' => TaskType::CREQ, 'status' => TaskStatus::TERJADWAL, 'scheduled_offset_days' => 3],
        ];

        foreach ($entries as $entry) {
            $customer = $this->customers->random();
            $createdAt = $this->originalNow->copy()->subHours(random_int(2, 96));
            $this->travelTo($createdAt);

            $task = Task::create([
                'task_number' => $this->nextTaskNumber(),
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'task_type' => $entry['type']->value,
                'title' => sprintf('[Demo Backlog] %s — %s', $entry['type']->label(), $customer->full_name),
                'status' => $entry['status']->value,
                'scheduled_at' => $this->originalNow->copy()->addDays($entry['scheduled_offset_days']),
                'report_deferred' => $entry['report_deferred'] ?? false,
                'sla_minutes' => $entry['type']->slaMinutes(),
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $task->teamMembers()->create([
                'user_id' => $this->technicians->random()->id,
                'role_in_task' => 'lead',
            ]);
        }

        Carbon::setTestNow($this->originalNow);
    }

    /**
     * Beberapa task selesai bulan berjalan dengan durasi ekstrem (6-24 jam)
     * — biar Top 20 Durasi Terlama & baseline rata-rata kelihatan jelas
     * variasinya, bukan cuma cluster rapat di bawah 6 jam dari loop utama.
     */
    private function topUpLongestDuration(): void
    {
        foreach ([360, 480, 720, 900, 1440] as $durationMinutes) {
            $customer = $this->customers->random();
            $createdAt = $this->originalNow->copy()->subDays(random_int(1, 20));
            $this->travelTo($createdAt);

            $startedAt = $createdAt->copy()->addHours(1);
            $completedAt = $startedAt->copy()->addMinutes($durationMinutes);

            $task = Task::create([
                'task_number' => $this->nextTaskNumber(),
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'task_type' => TaskType::MAINTENANCE->value,
                'title' => sprintf('[Demo Durasi Panjang] Maintenance — %s', $customer->full_name),
                'status' => TaskStatus::SELESAI->value,
                'scheduled_at' => $startedAt,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'completed_by' => $this->technicians->random()->id,
                'sla_minutes' => TaskType::MAINTENANCE->slaMinutes(),
                'created_by' => $this->actor->id,
                'updated_by' => $this->actor->id,
            ]);

            $task->teamMembers()->create([
                'user_id' => $this->technicians->random()->id,
                'role_in_task' => 'lead',
            ]);
        }

        Carbon::setTestNow($this->originalNow);
    }

    /**
     * Proporsi tipe task — MTN & PSB dominan (komplain & pemasangan yang
     * jadi fokus utama dashboard analitik), sisanya variasi kecil.
     */
    private function weightedTaskType(): TaskType
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 35 => TaskType::MAINTENANCE,
            $roll <= 60 => TaskType::PEMASANGAN,
            $roll <= 75 => TaskType::SURVEY,
            $roll <= 85 => TaskType::CREQ,
            $roll <= 93 => TaskType::OREQ,
            default => TaskType::INFR,
        };
    }

    /**
     * Proporsi status — mayoritas selesai (biar wilayah/teknisi/durasi
     * keisi), sisanya dibatalkan/backlog secukupnya buat variasi.
     */
    private function weightedStatus(): TaskStatus
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 65 => TaskStatus::SELESAI,
            $roll <= 78 => TaskStatus::DIBATALKAN,
            $roll <= 88 => TaskStatus::TERJADWAL,
            $roll <= 96 => TaskStatus::PENDING,
            default => TaskStatus::DRAFT,
        };
    }

    private function nextTaskNumber(): string
    {
        $this->taskSeq++;

        return sprintf('TASK-DEMO-%05d', $this->taskSeq);
    }

    private function nextFopTaskNumber(): string
    {
        $this->fopTaskSeq++;

        return sprintf('TFOP-DEMO-%05d', $this->fopTaskSeq);
    }

    /**
     * `Carbon::setTestNow()`, tapi diklem supaya gak pernah lewat waktu asli
     * seeder dijalankan (pola sama `NocDashboardDummySeeder::travelTo()`).
     */
    private function travelTo(Carbon $moment): void
    {
        Carbon::setTestNow($moment->greaterThan($this->originalNow) ? $this->originalNow->copy() : $moment);
    }
}
