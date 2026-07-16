<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FopDashboardController extends Controller
{
    public function __construct(
        private readonly EffectiveAccessService $accessService
    ) {}

    /**
     * FOP Dashboard utama — stat cards + antrean survey + tabel teknisi.
     * Guard: task.view.all
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAll', Task::class);

        $user            = auth()->user();
        $hasAllPopAccess = $this->accessService->hasAllPopAccess($user);
        $allowedPopIds   = $this->accessService->getAllowedPopIds($user);
        $today           = Carbon::today();

        // ── Antrean survey: pelanggan yang belum disurvey ───────────
        // Countdown Survey: (customers.created_at + 1 hari) - sekarang
        $surveyQueue = Customer::with(['pop'])
            ->when(!$hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
            ->orderBy('created_at', 'asc') // terlama di atas — paling prioritas
            ->limit(50)
            ->get()
            ->map(function (Customer $c) {
                $deadline     = Carbon::parse($c->created_at)->addDay();
                $nowTs        = Carbon::now();
                $secondsLeft  = $deadline->diffInSeconds($nowTs, false); // negatif = belum lewat
                $totalSeconds = 86400; // 1×24 jam
                // diffInSeconds dengan false: positif = deadline sudah lewat, negatif = belum
                $remainSeconds = -$secondsLeft; // positif = sisa waktu, negatif = terlambat

                return [
                    'id'             => $c->id,
                    'name'           => $c->full_name,
                    'cid'            => $c->display_id ?? $c->customer_code,
                    'pop_name'       => $c->pop?->name ?? '—',
                    'registered_at'  => $c->created_at->format('d M Y H:i'),
                    'deadline_iso'   => $deadline->toIso8601String(),
                    'remain_seconds' => $remainSeconds,
                    'total_seconds'  => $totalSeconds,
                    'is_late'        => $remainSeconds < 0,
                ];
            });

        // ── Stat cards ──────────────────────────────────────────────

        // Overdue Survey: created_at + 1 hari < sekarang (SLA 1×24 jam)
        $overdueSurvey = Customer::when(!$hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
            ->whereRaw('DATE_ADD(created_at, INTERVAL 1 DAY) < NOW()')
            ->count();

        // Overdue Installation: survey completed_at + 3 hari < sekarang (SLA 3×24 jam)
        // Join dengan task survey terbaru yang selesai untuk ambil completed_at
        $overdueInstallation = Customer::when(!$hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['waiting_installation', 'installation_in_progress', 'verification_admin', 'waiting_acc', 'surveyed'])
            ->whereHas('tasks', function ($q) {
                $q->where('task_type', \App\Enums\TaskType::SURVEY->value)
                  ->where('status', 'selesai')
                  ->whereRaw('DATE_ADD(completed_at, INTERVAL 3 DAY) < NOW()');
            })
            ->count();

        $perluAksiFopCount = Customer::when(!$hasAllPopAccess, fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['waiting_acc', 'surveyed'])
            ->count();

        $todayTaskCounts = Task::applyUserScope($user)
            ->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value, TaskStatus::SELESAI->value])
            ->whereDate('scheduled_at', $today)
            ->get(['status']);

        $stats = [
            'antrian_survey'       => $surveyQueue->count(),
            'perlu_aksi_fop'       => $perluAksiFopCount,
            'berjalan'             => $todayTaskCounts->where('status.value', TaskStatus::IN_PROGRESS->value)->count(),
            'selesai_hari_ini'     => $todayTaskCounts->where('status.value', TaskStatus::SELESAI->value)->count(),
            'total_hari_ini'       => $todayTaskCounts->count(),
            'overdue_survey'       => $overdueSurvey,
            'overdue_installation' => $overdueInstallation,
        ];

        // ── Teknisi di POP yang sama (static, real-time di T009) ────
        $teknisiList = $this->getTeknisiList($hasAllPopAccess, $allowedPopIds, $today);

        // ── Tim Gabungan Aktif Hari Ini ──────────────────────────────
        $activeTeams = Task::with(['customer', 'pop', 'teamMembers.user'])
            ->applyUserScope($user)
            ->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
            ->whereDate('scheduled_at', $today)
            ->has('teamMembers', '>', 1)
            ->orderBy('scheduled_at')
            ->get()
            ->map(function ($task) {
                $teamName = 'Tim Gabungan';
                if (preg_match('/^\[(.*?)\]/', $task->title, $matches)) {
                    $teamName = $matches[1];
                }

                return [
                    'task_id'       => $task->id,
                    'team_name'     => $teamName,
                    'members'       => $task->teamMembers->map(fn($m) => $m->user?->name)->filter()->toArray(),
                    'task_title'    => preg_replace('/^\[.*?\]\s*/', '', $task->title),
                    'task_type'     => $task->task_type->label(),
                    'address'       => $task->customer?->clean_address ?? '—',
                    'status'        => $task->status->label(),
                    'status_color'  => $task->status->value === TaskStatus::IN_PROGRESS->value ? 'warning' : 'info',
                ];
            });

        // ── POPs untuk filter (opsional) ────────────────────────────
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();

        // ── Team FOP Aktif — card per team, list task + footer avatar ────
        $activeFopTeams = FopTaskTeam::with([
            'members',
            'fopTasks.technicians',
            'fopTasks.customer',
            'fopTasks.task'
        ])
            ->get()
            ->filter->isActive()
            ->when(!$hasAllPopAccess, fn ($teams) => $teams->filter(
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

                    $statusStyle = match(true) {
                        $statusValue === 'terjadwal'   => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                        $statusValue === 'in_progress' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                        $statusValue === 'selesai'     => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                        $statusValue === 'dibatalkan'  => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                        $statusValue === 'pending' && $reportDeferred => 'background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd',
                        $statusValue === 'pending'     => 'background:#fefce8; color:#a16207; border-color:#fde68a',
                        default => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border)',
                    };

                    return [
                        'fop_task_id'  => $t->id,
                        'task_id'      => $t->task_id,
                        'task_number'  => $t->task_number,
                        'tugas'        => $t->tugas,
                        'status'       => $status,
                        'status_value' => $statusValue,
                        'status_style' => $statusStyle,
                        'draggable'    => !in_array($statusValue, ['selesai', 'dibatalkan', 'in_progress'], true),
                        'category_label' => $t->category->value,
                        'badge_classes'  => $t->category->badgeClasses(),
                        'customer_name' => $t->customer?->full_name ?? '—',
                        'customer_address' => $t->customer?->clean_address ?? '—',
                        'technicians'  => $t->technicians->pluck('name')->values(),
                    ];
                })->values();

                $totalTasks     = $mappedTasks->count();
                $completedTasks = $mappedTasks->filter(fn ($t) => $t['status_value'] === 'selesai')->count();

                return [
                    'id'               => $team->id,
                    'name'             => $team->name,
                    'work_date'        => $team->work_date->format('d M Y'),
                    'total_tasks'      => $totalTasks,
                    'completed_tasks'  => $completedTasks,
                    'progress_percent' => $totalTasks > 0 ? (int) round($completedTasks / $totalTasks * 100) : 0,
                    'members'          => $team->members->map(fn (User $m) => [
                        'id'       => $m->id,
                        'name'     => $m->name,
                        'initials' => $this->initials($m->name),
                    ])->values(),
                    'tasks'            => $mappedTasks,
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
        if (!$user) {
            abort(401);
        }

        if (!$user->hasRole('owner') && !$user->hasFullAccess() && !$user->hasPermission('fop_tasks.update')) {
            abort(403, 'Anda tidak memiliki hak akses ke modul ini.');
        }

        $validated = $request->validate([
            'to_team_id'         => ['required', 'integer', 'exists:fop_task_teams,id'],
            'technician_ids'     => ['required', 'array', 'min:1'],
            'technician_ids.*'   => ['integer', 'exists:users,id'],
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

        if (!$fopTask->task_date || $toTeam->work_date->toDateString() !== $fopTask->task_date->toDateString()) {
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

    private function getTeknisiList(bool $hasAllPopAccess, array $allowedPopIds, Carbon $today): \Illuminate\Support\Collection
    {
        $query = User::with(['roleScopes.targets'])
            ->whereHas('role', fn ($q) => $q->where('code', 'teknisi'))
            ->orderBy('name');

        if (!$hasAllPopAccess) {
            $query->whereHas('roleScopes.targets', fn ($q) => $q->whereIn('pop_id', $allowedPopIds));
        }

        return $query->get()->map(function (User $teknisi) use ($today) {
            // Task in progress aktif (terlepas dari tanggal, untuk status & lokasi)
            $activeTask = Task::with('customer')
                ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $teknisi->id))
                ->where('status', TaskStatus::IN_PROGRESS->value)
                ->latest('started_at')
                ->first();

            // Hitung task terjadwal hari ini + task terjadwal/in_progress overdue (sebelum hari ini)
            $taskCount = Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $teknisi->id))
                ->where(function ($q) use ($today) {
                    $q->where(function ($q1) use ($today) {
                        $q1->where('status', TaskStatus::TERJADWAL->value)
                           ->whereDate('scheduled_at', $today);
                    })
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereIn('status', [TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
                           ->whereDate('scheduled_at', '<', $today);
                    });
                })
                ->count();

            // Tentukan status: aktif (jika ada task in progress), terjadwal (jika ada scheduled/overdue), standby (jika tidak ada)
            $status = 'standby';
            if ($activeTask) {
                $status = 'aktif';
            } elseif ($taskCount > 0) {
                $status = 'terjadwal';
            }

            // Lokasi terakhir dari alamat customer pada task in progress
            $location = $activeTask ? ($activeTask->customer?->clean_address ?? 'Tidak Diketahui') : '-';

            return [
                'id'          => $teknisi->id,
                'name'        => $teknisi->name,
                'initials'    => $this->initials($teknisi->name),
                'status'      => $status,
                'task_count'  => $taskCount,
                'location'    => $location,
            ];
        });
    }

    private function initials(string $name): string
    {
        $words = explode(' ', trim($name));
        return strtoupper(
            implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 2)))
        );
    }
}
