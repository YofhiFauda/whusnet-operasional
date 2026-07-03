<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\TaskService;
use App\Services\CustomerWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FopDashboardController extends Controller
{
    public function __construct(
        private readonly EffectiveAccessService $accessService,
        private readonly TaskService $taskService
    ) {}

    /**
     * FOP Dashboard utama — kanban pipeline + antrean survey + tabel teknisi.
     * Guard: task.view.all
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAll', Task::class);

        $user          = auth()->user();
        $allowedPopIds = $this->accessService->getAllowedPopIds($user);
        $today         = Carbon::today();

        // ── Kanban: task berdasarkan status ─────────────────────────
        $pipelineStatuses = [
            TaskStatus::TERJADWAL->value,
            TaskStatus::IN_PROGRESS->value,
            TaskStatus::SELESAI->value,
        ];

        $pipelineTasks = Task::with(['customer', 'pop', 'teamMembers.user', 'checklists', 'evidences'])
            ->applyUserScope($user)
            ->whereIn('status', $pipelineStatuses)
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $pendingTasks = Task::with(['customer', 'pop', 'checklists'])
            ->applyUserScope($user)
            ->where('status', TaskStatus::PENDING->value)
            ->orderBy('created_at')
            ->get();

        $kanban = [
            'antrean'     => $pendingTasks,
            'terjadwal'   => $pipelineTasks->where('status.value', TaskStatus::TERJADWAL->value)->values(),
            'berjalan'    => $pipelineTasks->where('status.value', TaskStatus::IN_PROGRESS->value)->values(),
            'selesai'     => $pipelineTasks->where('status.value', TaskStatus::SELESAI->value)->values(),
        ];

        // ── Kolom ke-4: Perlu Aksi FOP ──────────────────────────────
        // Customer status 'waiting_acc' atau 'surveyed' + ambil completed_at task survey untuk countdown Verifikasi 3×24 jam.
        // Query ringan: satu query + eager load task survey selesai.
        $perluAksiFop = Customer::with([
            'pop',
            'latestSurvey',
            // eager load task survey yang paling terakhir selesai
            'tasks' => function ($q) {
                $q->where('task_type', TaskType::SURVEY->value)
                  ->where('status', TaskStatus::SELESAI->value)
                  ->orderByDesc('completed_at')
                  ->limit(1);
            },
            'tasks.teamMembers.user',
        ])
        ->when(!empty($allowedPopIds), fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
        ->whereIn('status', ['waiting_acc', 'surveyed'])
        ->orderBy('updated_at', 'asc') // terlama di atas — paling prioritas
        ->get()
        ->map(function (Customer $c) {
            // completed_at dari task survey terakhir — sumber countdown Verifikasi
            $surveyCompletedAt = $c->tasks->first()?->completed_at;
            $verifDeadline     = $surveyCompletedAt
                ? Carbon::parse($surveyCompletedAt)->addDays(3)
                : null;
            $verifRemain       = $verifDeadline
                ? -$verifDeadline->diffInSeconds(Carbon::now(), false)
                : null;

            $latestSurvey = $c->latestSurvey;
            $surveyNote = $latestSurvey?->survey_note ?? '';
            $mediaType = '—';
            if (preg_match('/Media:\s*(Fiber|Wireless|UTP)/i', $surveyNote, $matches)) {
                $mediaType = $matches[1];
            }

            $surveyStatus = $latestSurvey?->survey_status;
            $resultLabel = match ($surveyStatus) {
                'completed' => 'Layak',
                'failed'    => 'Tidak Layak',
                'pending'   => 'Kunjungan Ulang',
                default     => '—'
            };

            return [
                'id'               => $c->id,
                'name'             => $c->full_name,
                'cid'              => $c->display_id ?? $c->customer_code,
                'pop_name'         => $c->pop?->name ?? '—',
                // Countdown Verifikasi 3×24 jam
                'verif_deadline_iso' => $verifDeadline?->toIso8601String(),
                'verif_remain_seconds' => $verifRemain,
                'verif_total_seconds'  => 259200, // 3×24×60×60
                'verif_is_late'    => $verifRemain !== null && $verifRemain < 0,
                // Data survey untuk slide-over "Proses ke TIM" (S8.2-T006)
                'survey_completed_at' => $surveyCompletedAt?->format('d M Y H:i'),
                'survey_result'       => $resultLabel,
                'survey_distance'     => $latestSurvey?->cable_estimation_meter ? $latestSurvey->cable_estimation_meter . 'm' : '—',
                'survey_media'        => $mediaType,
                'survey_odp'          => $latestSurvey?->nearest_odp ?? '—',
                'survey_notes'        => $surveyNote,
            ];
        });

        $kanban['perlu_aksi_fop'] = $perluAksiFop;

        // ── Antrean survey: pelanggan yang belum disurvey ───────────
        // Countdown Survey: (customers.created_at + 1 hari) - sekarang
        $surveyQueue = Customer::with(['pop'])
            ->when(!empty($allowedPopIds), fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
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
        $overdueSurvey = Customer::when(!empty($allowedPopIds), fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
            ->whereRaw('DATE_ADD(created_at, INTERVAL 1 DAY) < NOW()')
            ->count();

        // Overdue Installation: survey completed_at + 3 hari < sekarang (SLA 3×24 jam)
        // Join dengan task survey terbaru yang selesai untuk ambil completed_at
        $overdueInstallation = Customer::when(!empty($allowedPopIds), fn ($q) => $q->whereIn('pop_id', $allowedPopIds))
            ->whereIn('status', ['waiting_installation', 'installation_in_progress', 'verification_admin', 'waiting_acc', 'surveyed'])
            ->whereHas('tasks', function ($q) {
                $q->where('task_type', \App\Enums\TaskType::SURVEY->value)
                  ->where('status', 'selesai')
                  ->whereRaw('DATE_ADD(completed_at, INTERVAL 3 DAY) < NOW()');
            })
            ->count();

        $stats = [
            'antrian_survey'       => $surveyQueue->count(),
            'perlu_aksi_fop'       => $kanban['perlu_aksi_fop']->count(),
            'berjalan'             => $kanban['berjalan']->count(),
            'selesai_hari_ini'     => $kanban['selesai']->count(),
            'total_hari_ini'       => $pipelineTasks->count(),
            'overdue_survey'       => $overdueSurvey,
            'overdue_installation' => $overdueInstallation,
        ];

        // ── Teknisi di POP yang sama (static, real-time di T009) ────
        $teknisiList = $this->getTeknisiList($user, $allowedPopIds, $today);

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
                    'address'       => $task->customer?->address ?? '—',
                    'status'        => $task->status->label(),
                    'status_color'  => $task->status->value === TaskStatus::IN_PROGRESS->value ? 'warning' : 'info',
                ];
            });

        // ── POPs untuk filter (opsional) ────────────────────────────
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();

        return view('fop.dashboard', compact(
            'kanban',
            'surveyQueue',
            'stats',
            'teknisiList',
            'activeTeams',
            'pops',
        ));
    }

    /**
     * JSON: data pipeline kanban untuk refresh partial.
     */
    public function pipeline(Request $request): JsonResponse
    {
        $this->authorize('viewAll', Task::class);

        $user  = auth()->user();
        $today = Carbon::today();

        $tasks = Task::with(['customer', 'pop', 'teamMembers.user', 'checklists', 'evidences'])
            ->applyUserScope($user)
            ->whereIn('status', [
                TaskStatus::TERJADWAL->value,
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::SELESAI->value,
            ])
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Task $t) => $this->taskToCard($t));

        return response()->json($tasks->groupBy('status'));
    }

    // ─── Private Helpers ─────────────────────────────────────────

    private function getTeknisiList(User $fop, array $allowedPopIds, Carbon $today): \Illuminate\Support\Collection
    {
        $query = User::with(['roleScopes.targets'])
            ->whereHas('role', fn ($q) => $q->where('code', 'teknisi'))
            ->orderBy('name');

        if (!empty($allowedPopIds)) {
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
            $location = $activeTask ? ($activeTask->customer?->address ?? 'Tidak Diketahui') : '-';

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

    private function taskToCard(Task $task): array
    {
        return [
            'id'              => $task->id,
            'task_number'     => $task->task_number,
            'title'           => $task->title,
            'task_type'       => $task->task_type->value,
            'task_type_label' => $task->task_type->label(),
            'card_classes'    => $task->task_type->cardClasses(),
            'status'          => $task->status->value,
            'status_label'    => $task->status->label(),
            'scheduled_at'    => $task->scheduled_at?->toIso8601String(),
            'started_at'      => $task->started_at?->toIso8601String(),
            'completed_at'    => $task->completed_at?->toIso8601String(),
            'sla_minutes'     => $task->sla_minutes,
            'customer_name'   => $task->customer?->full_name ?? '—',
            'customer_cid'    => $task->customer?->display_id ?? '—',
            'pop_name'        => $task->pop?->name ?? '—',
            'team'            => $task->teamMembers->map(fn ($m) => [
                'id'       => $m->user_id,
                'name'     => $m->user?->name ?? '?',
                'initials' => $this->initials($m->user?->name ?? '?'),
            ]),
            'checklist_total' => $task->checklists->count(),
            'checklist_done'  => $task->checklists->where('is_checked', true)->count(),
            'evidence_count'  => $task->evidences->count(),
            'is_over_sla'     => $task->isOverSla(),
        ];
    }

    private function initials(string $name): string
    {
        $words = explode(' ', trim($name));
        return strtoupper(
            implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 2)))
        );
    }
}
