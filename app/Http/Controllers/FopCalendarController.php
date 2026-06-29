<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FopCalendarController extends Controller
{
    public function __construct(
        private readonly EffectiveAccessService $accessService
    ) {}

    /**
     * Calendar view — mingguan task scheduler untuk FOP.
     * Guard: task.view.all
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAll', Task::class);

        $user          = auth()->user();
        $allowedPopIds = $this->accessService->getAllowedPopIds($user);

        // Default: minggu ini
        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->startOfWeek(Carbon::MONDAY)));
        $endDate   = $startDate->copy()->endOfWeek(Carbon::SUNDAY);

        // ── Summary Stats ────────────────────────────────────────────
        $totalTasks  = Task::applyUserScope($user)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->count();

        $completedTasks = Task::applyUserScope($user)
            ->where('status', TaskStatus::SELESAI->value)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->count();

        $pendingTasks = Task::applyUserScope($user)
            ->where('status', TaskStatus::PENDING->value)
            ->count();

        $cancelledTasks = Task::applyUserScope($user)
            ->where('status', TaskStatus::DIBATALKAN->value)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->count();

        $stats = [
            'total'     => $totalTasks,
            'completed' => $completedTasks,
            'pending'   => $pendingTasks,
            'cancelled' => $cancelledTasks,
        ];

        // ── Calendar Days (7 hari: Senin - Minggu) ────────────────────
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayKey = $date->format('Y-m-d');

            $tasks = Task::with(['customer.village', 'customer.district', 'customer.city', 'pop', 'teamMembers.user', 'checklists'])
                ->applyUserScope($user)
                ->whereDate('scheduled_at', $date)
                ->orderBy('scheduled_at')
                ->get();

            $days[$dayKey] = [
                'date'       => $date,
                'dayName'    => $date->translatedFormat('D'), // Sen, Sel, dll
                'dayNum'     => $date->day,
                'tasks'      => $tasks,
                'taskCount'  => $tasks->count(),
            ];
        }

        // ── Build TasksMap untuk UI Interaktif Tanpa Reload ────────────
        $tasksMap = [];
        foreach ($days as $dayData) {
            foreach ($dayData['tasks'] as $task) {
                $checklists = $task->checklists->map(fn($c) => [
                    'id'         => $c->id,
                    'item'       => $c->item,
                    'is_checked' => (bool) $c->is_checked,
                ])->toArray();

                $teamNames = $task->teamMembers->map(fn($m) => $m->user?->name ?? '?')->join(' • ');

                $tasksMap[$task->id] = [
                    'id'                  => $task->id,
                    'task_number'         => $task->task_number,
                    'title'               => $task->title,
                    'task_type'           => $task->task_type->value,
                    'task_type_label'     => $task->task_type->label(),
                    'status'              => $task->status->value,
                    'status_label'        => $task->status->label(),
                    'customer_name'       => $task->customer?->full_name ?? '—',
                    'customer_number'     => $task->customer?->customer_code ?? ($task->task_number ?? '—'),
                    'pop_name'            => $task->pop?->name ?? '—',
                    'location'            => $task->customer?->address ?? '—',
                    'customer_address'    => implode(', ', array_filter([
                        $task->customer?->address,
                        $task->customer?->village?->name,
                        $task->customer?->district?->name,
                        $task->customer?->city?->name,
                    ])) ?: '—',
                    'scheduled_at'        => $task->scheduled_at ? $task->scheduled_at->translatedFormat('l d M • H:i') : '—',
                    'time_only'           => $task->scheduled_at ? $task->scheduled_at->format('H:i') : '—',
                    'team_names'          => $teamNames ?: 'Belum ditugaskan',
                    'checklist_completed' => $task->checklists->where('is_checked', true)->count(),
                    'checklist_total'     => $task->checklists->count(),
                    'checklists'          => $checklists,
                ];
            }
        }

        // ── Auto-Sync Pelanggan waiting_survey & waiting_installation ke Antrean Task ──
        $unscheduledSurveyCustomers = \App\Models\Customer::applyUserScope($user)
            ->whereIn('status', ['waiting_survey', 'calon_pelanggan', 'registered'])
            ->whereDoesntHave('tasks', function ($q) {
                $q->where('task_type', TaskType::SURVEY->value)
                  ->whereIn('status', [TaskStatus::PENDING->value, TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value, 'waiting_survey']);
            })->get();

        foreach ($unscheduledSurveyCustomers as $c) {
            $year = date('Y');
            $count = Task::whereYear('created_at', $year)->count() + 1;
            Task::create([
                'task_number' => sprintf('TASK-%s-%04d', $year, $count),
                'task_type'   => TaskType::SURVEY->value,
                'title'       => 'Survey Pelanggan: ' . $c->full_name,
                'description' => 'Tiket survey untuk alamat: ' . ($c->address ?? '-'),
                'pop_id'      => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'status'      => TaskStatus::PENDING->value,
                'created_by'  => auth()->id() ?? 1,
                'updated_by'  => auth()->id() ?? 1,
            ]);
        }

        $unscheduledInstallCustomers = \App\Models\Customer::applyUserScope($user)
            ->whereIn('status', ['waiting_installation', 'waiting_installations', 'surveyed'])
            ->whereDoesntHave('tasks', function ($q) {
                $q->where('task_type', TaskType::PEMASANGAN->value)
                  ->whereIn('status', [TaskStatus::PENDING->value, TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value, 'waiting_installation', 'waiting_installations']);
            })->get();

        foreach ($unscheduledInstallCustomers as $c) {
            $year = date('Y');
            $count = Task::whereYear('created_at', $year)->count() + 1;
            Task::create([
                'task_number' => sprintf('TASK-%s-%04d', $year, $count),
                'task_type'   => TaskType::PEMASANGAN->value,
                'title'       => 'Pemasangan Baru: ' . $c->full_name,
                'description' => 'Tiket instalasi untuk alamat: ' . ($c->address ?? '-'),
                'pop_id'      => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'status'      => TaskStatus::PENDING->value,
                'created_by'  => auth()->id() ?? 1,
                'updated_by'  => auth()->id() ?? 1,
            ]);
        }

        // ── Fetch Pending Queue, Teknisi List & Default Checklists Map ──
        $pendingQueue = Task::with(['customer', 'pop'])
            ->applyUserScope($user)
            ->where(function ($q) {
                $q->whereIn('status', [
                    TaskStatus::PENDING->value,
                    TaskStatus::DRAFT->value,
                    'waiting_survey',
                    'waiting_installation',
                    'waiting_installations',
                ])
                ->orWhereHas('customer', function ($cq) {
                    $cq->whereIn('status', [
                        'waiting_survey',
                        'waiting_installation',
                        'waiting_installations',
                        'calon_pelanggan',
                        'registered',
                        'surveyed'
                    ]);
                });
            })
            ->whereNotIn('status', [
                TaskStatus::TERJADWAL->value,
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::SELESAI->value,
                TaskStatus::DIBATALKAN->value,
            ])
            ->orderBy('created_at')
            ->get();

        $teknisiQuery = User::with('role')->whereHas('role', fn($q) => $q->where('code', 'teknisi'))->orderBy('name');
        if (!empty($allowedPopIds)) {
            $teknisiQuery->whereHas('roleScopes.targets', fn($q) => $q->whereIn('pop_id', $allowedPopIds));
        }
        $teknisiList = $teknisiQuery->get();

        $defaultChecklistsMap = \App\Models\TaskChecklistTemplate::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('task_type')
            ->map(fn($group) => $group->pluck('item')->toArray())
            ->toArray();

        // ── Tim Aktif (Group tasks by team) ───────────────────────────
        $activeTeams = $this->getTeamsWithTaskCount($user, $allowedPopIds, $startDate, $endDate);

        // ── POPs untuk filter ────────────────────────────────────────
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();

        return view('fop.calendar', compact(
            'stats',
            'days',
            'activeTeams',
            'pops',
            'startDate',
            'endDate',
            'tasksMap',
            'pendingQueue',
            'teknisiList',
            'defaultChecklistsMap'
        ));
    }

    /**
     * Helper: Hitung tim aktif dengan task count dalam periode.
     */
    private function getTeamsWithTaskCount(User $user, array $allowedPopIds, Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        // Get unique users yang punya task dalam periode
        $taskUsers = Task::applyUserScope($user)
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('teamMembers.user')
            ->get()
            ->flatMap(fn ($task) => $task->teamMembers->pluck('user'))
            ->unique('id');

        return $taskUsers->map(function (User $technician) use ($start, $end) {
            $taskCount = Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $technician->id))
                ->whereBetween('scheduled_at', [$start, $end])
                ->count();

            $completedCount = Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $technician->id))
                ->where('status', TaskStatus::SELESAI->value)
                ->whereBetween('scheduled_at', [$start, $end])
                ->count();

            $inProgressTask = Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $technician->id))
                ->where('status', TaskStatus::IN_PROGRESS->value)
                ->latest('started_at')
                ->first();

            // Dapatkan inisial rekan tim yang bekerja sama dalam periode ini
            $coworkers = \App\Models\TaskTeam::whereIn('task_id', function ($query) use ($technician, $start, $end) {
                $query->select('task_id')
                    ->from('task_teams')
                    ->join('tasks', 'tasks.id', '=', 'task_teams.task_id')
                    ->where('task_teams.user_id', $technician->id)
                    ->whereBetween('tasks.scheduled_at', [$start, $end]);
            })->with('user')->get()->pluck('user')->unique('id');

            $avatars = $coworkers->take(3)->map(fn($u) => [
                'name' => $u->name,
                'initials' => $this->initials($u->name)
            ])->values()->toArray();

            if (empty($avatars)) {
                $avatars = [['name' => $technician->name, 'initials' => $this->initials($technician->name)]];
            }

            $extraCount = max(0, $coworkers->count() - 3);

            return [
                'id'             => $technician->id,
                'name'           => $technician->name,
                'initials'       => $this->initials($technician->name),
                'avatars'        => $avatars,
                'extraCount'     => $extraCount,
                'taskCount'      => $taskCount,
                'completedCount' => $completedCount,
                'status'         => $inProgressTask ? 'active' : 'standby',
                'activeTask'     => $inProgressTask,
            ];
        })->sortByDesc('taskCount');
    }

    /**
     * Helper: Extract initials dari nama.
     */
    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->map(fn ($word) => strtoupper($word[0] ?? ''))
            ->join('');
    }
}
