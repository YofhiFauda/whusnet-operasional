<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\FopTaskTeamService;
use App\Services\TaskService;
use App\Services\TeknisiWorkloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FopDashboardController extends Controller
{
    /**
     * Sejauh mana ke belakang papan menengok untuk tim yang task-nya masih aktif.
     * Bukan tak terhingga: papan FOP alat kerja harian, bukan arsip — tim yang
     * lebih tua dari ini hanya bisa dilihat lewat /fop-tasks.
     */
    private const BOARD_MAX_PAST_DAYS = 30;

    public function __construct(
        private readonly EffectiveAccessService $accessService,
        private readonly TeknisiWorkloadService $teknisiWorkload
    ) {}

    /**
     * FOP Dashboard utama — stat cards + antrean survey + tabel teknisi.
     * Guard: task.view.all
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAll', Task::class);

        $user = auth()->user();
        $hasAllPopAccess = $this->accessService->hasAllPopAccess($user);
        $allowedPopIds = $this->accessService->getAllowedPopIds($user);
        $today = Carbon::today();
        $startOfToday = $today->copy()->startOfDay();
        $endOfToday = $today->copy()->endOfDay();

        // ── Antrean survey: pelanggan yang belum disurvey ───────────
        // Countdown Survey: (customers.created_at + 1 hari) - sekarang
        $surveyQueue = Customer::with(['pop'])
            ->when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
            ->orderBy('created_at', 'asc') // terlama di atas — paling prioritas
            ->limit(50)
            ->get()
            ->map(function (Customer $c) {
                $deadline = Carbon::parse($c->created_at)->addDay();
                $nowTs = Carbon::now();
                $secondsLeft = $deadline->diffInSeconds($nowTs, false); // negatif = belum lewat
                $totalSeconds = 86400; // 1×24 jam
                // diffInSeconds dengan false: positif = deadline sudah lewat, negatif = belum
                $remainSeconds = -$secondsLeft; // positif = sisa waktu, negatif = terlambat

                return [
                    'id' => $c->id,
                    'name' => $c->full_name,
                    'cid' => $c->display_id ?? $c->customer_code,
                    'pop_name' => $c->pop?->name ?? '—',
                    'registered_at' => $c->created_at->format('d M Y H:i'),
                    'deadline_iso' => $deadline->toIso8601String(),
                    'remain_seconds' => $remainSeconds,
                    'total_seconds' => $totalSeconds,
                    'is_late' => $remainSeconds < 0,
                ];
            });

        // ── Stat cards ──────────────────────────────────────────────
        // Cache 30 detik per user+tanggal — hanya angka (count), semuanya
        // skalar dan aman diserialize cache store apa pun. TTL pendek karena
        // papan FOP live (drag&drop, start/complete task langsung ubah angka
        // ini). Query listing di bawah (surveyQueue/teknisiList/activeTeams/
        // activeFopTeams/pops) SENGAJA tidak ikut di-cache — semuanya
        // Eloquent Collection, dan round-trip Collection lewat cache store
        // (file MAUPUN redis) di environment ini korup jadi
        // __PHP_Incomplete_Class (reproduksi independen di luar dashboard
        // ini: Cache::put pada collect([1,2,3]) polos pun korup). Sampai bug
        // itu ditelusuri terpisah, jangan cache Eloquent Collection/Model.
        $statsCacheKey = sprintf('dashboard:fop:stats:%d:%s', $user->id, $today->toDateString());

        $stats = Cache::remember(
            $statsCacheKey,
            30,
            function () use ($hasAllPopAccess, $allowedPopIds, $startOfToday, $endOfToday, $user) {
                $surveyStatuses = ['calon_pelanggan', 'waiting_survey', 'registered'];

                return [
                    'antrian_survey' => Customer::when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
                        ->whereIn('status', $surveyStatuses)
                        ->count(),
                    'perlu_aksi_fop' => Customer::when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
                        ->whereIn('status', ['waiting_acc', 'surveyed'])
                        ->count(),
                    'berjalan' => Task::applyUserScope($user)
                        ->where('status', TaskStatus::IN_PROGRESS->value)
                        ->whereBetween('scheduled_at', [$startOfToday, $endOfToday])
                        ->count(),
                    'selesai_hari_ini' => Task::applyUserScope($user)
                        ->where('status', TaskStatus::SELESAI->value)
                        ->whereBetween('scheduled_at', [$startOfToday, $endOfToday])
                        ->count(),
                    'total_hari_ini' => Task::applyUserScope($user)
                        ->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value, TaskStatus::SELESAI->value])
                        ->whereBetween('scheduled_at', [$startOfToday, $endOfToday])
                        ->count(),
                    // Overdue Survey: created_at + 1 hari < sekarang (SLA 1×24 jam)
                    'overdue_survey' => Customer::when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
                        ->whereIn('status', $surveyStatuses)
                        ->where('created_at', '<', now()->subDay())
                        ->count(),
                    // Overdue Installation: survey completed_at + 3 hari < sekarang (SLA 3×24 jam)
                    'overdue_installation' => Customer::when(! $hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
                        ->whereIn('status', ['waiting_installation', 'installation_in_progress', 'verification_admin', 'waiting_acc', 'surveyed'])
                        ->whereHas('tasks', function ($q) {
                            $q->where('task_type', TaskType::SURVEY->value)
                                ->where('status', TaskStatus::SELESAI->value)
                                ->where('completed_at', '<', now()->subDays(3));
                        })
                        ->count(),
                ];
            }
        );

        // ── Teknisi di POP yang sama (static, real-time di T009) ────
        $teknisiList = $this->teknisiWorkload->summarize($hasAllPopAccess, $allowedPopIds, $today);

        // ── Tim Gabungan Aktif Hari Ini ──────────────────────────────
        // village/district/city ikut karena `clean_address` (dipakai di map di bawah)
        // membaca ketiganya — tanpa eager load ini 3 query per task.
        $activeTeams = Task::with(['customer.village', 'customer.district', 'customer.city', 'pop', 'teamMembers.user'])
            ->applyUserScope($user)
            ->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
            // scheduled_at bertipe timestamp — rentang eksplisit supaya index
            // tanggal bisa dipakai (whereDate() membungkusnya jadi DATE(...)).
            ->whereBetween('scheduled_at', [$startOfToday, $endOfToday])
            ->has('teamMembers', '>', 1)
            ->orderBy('scheduled_at')
            ->get()
            ->map(function ($task) {
                $teamName = 'Tim Gabungan';
                if (preg_match('/^\[(.*?)\]/', $task->title, $matches)) {
                    $teamName = $matches[1];
                }

                return [
                    'task_id' => $task->id,
                    'team_name' => $teamName,
                    'members' => $task->teamMembers->map(fn ($m) => $m->user?->name)->filter()->toArray(),
                    'task_title' => preg_replace('/^\[.*?\]\s*/', '', $task->title),
                    'task_type' => $task->task_type->label(),
                    'address' => $task->customer?->clean_address ?? '—',
                    'status' => $task->status->label(),
                    'status_color' => $task->status->value === TaskStatus::IN_PROGRESS->value ? 'warning' : 'info',
                ];
            });

        // ── POPs untuk filter (opsional) ────────────────────────────
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();

        // ── Team FOP Aktif — card per team, list task + footer avatar ────
        //
        // Sebelumnya SELURUH team yang pernah ada dimuat lengkap dengan anak-anaknya
        // lalu difilter di PHP — FopTaskTeamService::rebuildTeamsForDate() membuat
        // team baru tiap tanggal kerja, jadi setelah setahun operasi itu 300+ team
        // per refresh dashboard. Perbaikan 2026-07-22 memangkasnya jadi "hari ini
        // saja", dan itu kebablasan ke arah sebaliknya: tim yang SUDAH dijadwalkan
        // di /fop-tasks lenyap dari papan begitu ganti hari, padahal task-nya masih
        // hidup dan teknisinya masih melihatnya (TaskController::index() punya
        // cabang overdue, papan ini tidak).
        //
        // Sekarang: hari ini SELALU tampil (termasuk tim yang task-nya baru
        // dijadwalkan), ditambah tim tanggal lampau yang MASIH punya task aktif.
        // Yang membuat versi lama mahal bukan rentang tanggalnya, tapi memuat
        // semua team beserta anaknya — di sini tim lampau yang task-nya sudah
        // selesai/batal disaring di SQL (whereHas), jadi tidak ikut dimuat sama
        // sekali. Ditambah pagar BOARD_MAX_PAST_DAYS supaya papan tidak pelan-pelan
        // berubah jadi arsip.
        $boardFloor = $today->copy()->subDays(self::BOARD_MAX_PAST_DAYS)->startOfDay();

        $activeFopTeams = FopTaskTeam::with([
            'members',
            'fopTasks.technicians',
            'fopTasks.customer.village',
            'fopTasks.customer.district',
            'fopTasks.customer.city',
            'fopTasks.task',
        ])
            // Rentang, bukan `where('work_date', $tanggal)`: MySQL menyimpan
            // work_date sebagai DATE, tapi sqlite (dipakai test) tidak punya tipe
            // DATE dan menyimpannya sebagai '2026-07-22 00:00:00'. Perbandingan
            // sama-dengan jadi tidak cocok di sqlite. Bentuk rentang benar di
            // kedua engine DAN tetap bisa memakai index.
            ->where(function ($q) use ($startOfToday, $endOfToday, $boardFloor) {
                $q->whereBetween('work_date', [$startOfToday, $endOfToday])
                    ->orWhere(function ($lampau) use ($startOfToday, $boardFloor) {
                        $lampau->whereBetween('work_date', [$boardFloor, $startOfToday])
                            ->whereHas('fopTasks', fn ($t) => $t->whereNotIn(
                                'status',
                                [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value]
                            ));
                    });
            })
            // Tanggal terlama di atas — tim yang harinya sudah lewat lebih perlu
            // ditangani lebih dulu daripada yang baru dijadwalkan hari ini.
            ->orderBy('work_date')
            ->get()
            // Filter koleksi yang SUDAH dimuat, bukan `->filter->isActive()`:
            // isActive() memanggil relasi fopTasks() lewat query baru, jadi
            // eager load di atas terbuang dan lahir 1 query per team.
            // Pola ini menyalin FopTaskController:153-156 yang sudah benar.
            ->filter(fn (FopTaskTeam $team) => $team->fopTasks->contains(
                fn (FopTask $t) => ! in_array(
                    $t->status->value,
                    [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value],
                    true
                )
            ))
            ->when(! $hasAllPopAccess, fn ($teams) => $teams->filter(
                fn (FopTaskTeam $team) => $team->fopTasks->contains(
                    fn (FopTask $t) => in_array($t->pop_id, $allowedPopIds)
                )
            ))
            ->map(function (FopTaskTeam $team) {
                $mappedTasks = $team->fopTasks->map(function (FopTask $t) {
                    // FopTask.status share vocab persis TaskStatus (unifikasi 2026-07-20)
                    // — kalau ada Task eksekusi terhubung, pakai itu buat label/style
                    // (bawa nuansa report_deferred "Lapor Nanti"); FopTask standalone
                    // (task_id null, masih 'draft') pakai displayLabel() punya sendiri.
                    $refStatus = $t->task?->status ?? $t->status;
                    $reportDeferred = $t->task?->report_deferred ?? false;
                    $statusValue = $refStatus->value;
                    $status = $refStatus->displayLabel($reportDeferred);

                    $statusStyle = match (true) {
                        $statusValue === 'terjadwal' => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                        $statusValue === 'in_progress' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                        $statusValue === 'selesai' => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                        $statusValue === 'dibatalkan' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                        $statusValue === 'pending' && $reportDeferred => 'background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd',
                        $statusValue === 'pending' => 'background:#fefce8; color:#a16207; border-color:#fde68a',
                        default => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border)',
                    };

                    return [
                        'fop_task_id' => $t->id,
                        'task_id' => $t->task_id,
                        'task_number' => $t->task_number,
                        'tugas' => $t->tugas,
                        'status' => $status,
                        'status_value' => $statusValue,
                        'status_style' => $statusStyle,
                        'draggable' => ! in_array($statusValue, ['selesai', 'dibatalkan', 'in_progress'], true),
                        'category_label' => $t->category->value,
                        'badge_classes' => $t->category->badgeClasses(),
                        'customer_name' => $t->customer?->full_name ?? '—',
                        'customer_address' => $t->customer?->clean_address ?? '—',
                        'technicians' => $t->technicians->pluck('name')->values(),
                    ];
                })->values();

                $totalTasks = $mappedTasks->count();
                $completedTasks = $mappedTasks->filter(fn ($t) => $t['status_value'] === 'selesai')->count();

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'work_date' => $team->work_date->format('d M Y'),
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'progress_percent' => $totalTasks > 0 ? (int) round($completedTasks / $totalTasks * 100) : 0,
                    'members' => $team->members->map(fn (User $m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'initials' => $this->initials($m->name),
                    ])->values(),
                    'tasks' => $mappedTasks,
                ];
            })
            ->values();

        return view('fop.dashboard', compact(
            'surveyQueue',
            'stats',
            'teknisiList',
            'activeTeams',
            'activeFopTeams',
            'pops',
        ));
    }

    /**
     * Switch Task antar Team (drag & drop card di `/fop`) — sesuai kebutuhan
     * poin 3 & SOLUSI poin 3 (docs/fop-task/analisa-auto-team.md, Sprint Backlog Task 3).
     * Wajib pilih teknisi dari roster Team tujuan sebelum commit. Teknisi lama di
     * task dilepas otomatis, teknisi terpilih (bisa >1, seperti multi-select di edit
     * Task FOP) jadi assignee baru di Team tujuan. Pilihan teknisi dibatasi ke roster
     * Team tujuan saja (bukan semua teknisi) — biar gak ada konflik teknisi lain tim.
     */
    public function switchTeam(Request $request, FopTask $fopTask)
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        if (! $user->hasRole('owner') && ! $user->hasFullAccess() && ! $user->hasPermission('fop_tasks.update')) {
            abort(403, 'Anda tidak memiliki hak akses ke modul ini.');
        }

        $validated = $request->validate([
            'to_team_id' => ['required', 'integer', 'exists:fop_task_teams,id'],
            'technician_ids' => ['required', 'array', 'min:1'],
            'technician_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $fopTask->load(['technicians', 'task', 'team']);
        $toTeam = FopTaskTeam::with('members')->findOrFail($validated['to_team_id']);
        $technicianIds = collect($validated['technician_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        if (in_array($fopTask->status, [TaskStatus::SELESAI, TaskStatus::DIBATALKAN], true)) {
            return $this->switchTeamError($request, 'fop_task_id', "Task {$fopTask->task_number} berstatus {$fopTask->status->value}, tidak bisa dipindah team.");
        }

        if ($fopTask->task && $fopTask->task->status->value === TaskStatus::IN_PROGRESS->value) {
            return $this->switchTeamError($request, 'fop_task_id', "Task {$fopTask->task_number} sedang in_progress, tidak bisa dipindah team.");
        }

        if ($fopTask->team_id === $toTeam->id) {
            return $this->switchTeamError($request, 'to_team_id', 'Task sudah berada di Team tersebut.');
        }

        if (! $fopTask->task_date || $toTeam->work_date->toDateString() !== $fopTask->task_date->toDateString()) {
            return $this->switchTeamError($request, 'to_team_id', "Team \"{$toTeam->name}\" bukan untuk tanggal task ini.");
        }

        $teamMemberIds = $toTeam->members->pluck('id');
        $outsideRoster = $technicianIds->diff($teamMemberIds);
        if ($outsideRoster->isNotEmpty()) {
            return $this->switchTeamError($request, 'technician_ids', 'Teknisi yang dipilih harus anggota Team tujuan.');
        }

        $busyOn = Task::where('status', TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $technicianIds))
            ->first();

        if ($busyOn) {
            return $this->switchTeamError(
                $request,
                'technician_ids',
                "Salah satu teknisi terpilih sedang mengerjakan task lain yang in_progress [{$busyOn->task_number}]."
            );
        }

        return DB::transaction(function () use ($fopTask, $toTeam, $technicianIds, $request) {
            $oldValues = $fopTask->toArray();

            $fopTask->team_id = $toTeam->id;
            $fopTask->manual_override_at = now();
            $fopTask->save();

            $fopTask->technicians()->sync($technicianIds->all());

            if ($fopTask->task_id && $fopTask->task) {
                app(TaskService::class)->update($fopTask->task, [
                    'team_member_ids' => $technicianIds->all(),
                ], $request->user());
            }

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'switch_team', $oldValues, $fopTask->fresh(['technicians', 'team'])->toArray());
            }

            app(FopTaskTeamService::class)->rebuildTeamsForDate($fopTask->task_date->copy());

            $toTeam->refresh();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Task {$fopTask->task_number} berhasil dipindah ke \"{$toTeam->name}\".",
                ]);
            }

            return redirect()->route('fop.dashboard')->with('success', "Task berhasil dipindah ke \"{$toTeam->name}\".");
        });
    }

    /**
     * Response error konsisten (JSON atau redirect+errors) buat validasi switchTeam().
     */
    private function switchTeamError(Request $request, string $field, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->withErrors([$field => $message]);
    }

    // ─── Private Helpers ─────────────────────────────────────────

    private function initials(string $name): string
    {
        $words = explode(' ', trim($name));

        return strtoupper(
            implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 2)))
        );
    }
}
