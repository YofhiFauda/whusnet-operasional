<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CustomerWorkflowService;
use App\Services\EffectiveAccessService;
use App\Services\FopTaskTeamService;
use App\Services\TaskService;
use App\Support\ReasonValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $taskService) {}

    /**
     * Dashboard Teknisi — hanya task di mana user terdaftar sebagai anggota.
     * Guard: task.view.own
     */
    public function indexOwn(Request $request): View
    {
        $this->authorize('viewOwn', Task::class);

        $user = auth()->user();
        $today = Carbon::today();

        $tasks = Task::with(['customer', 'pop', 'evidences', 'fop', 'teamMembers'])
            ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', '!=', TaskStatus::DIBATALKAN->value)
            ->where(function ($q) use ($today) {
                $q->whereDate('scheduled_at', $today)
                    ->orWhereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
                  // Overdue: terjadwal tapi scheduled_at udah lewat hari ini — jangan sampe ilang dari list
                    ->orWhere(function ($q2) use ($today) {
                        $q2->where('status', TaskStatus::TERJADWAL->value)
                            ->whereDate('scheduled_at', '<', $today);
                    });
            })
            ->orderBy('scheduled_at')
            ->get();

        $upcomingTasks = Task::with(['customer', 'pop'])
            ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $user->id))
            ->whereDate('scheduled_at', '>', $today)
            ->whereIn('status', [TaskStatus::TERJADWAL->value])
            ->whereNotIn('id', $tasks->pluck('id'))
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

        abort_if(! $isMember, 403, 'Anda bukan anggota task ini.');

        $task->load(['customer', 'pop', 'evidences', 'teamMembers']);

        return view('tasks.partials.own-card', compact('task'));
    }

    /**
     * Detail task.
     */
    public function show(Task $task): View
    {
        $this->authorize('view', $task);

        $task->load([
            'customer.village',
            'customer.district',
            'customer.city',
            'customer.customerAddress',
            'customer.customerService.internetPackage',
            'customer.latestSurvey',
            'customer.customerDevice',
            'customer.customerTechnicalDetail',
            'pop',
            'fop',
            'teamMembers.user',
            'evidences.uploader',
        ]);

        $recentMaintenanceTasks = collect();
        if ($task->customer_id) {
            $recentMaintenanceTasks = Task::where('customer_id', $task->customer_id)
                ->where('id', '!=', $task->id)
                ->where('task_type', TaskType::MAINTENANCE->value)
                ->latest('scheduled_at')
                ->take(3)
                ->get();
        }

        return view('tasks.show', compact('task', 'recentMaintenanceTasks'));
    }

    /**
     * Form edit task.
     * Guard: task.edit
     */
    public function edit(Task $task): View
    {
        $this->authorize('edit', $task);

        $user = auth()->user();
        $pops = Pop::forUser($user)->where('status', 'active')->orderBy('name')->get();
        $teknisiList = $this->getTeknisiForUser($user);
        $types = TaskType::manualOptions();

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

        // Tipe auto-only (Survey/Pemasangan/Ambil Modem) tetap dikirim balik oleh
        // form lewat hidden input (lihat tasks/edit.blade.php) meski dropdown-nya
        // disabled — kunci validasi harus honor nilai existing itu, sama pola-nya
        // dengan FopTaskController::update().
        $lockedExistingType = $task->task_type->value;
        $typeAllowedValues = in_array($lockedExistingType, TaskType::autoOnlyValues(), true)
            ? array_unique(array_merge(TaskType::manualValues(), [$lockedExistingType]))
            : TaskType::manualValues();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'task_type' => 'sometimes|required|in:'.implode(',', $typeAllowedValues),
            'scheduled_at' => 'sometimes|required|date',
            'team_member_ids' => 'sometimes|array|min:1',
            'team_member_ids.*' => 'exists:users,id',
            'conflict_override' => 'nullable|boolean',
        ]);

        // Jika tipe task berubah, perlu izin editType
        if (isset($validated['task_type']) && $validated['task_type'] !== $task->task_type->value) {
            $this->authorize('editType', $task);
        } else {
            unset($validated['task_type']);
        }

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

            if ($conflicts->isNotEmpty() && ! ($validated['conflict_override'] ?? false)) {
                $messages = $conflicts->map(function ($team) {
                    $endStr = Carbon::parse($team->task->scheduled_at)->addMinutes($team->task->sla_minutes)->format('H:i');

                    return "• {$team->user->name}: Task \"{$team->task->task_number} {$team->task->task_type->label()}\" jam {$team->task->scheduled_at->format('H:i')}-{$endStr}";
                })->implode('<br>');

                return back()->withInput()->withErrors([
                    'conflict' => 'Konflik jadwal terdeteksi:<br>'.$messages.'<br><br>Tick "Override konflik" jika ingin lanjut.',
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
            // Task.cancel_reason varchar(255) — max harus samain kapasitas kolom.
            'cancel_reason' => ReasonValidationRule::required(255),
        ]);

        $this->taskService->cancel($task, auth()->user(), $validated['cancel_reason']);

        return redirect()
            ->route('fop.dashboard')
            ->with('success', "Task [{$task->task_number}] berhasil dibatalkan.");
    }

    /**
     * Teknisi: Pending top-level — lepas assignment teknisi, balik task ke
     * antrian Task FOP untuk dijadwalkan ulang. BEDA dari
     * `TaskStatusController::pending()` (Lapor Nanti, assignment tetap).
     * SAMA PERSIS perilakunya dengan `pending()` FOP di bawah — "Pending"
     * cuma 1 logic di sistem ini, siapapun yang trigger (lihat
     * `releaseTeamAndSetPending()` dan docs/project_status_label_unifikasi.md).
     * Guard: task.status.reschedule — hanya anggota tim, status terjadwal/in_progress.
     */
    public function reschedule(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('statusReschedule', $task);

        $validated = $request->validate([
            // Task.pending_reason varchar(255) — max harus samain kapasitas kolom.
            'pending_reason' => ReasonValidationRule::required(255),
        ]);

        $this->releaseTeamAndSetPending($task, $validated['pending_reason'], 'reschedule');

        return redirect()
            ->route('tasks.own')
            ->with('success', "Task [{$task->task_number}] di-reschedule, kembali ke antrian FOP.");
    }

    /**
     * Set Task ke `Pending` + lepas tim + rebuild jadwal — SATU-SATUNYA
     * perilaku "pending" di sistem (2026-07-15). Dipanggil dari `reschedule()`
     * (teknisi, top-level) dan `pending()` (FOP, manual) — beda cuma guard
     * permission & pesan redirect, kelakuan intinya SAMA PERSIS. Lihat
     * docs/project_status_label_unifikasi.md § DESAIN FINAL.
     */
    private function releaseTeamAndSetPending(Task $task, string $reason, string $auditAction): void
    {
        DB::transaction(function () use ($task, $reason, $auditAction) {
            $oldValues = $task->toArray();

            $task->update([
                'status' => TaskStatus::PENDING,
                'pending_reason' => $reason,
                'updated_by' => auth()->id(),
            ]);

            $task->teamMembers()->delete();

            AuditLog::log($task, $auditAction, $oldValues, $task->fresh()->toArray());

            $fopTask = FopTask::where('task_id', $task->id)->first();

            if ($fopTask) {
                $fopOldValues = $fopTask->toArray();

                $fopTask->technicians()->detach();
                $fopTask->update([
                    'status' => TaskStatus::PENDING,
                    'pending_reason' => $reason,
                    'team_id' => null,
                ]);
                $fopTask->manual_override_at = null;
                $fopTask->save();

                AuditLog::log($fopTask, $auditAction, $fopOldValues, $fopTask->fresh()->toArray());

                if ($fopTask->task_date) {
                    app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($fopTask->task_date));
                }
            }
        });
    }

    // ─── API Endpoints ───────────────────────────────────────────

    /**
     * JSON: cari pelanggan by CID/nama untuk autocomplete (dipakai modal /fop-tasks).
     * Guard: task.lookup
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('lookup', Task::class);

        $q = $request->query('q', '');
        $user = auth()->user();

        $customers = Customer::with(['village', 'pop'])->applyUserScope($user)
            ->where(function ($query) use ($q) {
                $query->where('full_name', 'like', "%{$q}%")
                    ->orWhere('cid', 'like', "%{$q}%")
                    ->orWhere('customer_code', 'like', "%{$q}%");
            })
            ->whereIn('status', [
                'active',
                'siap_billing',
                'registered',
                'waiting_survey',
                'survey_in_progress',
                'surveyed',
                'waiting_acc',
                'waiting_installation',
                'installation_in_progress',
                'installed',
                'verification_admin',
                'revision_installation',
            ])
            ->select('id', 'full_name', 'cid', 'customer_code', 'pop_id', 'village_id', 'distribution_id', 'status')
            ->limit(10)
            ->get();

        return response()->json($customers->map(function ($c) {
            $desa = $c->village ? strtoupper($c->village->name) : 'NODESA';
            $cid = $c->display_id ?: ($c->cid ?: $c->customer_code);
            $nama = strtoupper($c->full_name);

            return [
                'id' => $c->id,
                'label' => "{$cid}_{$desa}_{$nama}",
                'pop_id' => $c->pop_id,
                'village_id' => $c->village_id,
            ];
        }));
    }

    /**
     * JSON: cek konflik jadwal (dipakai form edit task).
     * Guard: task.lookup
     */
    public function checkConflict(Request $request): JsonResponse
    {
        $this->authorize('lookup', Task::class);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'scheduled_at' => 'required|date',
            'task_type' => 'required',
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
            'has_conflict' => ! empty($conflictIds),
            'conflict_users' => $conflictUsers,
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
            // Task.reject_reason varchar(1000).
            'reject_reason' => ReasonValidationRule::required(1000),
        ]);

        $task->update([
            'reject_reason' => $validated['reject_reason'],
            'fop_review_status' => 'rejected',
        ]);

        $this->notifyTeamMembers(
            $task,
            'Task Ditolak: '.$task->task_number,
            'Task pending ditolak oleh FOP: '.$validated['reject_reason'],
            NotificationType::ERROR
        );

        return back()->with('success', "Task [{$task->task_number}] ditolak (reject).");
    }

    /**
     * FOP: Pending scheduled/in_progress task — lepas tim + rebuild jadwal,
     * perilaku SAMA PERSIS kayak `reschedule()` teknisi di atas (lihat
     * `releaseTeamAndSetPending()`). Notifikasi ke tim dikirim SEBELUM tim
     * dilepas (`releaseTeamAndSetPending` men-detach pivot-nya, jadi query tim
     * sesudahnya bakal kosong).
     */
    public function pending(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('fopPending', $task);

        $validated = $request->validate([
            // Task.pending_reason varchar(255) — max harus samain kapasitas kolom.
            'pending_reason' => ReasonValidationRule::required(255),
        ]);

        $this->notifyTeamMembers(
            $task,
            'Task Di-pending: '.$task->task_number,
            'Task ditangguhkan oleh FOP: '.$validated['pending_reason'],
            NotificationType::WARNING
        );

        $this->releaseTeamAndSetPending($task, $validated['pending_reason'], 'pending');

        return back()->with('success', "Task [{$task->task_number}] di-pending.");
    }

    public function review(Request $request, Task $task, CustomerWorkflowService $workflowService): RedirectResponse
    {
        $this->authorize('review', $task);

        $validated = $request->validate([
            'action' => 'required|in:approve,reject,pending',
            // 'reason' ditulis ke reject_reason (varchar 1000) ATAU pending_reason
            // (varchar 255) tergantung $action — dicap ke yg lebih kecil (255)
            // biar gak pernah overflow kolom manapun tujuannya.
            'reason' => 'required_if:action,reject,pending|nullable|string|max:255',
        ]);

        $action = $validated['action'];
        $reason = $validated['reason'] ?? null;
        $oldValues = $task->toArray();
        $msg = '';

        if ($action === 'approve') {
            // Approve Pemasangan wajib lewat Verifikasi Admin (finalVerify) — jalur itu
            // yang generate CID & Invoice AWAL. Approve dari sini dulu langsung
            // men-transisi customer ke ACTIVE tanpa CID/Invoice, bikin pelanggan
            // "aktif" tapi gak pernah ditagih dan gak punya CID.
            if ($task->task_type === TaskType::PEMASANGAN) {
                return back()->with('error', 'Approve pemasangan wajib dilakukan lewat halaman Verifikasi Admin (generate CID & tagihan awal).');
            }

            $task->update(['fop_review_status' => 'approved']);
            AuditLog::log($task, 'approved', $oldValues, $task->toArray());

            // Transition customer status
            if ($task->customer) {
                if ($task->task_type === TaskType::SURVEY) {
                    $workflowService->transition($task->customer, WorkflowTransition::WAITING_INSTALLATION, 'Survey Approved by FOP');
                }
            }
            $this->notifyTeamMembers(
                $task,
                'Laporan Disetujui: '.$task->task_number,
                'Laporan task Anda telah disetujui oleh FOP.',
                NotificationType::SUCCESS
            );
            $msg = 'disetujui';
        } elseif ($action === 'reject') {
            $task->update([
                'status' => TaskStatus::IN_PROGRESS,
                'fop_review_status' => 'rejected',
                'reject_reason' => $reason,
            ]);
            AuditLog::log($task, 'rejected', $oldValues, $task->toArray());

            // Revert customer status
            if ($task->customer) {
                if ($task->task_type === TaskType::SURVEY) {
                    $workflowService->transition($task->customer, WorkflowTransition::SURVEY_IN_PROGRESS, 'Survey Rejected by FOP: '.$reason);
                } elseif ($task->task_type === TaskType::PEMASANGAN) {
                    $workflowService->transition($task->customer, WorkflowTransition::INSTALLATION_IN_PROGRESS, 'Installation Rejected by FOP: '.$reason);
                }
            }
            $this->notifyTeamMembers(
                $task,
                'Laporan Ditolak: '.$task->task_number,
                'Laporan task Anda ditolak oleh FOP: '.$reason,
                NotificationType::ERROR
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
                'Task Di-pending: '.$task->task_number,
                'Task Anda di-pending kembali oleh FOP: '.$reason,
                NotificationType::WARNING
            );
            $msg = 'di-pending';
        }

        return back()->with('success', "Review FOP selesai. Task [{$task->task_number}] {$msg}.");
    }

    // ─── Private Helpers ─────────────────────────────────────────

    private function initials(string $name): string
    {
        $words = explode(' ', trim($name));

        return strtoupper(
            implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 2)))
        );
    }

    private function getTeknisiForUser(User $user): Collection
    {
        /** @var EffectiveAccessService $accessService */
        $accessService = app(EffectiveAccessService::class);
        $allowedPopIds = $accessService->getAllowedPopIds($user);

        $query = User::with('role')
            ->whereHas('role', fn ($q) => $q->where('code', 'teknisi'))
            ->orderBy('name');

        if (! empty($allowedPopIds)) {
            // Filter teknisi berdasarkan POP yang boleh diakses FOP
            $query->whereHas('roleScopes.targets', fn ($q) => $q->whereIn('pop_id', $allowedPopIds));
        }

        return $query->get();
    }

    private function buildTeamStats(Collection $todayTasks): array
    {
        // Kumpulkan semua user unik dari semua task hari ini
        $teamsMap = [];

        foreach ($todayTasks as $task) {
            $members = $task->teamMembers ?? collect();
            // Buat key dari sorted user IDs untuk grouping
            $memberIds = $members->pluck('user_id')->sort()->values()->implode(',');
            if (! $memberIds) {
                continue;
            }

            if (! isset($teamsMap[$memberIds])) {
                $teamsMap[$memberIds] = [
                    'name' => 'Tim '.(count($teamsMap) + 1), // Will be replaced
                    'members' => [],
                    'task_count' => 0,
                    'selesai_count' => 0,
                    'color' => collect(['bg-sky-500', 'bg-green-500', 'bg-amber-500', 'bg-purple-500'])->get(count($teamsMap), 'bg-slate-500'),
                    'extra_count' => 0,
                ];

                $maxShow = 2;
                foreach ($members->take($maxShow) as $member) {
                    $teamsMap[$memberIds]['members'][] = [
                        'name' => $member->user?->name ?? '?',
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

    private function notifyTeamMembers(Task $task, string $title, string $message, NotificationType $type = NotificationType::INFO): void
    {
        $task->loadMissing('teamMembers.user');
        $url = route('tasks.show', $task->id);
        foreach ($task->teamMembers as $member) {
            if ($member->user) {
                /** @var User $user */
                $user = $member->user;
                $user->notify(new AppNotification(
                    title: $title,
                    message: $message,
                    actionUrl: $url,
                    type: $type
                ));
            }
        }
    }
}
