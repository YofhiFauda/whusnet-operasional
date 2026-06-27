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
            ->where('status', TaskStatus::BATAL->value)
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

            $tasks = Task::with(['customer', 'pop', 'teamMembers.user'])
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

            $inProgressTask = Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $technician->id))
                ->where('status', TaskStatus::IN_PROGRESS->value)
                ->latest('started_at')
                ->first();

            return [
                'id'       => $technician->id,
                'name'     => $technician->name,
                'initials' => $this->initials($technician->name),
                'taskCount' => $taskCount,
                'status'   => $inProgressTask ? 'active' : 'standby',
                'activeTask' => $inProgressTask,
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
