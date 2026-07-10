<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
                    $status = $t->status->value;
                    $statusStyle = 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)';

                    if ($t->task) {
                        $taskStatus = $t->task->status->value;
                        if ($taskStatus === 'selesai') {
                            $status = 'Selesai';
                            $statusStyle = 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)';
                        } elseif ($taskStatus === 'pending') {
                            $status = 'Pending';
                            $statusStyle = 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)';
                        } elseif ($taskStatus === 'dibatalkan') {
                            $status = 'Cancel';
                            $statusStyle = 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)';
                        } elseif ($taskStatus === 'in_progress') {
                            $status = 'In Progress';
                            $statusStyle = 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)';
                        } elseif ($taskStatus === 'terjadwal') {
                            $status = 'Terjadwal';
                            $statusStyle = 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)';
                        }
                    } else {
                        if ($status === 'Selesai') {
                            $statusStyle = 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)';
                        } elseif ($status === 'Pending') {
                            $statusStyle = 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)';
                        } elseif ($status === 'Cancel') {
                            $statusStyle = 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)';
                        }
                    }

                    return [
                        'task_id'      => $t->task_id,
                        'task_number'  => $t->task_number,
                        'tugas'        => $t->tugas,
                        'status'       => $status,
                        'status_style' => $statusStyle,
                        'category_label' => $t->category->value,
                        'badge_classes'  => $t->category->badgeClasses(),
                        'customer_name' => $t->customer?->full_name ?? '—',
                        'customer_address' => $t->customer?->clean_address ?? '—',
                        'technicians'  => $t->technicians->pluck('name')->values(),
                    ];
                })->values();

                $totalTasks     = $mappedTasks->count();
                $completedTasks = $mappedTasks->filter(fn ($t) => $t['status'] === 'Selesai')->count();

                return [
                    'id'               => $team->id,
                    'name'             => $team->name,
                    'work_date'        => $team->work_date->format('d M Y'),
                    'total_tasks'      => $totalTasks,
                    'completed_tasks'  => $completedTasks,
                    'progress_percent' => $totalTasks > 0 ? (int) round($completedTasks / $totalTasks * 100) : 0,
                    'members'          => $team->members->map(fn (User $m) => [
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
