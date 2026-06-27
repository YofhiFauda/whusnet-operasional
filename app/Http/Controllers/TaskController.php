<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistTemplate;
use App\Models\TaskEvidence;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService) {}

    // ─── FOP Dashboard — Kalender ────────────────────────────────

    /**
     * Central List Task View — Menggantikan Kalender FOP.
     * Menampilkan list task dengan fitur filter, sort, dan pagination.
     * Guard: task.view.all
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAll', Task::class);

        $user = auth()->user();

        $query = Task::with(['customer', 'pop', 'teamMembers.user', 'checklists'])
            ->applyUserScope($user);

        // Filter: Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter: Tipe
        if ($request->filled('type')) {
            $query->where('task_type', $request->type);
        }

        // Filter: Range Tanggal
        if ($request->filled('date_start') && $request->filled('date_end')) {
            $query->whereBetween('scheduled_at', [
                Carbon::parse($request->date_start)->startOfDay(),
                Carbon::parse($request->date_end)->endOfDay()
            ]);
        } elseif ($request->filled('date_start')) {
            $query->whereDate('scheduled_at', '>=', $request->date_start);
        } elseif ($request->filled('date_end')) {
            $query->whereDate('scheduled_at', '<=', $request->date_end);
        }

        // Search: Task Number / Customer
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function($qBuilder) use ($q) {
                $qBuilder->where('task_number', 'like', "%{$q}%")
                         ->orWhereHas('customer', function($cq) use ($q) {
                             $cq->where('full_name', 'like', "%{$q}%")
                                ->orWhere('cid', 'like', "%{$q}%");
                         });
            });
        }

        // Sorting
        switch ($request->query('sort', 'date_desc')) {
            case 'status':
                $query->orderBy('status');
                break;
            case 'type':
                $query->orderBy('task_type');
                break;
            case 'date_asc':
                $query->orderBy('scheduled_at', 'asc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('scheduled_at', 'desc');
                break;
        }

        $tasks = $query->paginate(20)->withQueryString();

        $types = TaskType::cases();
        $statuses = TaskStatus::cases();

        return view('tasks.index', compact('tasks', 'types', 'statuses'));
    }

    /**
     * Dashboard Teknisi — hanya task di mana user terdaftar sebagai anggota.
     * Guard: task.view.own
     */
    public function indexOwn(Request $request): View
    {
        $this->authorize('viewOwn', Task::class);

        $user = auth()->user();
        $today = Carbon::today();

        $tasks = Task::with(['customer', 'pop', 'checklists', 'evidences', 'fop'])
            ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id))
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $upcomingTasks = Task::with(['customer', 'pop'])
            ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id))
            ->whereDate('scheduled_at', '>', $today)
            ->whereIn('status', [TaskStatus::TERJADWAL->value])
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        return view('tasks.own', compact('tasks', 'upcomingTasks'));
    }

    /**
     * Partial HTML satu task card — digunakan oleh Echo listener di Teknisi Dashboard.
     * Fetch GET /tasks-saya/partial/{task} → inject card ke DOM tanpa page reload.
     * Guard: task.view.own + teknisi harus terdaftar sebagai member task.
     */
    public function cardPartial(Task $task): View
    {
        $this->authorize('viewOwn', Task::class);

        // Pastikan teknisi yang request memang member dari task ini
        $isMember = $task->teamMembers()
            ->where('user_id', auth()->id())
            ->exists();

        abort_if(!$isMember, 403, 'Anda bukan anggota task ini.');

        $task->load(['customer', 'pop', 'checklists', 'evidences']);

        return view('tasks.partials.own-card', compact('task'));
    }


    /**
     * Form buat task baru.
     * Guard: task.create
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        $user       = auth()->user();
        $pops       = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();
        $teknisiList = $this->getTeknisiForUser($user);
        $types      = TaskType::options();

        // Pre-fill customer jika ada query ?customer_id=
        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::find($request->customer_id);
        }

        return view('tasks.create', compact('pops', 'teknisiList', 'types', 'customer'));
    }

    /**
     * Simpan task baru.
     * Guard: task.create
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'customer_id'       => 'nullable|exists:customers,id',
            'pop_id'            => 'required|exists:pops,id',
            'task_type'         => 'required|in:' . implode(',', array_column(TaskType::cases(), 'value')),
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'scheduled_at'      => 'nullable|date|after:now',
            'team_member_ids'   => 'nullable|array|max:3',
            'team_member_ids.*' => 'exists:users,id',
            'conflict_override' => 'nullable|boolean',
        ]);

        $user = auth()->user();

        $memberIds   = $validated['team_member_ids'] ?? [];
        $scheduledAt = $validated['scheduled_at'] ?? null;

        if (!empty($memberIds) && !empty($scheduledAt)) {
            // Validasi konflik jadwal
            $conflicts = $this->taskService->detectConflicts(
                $memberIds,
                $scheduledAt,
                TaskType::from($validated['task_type'])->slaMinutes()
            );

            if ($conflicts->isNotEmpty() && !($validated['conflict_override'] ?? false)) {
                $messages = $conflicts->map(function ($team) {
                    $endStr = Carbon::parse($team->task->scheduled_at)->addMinutes($team->task->sla_minutes)->format('H:i');
                    return "• {$team->user->name}: Task \"{$team->task->task_number} {$team->task->task_type->label()}\" jam {$team->task->scheduled_at->format('H:i')}-{$endStr}";
                })->implode('<br>');

                return back()
                    ->withInput()
                    ->withErrors(['conflict' => 'Konflik jadwal terdeteksi:<br>' . $messages . '<br><br>Tick "Override konflik" jika ingin lanjut.'])
                    ->with('conflict_user_ids', $conflicts->pluck('user_id')->unique()->toArray());
            }

            if ($conflicts->isNotEmpty() && ($validated['conflict_override'] ?? false)) {
                // Cek apakah user punya izin override
                $this->authorize('conflictOverride', Task::class);
            }

            // Validasi batas 4 task/tim/hari
            $scheduleDate = Carbon::parse($scheduledAt)->format('Y-m-d');
            if (!$this->taskService->teamCanAddTask($memberIds, $scheduleDate)) {
                return back()->withInput()->withErrors([
                    'team' => 'Tim ini sudah memiliki ' . self::MAX_LIMIT_LABEL . ' task aktif pada tanggal tersebut.',
                ]);
            }
        }

        $task = $this->taskService->create($validated, $user);

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', "Task [{$task->task_number}] berhasil dibuat.");
    }

    /**
     * Jadwalkan tiket antrean ke tim teknisi.
     */
    public function schedule(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('schedule', $task);

        $validated = $request->validate([
            'scheduled_at'      => 'required|date|after:now',
            'team_member_ids'   => 'required|array|min:1|max:3',
            'team_member_ids.*' => 'exists:users,id',
            'conflict_override' => 'nullable|boolean',
            'checklist_items'   => 'required|string',
        ]);

        $user = auth()->user();

        // Validasi konflik jadwal
        $conflicts = $this->taskService->detectConflicts(
            $validated['team_member_ids'],
            $validated['scheduled_at'],
            TaskType::from($task->task_type->value)->slaMinutes(),
            $task->id
        );

        if ($conflicts->isNotEmpty() && !($validated['conflict_override'] ?? false)) {
            $messages = $conflicts->map(function ($team) {
                $endStr = Carbon::parse($team->task->scheduled_at)->addMinutes($team->task->sla_minutes)->format('H:i');
                return "• {$team->user->name}: Task \"{$team->task->task_number} {$team->task->task_type->label()}\" jam {$team->task->scheduled_at->format('H:i')}-{$endStr}";
            })->implode('<br>');

            return back()
                ->withErrors(['conflict' => 'Konflik jadwal terdeteksi:<br>' . $messages . '<br><br>Tick "Override konflik" jika ingin lanjut.'])
                ->with('conflict_user_ids', $conflicts->pluck('user_id')->unique()->toArray());
        }

        if ($conflicts->isNotEmpty() && ($validated['conflict_override'] ?? false)) {
            $this->authorize('conflictOverride', Task::class);
        }

        $scheduleDate = Carbon::parse($validated['scheduled_at'])->format('Y-m-d');
        if (!$this->taskService->teamCanAddTask($validated['team_member_ids'], $scheduleDate)) {
            return back()->withErrors([
                'team' => 'Tim ini sudah memiliki ' . self::MAX_LIMIT_LABEL . ' task aktif pada tanggal tersebut.',
            ]);
        }

        $this->taskService->scheduleTask($task, $validated, $user);

        return redirect()->back()->with('success', "Tiket [{$task->task_number}] berhasil dijadwalkan ke tim teknisi.");
    }

    /**
     * Detail task.
     */
    public function show(Task $task): View
    {
        $this->authorize('view', $task);

        $task->load(['customer', 'pop', 'fop', 'teamMembers.user', 'checklists.checkedByUser', 'evidences.uploader']);

        return view('tasks.show', compact('task'));
    }

    /**
     * Form edit task.
     * Guard: task.edit
     */
    public function edit(Task $task): View
    {
        $this->authorize('edit', $task);

        $user        = auth()->user();
        $pops        = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();
        $teknisiList = $this->getTeknisiForUser($user);
        $types       = TaskType::options();

        $task->load(['customer', 'teamMembers.user']);

        return view('tasks.edit', compact('task', 'pops', 'teknisiList', 'types'));
    }

    /**
     * Update task.
     * Guard: task.edit + (task.schedule jika jadwal berubah)
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('edit', $task);

        $validated = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'description'       => 'nullable|string|max:2000',
            'scheduled_at'      => 'sometimes|required|date',
            'team_member_ids'   => 'sometimes|array|min:1|max:3',
            'team_member_ids.*' => 'exists:users,id',
            'conflict_override' => 'nullable|boolean',
        ]);

        // Jika jadwal berubah, perlu izin schedule
        if (isset($validated['scheduled_at'])) {
            $this->authorize('schedule', $task);

            $memberIds = $validated['team_member_ids'] ?? $task->teamMembers()->pluck('user_id')->toArray();
            $conflicts = $this->taskService->detectConflicts(
                $memberIds,
                $validated['scheduled_at'],
                $task->sla_minutes ?? 120,
                $task->id
            );

            if ($conflicts->isNotEmpty() && !($validated['conflict_override'] ?? false)) {
                $messages = $conflicts->map(function ($team) {
                    $endStr = Carbon::parse($team->task->scheduled_at)->addMinutes($team->task->sla_minutes)->format('H:i');
                    return "• {$team->user->name}: Task \"{$team->task->task_number} {$team->task->task_type->label()}\" jam {$team->task->scheduled_at->format('H:i')}-{$endStr}";
                })->implode('<br>');

                return back()->withInput()->withErrors([
                    'conflict' => 'Konflik jadwal terdeteksi:<br>' . $messages . '<br><br>Tick "Override konflik" jika ingin lanjut.',
                ])->with('conflict_user_ids', $conflicts->pluck('user_id')->unique()->toArray());
            }

            if ($conflicts->isNotEmpty() && ($validated['conflict_override'] ?? false)) {
                $this->authorize('conflictOverride', Task::class);
            }
        }

        $this->taskService->update($task, $validated, auth()->user());

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', 'Task berhasil diperbarui.');
    }

    /**
     * Cancel task.
     * Guard: task.cancel
     */
    public function cancel(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('cancel', $task);

        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $this->taskService->cancel($task, auth()->user(), $validated['cancel_reason']);

        return redirect()
            ->route('tasks.index')
            ->with('success', "Task [{$task->task_number}] berhasil dibatalkan.");
    }

    // ─── API Endpoints ───────────────────────────────────────────

    /**
     * JSON: data task untuk kalender (filter by date range + pop + type).
     * Guard: task.view.all
     */
    public function calendarData(Request $request): JsonResponse
    {
        $this->authorize('viewAll', Task::class);

        $validated = $request->validate([
            'start'    => 'required|date',
            'end'      => 'required|date|after_or_equal:start',
            'pop_id'   => 'nullable|exists:pops,id',
            'type'     => 'nullable|in:' . implode(',', array_column(TaskType::cases(), 'value')),
            'team_ids' => 'nullable|array',
        ]);

        $user = auth()->user();

        $query = Task::with(['customer', 'pop', 'teamMembers.user'])
            ->applyUserScope($user)
            ->whereBetween('scheduled_at', [
                Carbon::parse($validated['start'])->startOfDay(),
                Carbon::parse($validated['end'])->endOfDay(),
            ]);

        if (!empty($validated['pop_id'])) {
            $query->where('pop_id', $validated['pop_id']);
        }

        if (!empty($validated['type'])) {
            $query->where('task_type', $validated['type']);
        }

        if (!empty($validated['team_ids'])) {
            $query->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $validated['team_ids']));
        }

        $tasks = $query->orderBy('scheduled_at')->get();

        return response()->json($tasks->map(fn (Task $t) => $this->taskToCalendarItem($t)));
    }

    /**
     * JSON: cari pelanggan by CID/nama untuk autocomplete saat buat task.
     * Guard: task.create
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $q    = $request->query('q', '');
        $user = auth()->user();

        $customers = Customer::applyUserScope($user)
            ->where(function ($query) use ($q) {
                $query->where('full_name', 'like', "%{$q}%")
                      ->orWhere('cid', 'like', "%{$q}%")
                      ->orWhere('customer_code', 'like', "%{$q}%");
            })
            ->whereIn('status', ['active', 'siap_billing'])
            ->select('id', 'full_name', 'cid', 'customer_code', 'pop_id')
            ->limit(10)
            ->get();

        return response()->json($customers->map(fn ($c) => [
            'id'       => $c->id,
            'label'    => "{$c->full_name} — {$c->cid}",
            'pop_id'   => $c->pop_id,
        ]));
    }

    /**
     * JSON: cek konflik jadwal sebelum simpan task (dipakai form JS).
     */
    public function checkConflict(Request $request): JsonResponse
    {
        $this->authorize('create', Task::class);

        $validated = $request->validate([
            'user_ids'     => 'required|array',
            'scheduled_at' => 'required|date',
            'task_type'    => 'required',
            'exclude_task_id' => 'nullable|integer',
        ]);

        $slaMinutes = TaskType::tryFrom($validated['task_type'])?->slaMinutes() ?? 120;

        $conflictIds = $this->taskService->detectConflicts(
            $validated['user_ids'],
            $validated['scheduled_at'],
            $slaMinutes,
            $validated['exclude_task_id'] ?? null
        );

        $conflictUsers = User::whereIn('id', $conflictIds)->select('id', 'name')->get();

        return response()->json([
            'has_conflict'    => !empty($conflictIds),
            'conflict_users'  => $conflictUsers,
        ]);
    }

    // ─── FOP Review Actions ──────────────────────────────────────

    /**
     * FOP: Reject pending task (belum dijadwal)
     */
    public function reject(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('fopReject', $task);

        $validated = $request->validate([
            'reject_reason' => 'required|string|max:1000',
        ]);

        $task->update([
            'reject_reason' => $validated['reject_reason'],
            'fop_review_status' => 'rejected',
        ]);

        $this->notifyTeamMembers(
            $task,
            'Task Ditolak: ' . $task->task_number,
            'Task pending ditolak oleh FOP: ' . $validated['reject_reason'],
            'error'
        );

        return back()->with('success', "Task [{$task->task_number}] ditolak (reject).");
    }

    /**
     * FOP: Pending scheduled/in_progress task
     */
    public function pending(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('fopPending', $task);

        $validated = $request->validate([
            'pending_reason' => 'required|string|max:1000',
        ]);

        $task->update([
            'status' => TaskStatus::PENDING,
            'pending_reason' => $validated['pending_reason'],
        ]);

        $this->notifyTeamMembers(
            $task,
            'Task Di-pending: ' . $task->task_number,
            'Task ditangguhkan oleh FOP: ' . $validated['pending_reason'],
            'warning'
        );

        return back()->with('success', "Task [{$task->task_number}] di-pending.");
    }

    /**
     */
    public function review(Request $request, Task $task, \App\Services\CustomerWorkflowService $workflowService): RedirectResponse
    {
        $this->authorize('review', $task);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject,pending',
            'reason' => 'required_if:action,reject,pending|nullable|string|max:1000',
        ]);

        $action = $validated['action'];
        $reason = $validated['reason'] ?? null;
        $oldValues = $task->toArray();
        $msg = '';

        if ($action === 'approve') {
            $task->update(['fop_review_status' => 'approved']);
            \App\Models\AuditLog::log($task, 'approved', $oldValues, $task->toArray());

            // Transition customer status
            if ($task->customer) {
                if ($task->task_type === TaskType::SURVEY) {
                    $workflowService->transition($task->customer, \App\Enums\WorkflowTransition::WAITING_INSTALLATION, 'Survey Approved by FOP');
                } elseif ($task->task_type === TaskType::PEMASANGAN) {
                    $workflowService->transition($task->customer, \App\Enums\WorkflowTransition::ACTIVE, 'Installation Approved by FOP');
                }
            }
            $this->notifyTeamMembers(
                $task,
                'Laporan Disetujui: ' . $task->task_number,
                'Laporan task Anda telah disetujui oleh FOP.',
                'success'
            );
            $msg = 'disetujui';
        } elseif ($action === 'reject') {
            $task->update([
                'status' => TaskStatus::IN_PROGRESS,
                'fop_review_status' => 'rejected',
                'reject_reason' => $reason,
            ]);
            \App\Models\AuditLog::log($task, 'rejected', $oldValues, $task->toArray());

            // Revert customer status
            if ($task->customer) {
                if ($task->task_type === TaskType::SURVEY) {
                    $workflowService->transition($task->customer, \App\Enums\WorkflowTransition::SURVEY_IN_PROGRESS, 'Survey Rejected by FOP: ' . $reason);
                } elseif ($task->task_type === TaskType::PEMASANGAN) {
                    $workflowService->transition($task->customer, \App\Enums\WorkflowTransition::INSTALLATION_IN_PROGRESS, 'Installation Rejected by FOP: ' . $reason);
                }
            }
            $this->notifyTeamMembers(
                $task,
                'Laporan Ditolak: ' . $task->task_number,
                'Laporan task Anda ditolak oleh FOP: ' . $reason,
                'error'
            );
            $msg = 'ditolak dan dikembalikan ke In Progress';
        } elseif ($action === 'pending') {
            $task->update([
                'status' => TaskStatus::PENDING,
                'fop_review_status' => 'pending',
                'pending_reason' => $reason,
            ]);
            $this->notifyTeamMembers(
                $task,
                'Task Di-pending: ' . $task->task_number,
                'Task Anda di-pending kembali oleh FOP: ' . $reason,
                'warning'
            );
            $msg = 'di-pending';
        }

        return back()->with('success', "Review FOP selesai. Task [{$task->task_number}] {$msg}.");
    }

    // ─── Private Helpers ─────────────────────────────────────────

    private function taskToCalendarItem(Task $task): array
    {
        return [
            'id'            => $task->id,
            'task_number'   => $task->task_number,
            'title'         => $task->title,
            'task_type'     => $task->task_type->value,
            'task_type_label' => $task->task_type->label(),
            'card_classes'  => $task->task_type->cardClasses(),
            'status'        => $task->status->value,
            'status_label'  => $task->status->label(),
            'scheduled_at'  => $task->scheduled_at?->toIso8601String(),
            'scheduled_time' => $task->scheduled_at?->format('H:i'),
            'customer_name' => $task->customer?->full_name ?? '-',
            'customer_cid'  => $task->customer?->display_id ?? '-',
            'pop_name'      => $task->pop?->name ?? '-',
            'team'          => $task->teamMembers->map(fn ($tm) => [
                'id'     => $tm->user_id,
                'name'   => $tm->user?->name,
                'initials' => $this->initials($tm->user?->name ?? ''),
                'role'   => $tm->role_in_task,
            ]),
            'checklist_total'    => $task->checklists->count(),
            'checklist_done'     => $task->checklists->where('is_checked', true)->count(),
            'evidence_count'     => $task->evidences->count(),
            'is_cancelled'       => $task->status === TaskStatus::DIBATALKAN,
            'sla_minutes'        => $task->sla_minutes,
            'is_over_sla'        => $task->isOverSla(),
        ];
    }

    private function initials(string $name): string
    {
        $words = explode(' ', trim($name));
        return strtoupper(
            implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 2)))
        );
    }

    private function getTeknisiForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        /** @var EffectiveAccessService $accessService */
        $accessService = app(EffectiveAccessService::class);
        $allowedPopIds = $accessService->getAllowedPopIds($user);

        $query = User::with('role')
            ->whereHas('role', fn ($q) => $q->where('code', 'teknisi'))
            ->orderBy('name');

        if (!empty($allowedPopIds)) {
            // Filter teknisi berdasarkan POP yang boleh diakses FOP
            $query->whereHas('roleScopes.targets', fn ($q) => $q->whereIn('pop_id', $allowedPopIds));
        }

        return $query->get();
    }

    private function buildTeamStats(\Illuminate\Database\Eloquent\Collection $todayTasks): array
    {
        // Kumpulkan semua user unik dari semua task hari ini
        $teamsMap = [];

        foreach ($todayTasks as $task) {
            $members = $task->teamMembers ?? collect();
            // Buat key dari sorted user IDs untuk grouping
            $memberIds = $members->pluck('user_id')->sort()->values()->implode(',');
            if (!$memberIds) continue;

            if (!isset($teamsMap[$memberIds])) {
                $teamsMap[$memberIds] = [
                    'name'         => 'Tim ' . (count($teamsMap) + 1), // Will be replaced
                    'members'      => [],
                    'task_count'   => 0,
                    'selesai_count' => 0,
                    'color'        => collect(['bg-sky-500', 'bg-green-500', 'bg-amber-500', 'bg-purple-500'])->get(count($teamsMap), 'bg-slate-500'),
                    'extra_count'  => 0,
                ];

                $maxShow = 2;
                foreach ($members->take($maxShow) as $member) {
                    $teamsMap[$memberIds]['members'][] = [
                        'name'     => $member->user?->name ?? '?',
                        'initials' => $this->initials($member->user?->name ?? '?'),
                    ];
                }
                if ($members->count() > $maxShow) {
                    $teamsMap[$memberIds]['extra_count'] = $members->count() - $maxShow;
                }
            }

            $teamsMap[$memberIds]['task_count']++;
            if ($task->status === TaskStatus::SELESAI) {
                $teamsMap[$memberIds]['selesai_count']++;
            }
        }

        return array_values($teamsMap);
    }

    private function notifyTeamMembers(Task $task, string $title, string $message, string $type = 'info'): void
    {
        $url = route('tasks.show', $task->id);
        foreach ($task->teamMembers as $member) {
            if ($member->user) {
                /** @var \App\Models\User $user */
                $user = $member->user;
                $user->notify(new \App\Notifications\AppNotification(
                    title: $title,
                    message: $message,
                    actionUrl: $url,
                    type: $type
                ));
            }
        }
    }

    private const MAX_LIMIT_LABEL = '4';
}



