<?php

namespace App\Http\Controllers;

use App\Models\FopTask;
use App\Models\FopTaskTeam;
use App\Models\Village;
use App\Models\User;
use App\Models\Pop;
use App\Models\Task;
use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Notifications\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Enums\TaskType;
use App\Services\TaskService;
use App\Services\FopTaskTeamService;

class FopTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeAccess();
        
        $this->autoSyncAndCalculatePriority();

        $query = FopTask::with([
            'village',
            'technicians',
            'task:id,scheduled_at,status,report_deferred,fop_review_status',
            'statusHistories',
            'customer:id,created_at,updated_at',
            'customer.tasks' => function ($q) {
                $q->where('task_type', TaskType::SURVEY->value)
                  ->where('status', 'selesai')
                  ->select('id', 'customer_id', 'task_type', 'status', 'completed_at');
            },
        ])
            ->whereNotIn('status', [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])
            ->orderByRaw("CASE WHEN client_request_date IS NOT NULL AND client_request_date >= ? THEN 1 ELSE 0 END", [now()->addDay()->toDateString()])
            ->orderByRaw("CASE priority
                WHEN 'Urgent' THEN 1
                WHEN 'High' THEN 2
                WHEN 'Medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 5 END")
            ->orderByRaw("CASE WHEN category IN ('Survey', 'PSB') THEN created_at END ASC")
            ->orderByRaw("CASE WHEN category NOT IN ('Survey', 'PSB') THEN created_at END DESC");

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('task_number', 'like', "%{$search}%")
                  ->orWhere('tugas', 'like', "%{$search}%")
                  ->orWhere('issue', 'like', "%{$search}%");
            });
        }

        // Dropdown filters
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $statusVal = $request->input('status');
            $activeStatuses = collect(TaskStatus::cases())
                ->reject(fn (TaskStatus $s) => in_array($s, [TaskStatus::SELESAI, TaskStatus::DIBATALKAN], true))
                ->map(fn (TaskStatus $s) => $s->value)
                ->all();
            if (in_array($statusVal, $activeStatuses, true)) {
                $query->where('status', $statusVal);
            } else {
                $query->whereRaw('1=0');
            }
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('village_id')) {
            $query->where('village_id', $request->input('village_id'));
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }

        $fopTasks = $query->with('team:id,name')->paginate(20)->withQueryString();

        // Get villages for area selector
        $villages = Village::orderBy('name', 'asc')->get();

        // Get POPs for cabang selector
        $pops = Pop::orderBy('name', 'asc')->get();

        // Get technicians for assignee selector
        $technicians = User::whereHas('role', function($q) {
            $q->where('code', 'teknisi');
        })->where('status', 'active')->orderBy('name', 'asc')->get();

        // Categories mapping using Enum
        $categories = collect(TaskType::cases())->mapWithKeys(function ($category) {
            return [$category->value => $category->label()];
        })->toArray();

        // Tipe task yang boleh dipakai utk tambah task manual — Survey & Pemasangan Baru
        // dikecualikan karena wajib lewat Registrasi Pelanggan (auto-sync).
        $manualCategories = collect($categories)
            ->except(TaskType::autoOnlyValues())
            ->toArray();

        $canEditFopTaskType = auth()->user()->hasPermission('fop_tasks.update_sensitive');

        // Daftar Team (dipakai dropdown filter & dropdown "+ Masukkan ke Team..." di kolom Team)
        $teams = FopTaskTeam::with(['members:id,name', 'fopTasks:id,team_id,status', 'fopTasks.technicians:id,name'])
            ->orderByDesc('work_date')
            ->limit(50)
            ->get()
            ->map(function (FopTaskTeam $team) {
                $activeCount = $team->fopTasks->filter(
                    fn ($t) => !in_array($t->status->value, [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                )->count();

                $workload = $team->fopTasks
                    ->flatMap(fn ($t) => $t->technicians)
                    ->countBy('id');

                return [
                    'id'         => $team->id,
                    'name'       => $team->name,
                    'work_date'  => $team->work_date->format('Y-m-d'),
                    'members'    => $team->members->map(fn ($m) => [
                        'id'    => $m->id,
                        'name'  => $m->name,
                        'count' => $workload->get($m->id, 0),
                    ])->values(),
                    'task_count' => $team->fopTasks->count(),
                    'is_active'  => $activeCount > 0,
                ];
            });

        $teamConflicts = $this->currentTeamConflicts();

        // Daftar task ringkas buat dropdown "Task Tujuan" di modal Switch Teknisi — query
        // TERPISAH dari $fopTasks (yang kena filter/paginate dari request), soalnya "Task
        // Tujuan" harus nampilin SEMUA task aktif (lintas team, lintas filter/halaman
        // berapapun), bukan cuma yang lagi kebetulan tampil di tabel.
        $switchTargetTasks = FopTask::whereNotIn('status', [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])
            ->with('technicians:id,name')
            ->get()
            ->map(fn (FopTask $t) => [
                'id' => $t->id,
                'task_number' => $t->task_number,
                'tugas' => $t->tugas,
                'task_date' => $t->task_date?->toDateString(),
                'technician_ids' => $t->technicians->pluck('id')->all(),
            ])
            ->values();

        return view('fop_tasks.index', compact('fopTasks', 'villages', 'pops', 'technicians', 'categories', 'manualCategories', 'canEditFopTaskType', 'teams', 'teamConflicts', 'switchTargetTasks'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(TaskType::manualValues())],
            'task_date' => ['required', 'date'],
            'tugas' => ['required', 'string', 'max:255'],
            'village_id' => ['required', 'exists:villages,id'],
            'pop_id' => ['required', 'exists:pops,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'issue' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(TaskStatus::class)],
            'priority' => ['required', 'string', Rule::enum(FopTaskPriority::class)],
            'pending_reason' => ['nullable', 'required_if:status,pending', 'string', 'max:255'],
            'client_request_date' => ['nullable', 'required_if:status,pending', 'date'],
            'technicians' => ['required', 'array', 'min:1'],
            'technicians.*' => ['exists:users,id'],
        ], [
            'pending_reason.required_if' => 'Alasan pending wajib diisi jika status Pending.',
            'client_request_date.required_if' => 'Tanggal request client wajib diisi jika status Pending.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $taskNumber = $this->generateTaskNumber();

            $fopTask = new FopTask();
            $fopTask->task_number = $taskNumber;
            $fopTask->task_date = $validated['task_date'];
            $fopTask->category = $validated['category'];
            $fopTask->tugas = $validated['tugas'];
            $fopTask->village_id = $validated['village_id'];
            $fopTask->pop_id = $validated['pop_id'];
            $fopTask->customer_id = $validated['customer_id'] ?? null;
            $fopTask->issue = $validated['issue'] ?? null;
            $fopTask->notes = $validated['notes'] ?? null;
            $fopTask->status = $validated['status'];
            $fopTask->priority = $validated['priority'];

            if ($validated['status'] === TaskStatus::PENDING->value) {
                $fopTask->pending_reason = $validated['pending_reason'];
                $fopTask->client_request_date = $validated['client_request_date'];
            } elseif ($validated['status'] === TaskStatus::DIBATALKAN->value) {
                $fopTask->cancelled_at = now();
            }

            $fopTask->save();

            if (!empty($validated['technicians'])) {
                $technicians = $validated['technicians'];
                $fopTask->technicians()->sync($technicians);

                // Title dibuat polos dulu — prefix "[Nama Team]" diisi oleh
                // FopTaskTeamService::rebuildTeamsForDate() begitu team-nya kebentuk
                // (dipanggil beberapa baris di bawah), bukan ditebak di sini.
                $taskData = [
                    'customer_id' => $fopTask->customer_id,
                    'pop_id' => $fopTask->pop_id,
                    'task_type' => $fopTask->category->value,
                    'title' => 'FOP: ' . $fopTask->tugas,
                    'description' => trim($fopTask->issue . "\n" . $fopTask->notes),
                    'team_member_ids' => $technicians,
                    'scheduled_at' => $fopTask->task_date,
                    'conflict_override' => true,
                ];

                $task = app(TaskService::class)->create($taskData, auth()->user());
                $fopTask->task_id = $task->id;
                $fopTask->save();
            }

            // Log activity to audit log if AuditLog exists
            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'create', null, $fopTask->load('technicians')->toArray());
            }

            $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($fopTask->task_date));

            $redirect = redirect()->route('fop-tasks.index')
                ->with('success', "Task FOP {$taskNumber} berhasil dibuat.");

            if (count($technicians) > 1) {
                $redirect = $redirect->with('fop_team_conflicts', $teamResult['conflicts']);
            }

            return $redirect;
        });
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FopTask $fopTask)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'category' => ['sometimes', 'required', 'string', Rule::enum(TaskType::class)],
            'task_date' => ['sometimes', 'required', 'date'],
            'tugas' => ['sometimes', 'required', 'string', 'max:255'],
            'village_id' => ['sometimes', 'required', 'exists:villages,id'],
            'pop_id' => ['sometimes', 'required', 'exists:pops,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'issue' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'required', 'string', Rule::enum(FopTaskPriority::class)],
            'pending_reason' => ['nullable', 'required_if:status,pending', 'string', 'max:255'],
            'client_request_date' => ['nullable', 'required_if:status,pending', 'date'],
            'technicians' => ['sometimes', 'required', 'array', 'min:1'],
            'technicians.*' => ['exists:users,id'],
        ], [
            'pending_reason.required_if' => 'Alasan pending wajib diisi jika status Pending.',
            'client_request_date.required_if' => 'Tanggal request client wajib diisi jika status Pending.',
        ]);

        // RBAC: hanya user dgn fop_tasks.update_sensitive yang boleh ubah Tipe Task & Prioritas.
        if (!auth()->user()->hasPermission('fop_tasks.update_sensitive')) {
            unset($validated['category']);
            unset($validated['priority']);
        }

        // SRV/PSB terikat ke workflow Customer (List Pelanggan Gagal) — cancel
        // buat 2 tipe ini WAJIB lewat halaman Customer (CustomerSurveyController/
        // CustomerInstallationController::cancel()), BUKAN dari tiket FOP. Sama
        // persis aturan yang dipasang di TaskPolicy::cancel() buat halaman Task.
        $effectiveCategory = $validated['category'] ?? $fopTask->category?->value ?? $fopTask->category;
        if (
            ($validated['status'] ?? null) === TaskStatus::DIBATALKAN->value
            && in_array($effectiveCategory, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value], true)
        ) {
            abort(422, 'Task SRV/PSB gak bisa dibatalkan dari sini — batalkan lewat halaman Pelanggan (tab Survey/Pemasangan).');
        }

        return DB::transaction(function () use ($validated, $fopTask, $request) {
            $oldValues = $fopTask->load('technicians')->toArray();
            $oldTaskDate = $fopTask->task_date ? $fopTask->task_date->copy() : null;
            $oldTechnicianIds = collect($oldValues['technicians'] ?? [])->pluck('id')->sort()->values()->all();

            if (isset($validated['category'])) $fopTask->category = $validated['category'];
            if (isset($validated['task_date'])) $fopTask->task_date = $validated['task_date'];
            if (isset($validated['tugas'])) $fopTask->tugas = $validated['tugas'];
            if (array_key_exists('village_id', $validated)) $fopTask->village_id = $validated['village_id'];
            if (array_key_exists('pop_id', $validated)) $fopTask->pop_id = $validated['pop_id'];
            if (array_key_exists('customer_id', $validated)) $fopTask->customer_id = $validated['customer_id'];
            if (array_key_exists('issue', $validated)) $fopTask->issue = $validated['issue'];
            if (array_key_exists('notes', $validated)) $fopTask->notes = $validated['notes'];
            if (isset($validated['priority'])) $fopTask->priority = $validated['priority'];

            if (isset($validated['status'])) {
                $oldStatus = $fopTask->status->value ?? $fopTask->status;
                $fopTask->status = $validated['status'];

                if ($validated['status'] === TaskStatus::PENDING->value) {
                    $fopTask->pending_reason = $validated['pending_reason'] ?? $fopTask->pending_reason;
                    $fopTask->client_request_date = $validated['client_request_date'] ?? $fopTask->client_request_date;
                    $fopTask->cancelled_at = null;
                } elseif ($validated['status'] === TaskStatus::DIBATALKAN->value || $validated['status'] === TaskStatus::SELESAI->value) {
                    if ($oldStatus !== TaskStatus::DIBATALKAN->value && $validated['status'] === TaskStatus::DIBATALKAN->value) {
                        $fopTask->cancelled_at = now();
                    }
                    if ($validated['status'] === TaskStatus::SELESAI->value && $oldStatus === TaskStatus::DIBATALKAN->value) {
                        $fopTask->cancelled_at = null;
                    }
                    $fopTask->pending_reason = null;
                    $fopTask->client_request_date = null;
                } else { // draft/terjadwal/in_progress
                    $fopTask->pending_reason = null;
                    $fopTask->client_request_date = null;
                    $fopTask->cancelled_at = null;
                }
            }

            $fopTask->save();

            // Cancel dari sisi FOP harus nembus ke Task eksekusi teknisi juga —
            // kalau enggak, Task tetap Terjadwal/Sedang Dikerjakan di Riwayat
            // Task & /tasks-saya walau tiket FOP-nya udah Cancel.
            if (isset($validated['status'])
                && $validated['status'] === TaskStatus::DIBATALKAN->value
                && $oldStatus !== TaskStatus::DIBATALKAN->value
                && $fopTask->task_id
            ) {
                $linkedTask = $fopTask->task;
                if ($linkedTask && !in_array($linkedTask->status, [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])) {
                    app(TaskService::class)->cancel($linkedTask, auth()->user(), "Dibatalkan dari Task FOP {$fopTask->task_number}.");
                }
            }

            if ($request->has('technicians')) {
                $technicians = $validated['technicians'] ?? [];
                $newTechnicianIds = collect($technicians)->sort()->values()->all();

                if ($newTechnicianIds !== $oldTechnicianIds) {
                    $fopTask->team_id = null;
                    if ($fopTask->manual_override_at !== null) {
                        $fopTask->manual_override_at = null;
                    }
                    $fopTask->save();
                }

                $fopTask->technicians()->sync($technicians);

                if (!empty($technicians) || $fopTask->task_id) {
                    // Title dibuat polos — prefix "[Nama Team]" diisi oleh
                    // FopTaskTeamService::rebuildTeamsForDate() setelah ini (bukan ditebak di sini).
                    $taskData = [
                        'customer_id' => $fopTask->customer_id,
                        'pop_id' => $fopTask->pop_id,
                        'task_type' => $fopTask->category->value,
                        'title' => 'FOP: ' . $fopTask->tugas,
                        'description' => trim($fopTask->issue . "\n" . $fopTask->notes),
                        'team_member_ids' => $technicians,
                        'scheduled_at' => $fopTask->task_date,
                        'conflict_override' => true,
                    ];

                    if (!$fopTask->task_id && !empty($technicians)) {
                        $task = app(TaskService::class)->create($taskData, auth()->user());
                        $fopTask->task_id = $task->id;
                        $fopTask->save();
                    } elseif ($fopTask->task_id) {
                        app(TaskService::class)->update($fopTask->task, $taskData, auth()->user());
                    }
                }
            }

            $newValues = $fopTask->load('technicians')->toArray();

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'update', $oldValues, $newValues);
            }

            $conflicts = [];
            if ($fopTask->task_date) {
                if ($oldTaskDate && !$oldTaskDate->isSameDay($fopTask->task_date)) {
                    app(FopTaskTeamService::class)->rebuildTeamsForDate($oldTaskDate);
                }

                $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($fopTask->task_date));
                $technicianCount = $request->has('technicians') ? count($validated['technicians'] ?? []) : $fopTask->technicians()->count();
                if ($technicianCount > 1) {
                    $conflicts = $teamResult['conflicts'];
                }
            }

            if ($request->wantsJson()) {
                if (!empty($conflicts)) {
                    session()->flash('fop_team_conflicts', $conflicts);
                }
                return response()->json([
                    'success' => true,
                    'message' => "Task FOP {$fopTask->task_number} berhasil diperbarui.",
                    'task' => $fopTask,
                    'team_conflicts' => $conflicts,
                ]);
            }

            $redirect = redirect()->route('fop-tasks.index')
                ->with('success', "Task FOP {$fopTask->task_number} berhasil diperbarui.");

            if (!empty($conflicts)) {
                $redirect = $redirect->with('fop_team_conflicts', $conflicts);
            }

            return $redirect;
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FopTask $fopTask)
    {
        $this->authorizeAccess();

        return DB::transaction(function () use ($fopTask) {
            $oldValues = $fopTask->load('technicians')->toArray();

            // Jika merupakan task auto-sync (Survey / PSB), ubah status customer menjadi REJECTED agar tidak terbuat lagi secara otomatis
            if ($fopTask->customer_id && in_array($fopTask->category->value, \App\Enums\TaskType::autoOnlyValues(), true)) {
                $customer = $fopTask->customer;
                if ($customer && $customer->status !== 'rejected') {
                    try {
                        app(\App\Services\CustomerWorkflowService::class)->transition(
                            $customer,
                            \App\Enums\WorkflowTransition::REJECTED,
                            'Tiket FOP dihapus oleh FOP.'
                        );
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("Could not transition customer to rejected during FopTask deletion: " . $e->getMessage());
                    }
                }
            }

            $fopTask->technicians()->detach();
            $fopTask->delete();

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'delete', $oldValues, null);
            }

            return redirect()->route('fop-tasks.index')->with('success', "Task FOP {$fopTask->task_number} berhasil dihapus.");
        });
    }

    /**
     * Drop-in manual task ke Team (Skenario C2: solo task tanpa overlap; Skenario C3:
     * task yang narik teknisi dari >=2 team berbeda). FOP pilih team tujuan lewat
     * dropdown "+ Masukkan ke Team..." (team_id terisi), atau minta dibikinkan Team
     * baru dari roster task ini sendiri (team_id kosong).
     * Assignment ini di-pin lewat manual_override_at supaya gak ketimpa rebuild
     * otomatis berikutnya, sampai teknisi task ini diganti lagi lewat assignment biasa.
     */
    public function assignToTeam(Request $request, FopTask $fopTask)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:fop_task_teams,id'],
        ]);

        if (!$fopTask->task_date) {
            return back()->withErrors(['team_id' => 'Task ini belum punya tanggal jadwal.']);
        }

        return DB::transaction(function () use ($validated, $fopTask, $request) {
            $workDate = $fopTask->task_date->toDateString();

            if (!empty($validated['team_id'])) {
                $team = FopTaskTeam::findOrFail($validated['team_id']);

                if ($team->work_date->toDateString() !== $workDate) {
                    return back()->withErrors([
                        'team_id' => "Team \"{$team->name}\" bukan untuk tanggal {$workDate}, gak bisa dipakai buat task ini.",
                    ]);
                }
            } else {
                $team = FopTaskTeam::create([
                    'name' => app(FopTaskTeamService::class)->nextTeamName($fopTask->task_date->copy()),
                    'work_date' => $workDate,
                    'created_by' => auth()->id(),
                ]);
                $team->members()->sync($fopTask->technicians->pluck('id'));
            }

            $oldValues = $fopTask->toArray();
            $fopTask->team_id = $team->id;
            $fopTask->manual_override_at = now();
            $fopTask->save();

            // Bersihkan teknisi dari task lain di tanggal yang sama yang berada di team yang berbeda
            foreach ($fopTask->technicians as $tech) {
                $otherTasks = FopTask::where('id', '!=', $fopTask->id)
                    ->whereDate('task_date', $workDate)
                    ->whereHas('technicians', fn ($q) => $q->where('users.id', $tech->id))
                    ->whereNotNull('team_id')
                    ->where('team_id', '!=', $team->id)
                    ->get();

                foreach ($otherTasks as $otherTask) {
                    $otherTask->technicians()->detach($tech->id);

                    // Update execution task (tabel tasks) jika ada
                    if ($otherTask->task_id && $otherTask->task) {
                        $remainingTechs = $otherTask->technicians()->pluck('users.id')->all();
                        app(TaskService::class)->update($otherTask->task, [
                            'team_member_ids' => $remainingTechs,
                        ], auth()->user());
                    }
                }
            }

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'assign_to_team', $oldValues, $fopTask->toArray());
            }

            $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate($fopTask->task_date->copy());
            $team->refresh();

            $conflicts = [];

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Task berhasil dimasukkan ke Team \"{$team->name}\".",
                    'team_conflicts' => $conflicts,
                ]);
            }

            return redirect()->route('fop-tasks.index')
                ->with('success', "Task berhasil dimasukkan ke Team \"{$team->name}\".");
        });
    }

    /**
     * Switch Teknisi antar Team (1 payload sekali submit, atomic) — sesuai kebutuhan poin 2.
     * Mindahin `technician_id` dari Task asal ke Task tujuan, DAN wajib isi `replacement_technician_id`
     * buat gantiin dia di Task asal — supaya Task asal gak pernah kosong teknisi. Cuma boleh
     * intra-hari (task_date sama). Reuse conflict-check "in_progress" yang sama dengan
     * TaskService::start() — bukan bikin conflict-check baru.
     */
    public function switchTechnician(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:users,id'],
            'from_task_id' => ['required', 'integer', 'exists:fop_tasks,id'],
            'to_task_id' => ['required', 'integer', 'different:from_task_id', 'exists:fop_tasks,id'],
            'replacement_technician_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $fromTask = FopTask::with('technicians')->findOrFail($validated['from_task_id']);
        $toTask = FopTask::with('technicians')->findOrFail($validated['to_task_id']);

        if (!$fromTask->technicians->contains('id', $validated['technician_id'])) {
            return $this->switchTechnicianError($request, 'technician_id', 'Teknisi yang dipilih bukan anggota Task asal.');
        }

        if ($toTask->technicians->contains('id', $validated['technician_id'])) {
            return $this->switchTechnicianError($request, 'technician_id', 'Teknisi tersebut sudah ada di Task tujuan.');
        }

        if ((int) $validated['replacement_technician_id'] === (int) $validated['technician_id']) {
            return $this->switchTechnicianError($request, 'replacement_technician_id', 'Pengganti tidak boleh teknisi yang sama dengan yang dipindah.');
        }

        if (!$fromTask->task_date || !$toTask->task_date || !$fromTask->task_date->isSameDay($toTask->task_date)) {
            return $this->switchTechnicianError($request, 'to_task_id', 'Switch teknisi cuma boleh intra-hari (tanggal Task asal & tujuan harus sama). Task beda hari, pakai jalur Pending/reschedule.');
        }

        // Reuse conflict-check "in_progress" yang sama dengan TaskService::start() —
        // pengganti gak boleh ditarik kalau lagi in_progress di task lain.
        $replacementBusyOn = Task::where('status', TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $validated['replacement_technician_id']))
            ->first();

        if ($replacementBusyOn) {
            return $this->switchTechnicianError(
                $request,
                'replacement_technician_id',
                "Pengganti sedang mengerjakan task lain yang in_progress [{$replacementBusyOn->task_number}]. Selesaikan/pending-kan dulu sebelum jadi pengganti."
            );
        }

        return DB::transaction(function () use ($validated, $fromTask, $toTask, $request) {
            $oldFromValues = $fromTask->toArray();
            $oldToValues = $toTask->toArray();

            $newFromTechs = $fromTask->technicians->pluck('id')
                ->reject(fn ($id) => (int) $id === (int) $validated['technician_id'])
                ->push((int) $validated['replacement_technician_id'])
                ->unique()
                ->values()
                ->all();

            $newToTechs = $toTask->technicians->pluck('id')
                ->push((int) $validated['technician_id'])
                ->unique()
                ->values()
                ->all();

            // Sengaja gak nge-null-in team_id di sini (beda dari update() biasa) — biar
            // FopTaskTeamService::rebuildTeamsForDate() masih bisa pakai team_id lama sebagai
            // anchor via $existingTeamOf kalau salah satu task nyusut jadi solo (lihat
            // analisa-sync-execution-task.md bagian 1). manual_override_at tetap dilepas
            // karena assignment manual lama udah gak relevan setelah teknisinya diganti.
            foreach ([$fromTask, $toTask] as $task) {
                $task->manual_override_at = null;
                $task->save();
            }

            $fromTask->technicians()->sync($newFromTechs);
            $toTask->technicians()->sync($newToTechs);

            $this->syncSwitchedExecutionTask($fromTask, $newFromTechs);
            $this->syncSwitchedExecutionTask($toTask, $newToTechs);

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fromTask, 'switch_technician_out', $oldFromValues, $fromTask->fresh('technicians')->toArray());
                AuditLog::log($toTask, 'switch_technician_in', $oldToValues, $toTask->fresh('technicians')->toArray());
            }

            $this->notifySwitchedTechnician(
                (int) $validated['technician_id'],
                "Anda dipindahkan dari task \"{$fromTask->tugas}\" ke task \"{$toTask->tugas}\".",
            );
            $this->notifySwitchedTechnician(
                (int) $validated['replacement_technician_id'],
                "Anda ditugaskan sebagai pengganti pada task \"{$fromTask->tugas}\".",
            );

            // from/to task_date SELALU sama (udah divalidasi intra-hari di atas) — cukup
            // rebuild SEKALI. Manggil rebuildTeamsForDate() 2x buat tanggal yang sama bikin
            // pass ke-2 gak nemu lagi konflik/team yang udah kehapus sama cleanup di pass
            // pertama (lihat analisa-sync-execution-task.md), teknisi jadi ke-merge salah.
            $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate($fromTask->task_date->copy());
            $conflicts = $teamResult['conflicts'];

            if ($request->wantsJson()) {
                if (!empty($conflicts)) {
                    session()->flash('fop_team_conflicts', $conflicts);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Switch teknisi berhasil.',
                    'team_conflicts' => $conflicts,
                ]);
            }

            return redirect()->route('fop-tasks.index')
                ->with('success', 'Switch teknisi berhasil.')
                ->with('fop_team_conflicts', $conflicts);
        });
    }

    /**
     * Sync roster teknisi Task eksekusi (`tasks`/`task_team`) setelah switch.
     * SENGAJA gak lewat `TaskService::update()` (beda dari `assignToTeam()`) — method itu
     * manggil `syncToFopTask()` di ujungnya yang UNCONDITIONAL manggil balik
     * `rebuildTeamsForDate()`. Kalau dipanggil 2x (fromTask & toTask), itu jadi 2 rebuild
     * pass TAMBAHAN yang gak diminta, masing-masing liat state transisi yang beda —
     * rebuild pass yang nulify task konflik (C3) bisa ke-cleanup (team kandidat kehapus)
     * SEBELUM pass berikutnya sempet baca datanya, hasilnya teknisi ke-merge ke team yang
     * salah. Sync pivot `task_team` LANGSUNG di sini, roster doang, gak mancing rebuild lain.
     */
    private function syncSwitchedExecutionTask(FopTask $fopTask, array $technicianIds): void
    {
        if (!$fopTask->task_id || !$fopTask->task) {
            return;
        }

        $execTask = $fopTask->task;
        $execTask->teamMembers()->delete();
        foreach ($technicianIds as $index => $userId) {
            $execTask->teamMembers()->create([
                'user_id' => $userId,
                'role_in_task' => $index === 0 ? 'lead' : 'teknisi',
            ]);
        }
    }

    /**
     * Notifikasi in-app buat teknisi yang kena switch (keluar atau masuk).
     */
    private function notifySwitchedTechnician(int $userId, string $message): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $user->notify(new AppNotification(
            title: 'Switch Teknisi',
            message: $message,
            actionUrl: route('fop-tasks.index'),
            type: 'info'
        ));
    }

    /**
     * Response error konsisten (JSON atau redirect+errors) buat validasi switchTechnician().
     */
    private function switchTechnicianError(Request $request, string $field, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->withErrors([$field => $message]);
    }

    /**
     * Konflik team (Skenario C3) yang lagi nunggu keputusan FOP — dihitung LANGSUNG dari
     * state DB (task multi-teknisi tapi `team_id` null), bukan cuma dari session flash.
     * Jadi kalau modal konfliknya gak sengaja ke-close atau halaman di-refresh, konfliknya
     * tetap kebaca lagi begitu index() dipanggil ulang — gak ilang/hangus kayak flash.
     */
    private function currentTeamConflicts(): array
    {
        $conflictDates = FopTask::whereNotIn('status', [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])
            ->whereNull('team_id')
            ->has('technicians', '>=', 2)
            ->get(['task_date'])
            ->map(fn (FopTask $t) => $t->task_date->toDateString())
            ->unique();

        $conflicts = collect(session('fop_team_conflicts', []));

        foreach ($conflictDates as $date) {
            $result = app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($date));
            $conflicts = $conflicts->merge($result['conflicts']);
        }

        return $conflicts->unique('task_id')->values()->all();
    }

    /**
     * Authorize access to FOP task resource.
     */
    protected function authorizeAccess()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        // owner has full access
        if ($user->hasRole('owner') || $user->hasFullAccess()) {
            return;
        }

        // check permission
        if ($user->hasPermission('fop_tasks.view') || $user->hasPermission('fop_tasks.create') || $user->hasPermission('fop_tasks.update') || $user->hasPermission('fop_tasks.delete')) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses ke modul ini.');
    }

    /**
     * Display a listing of completed and cancelled FOP tasks.
     */
    public function history(Request $request)
    {
        $this->authorizeAccess();

        $query = FopTask::with(['village', 'technicians', 'task:id,status,report_deferred'])
            ->whereIn('status', [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])
            ->orderBy('updated_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('task_number', 'like', "%{$search}%")
                  ->orWhere('tugas', 'like', "%{$search}%")
                  ->orWhere('issue', 'like', "%{$search}%");
            });
        }

        // Dropdown filters
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $statusVal = $request->input('status');
            if (in_array($statusVal, [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value], true)) {
                $query->where('status', $statusVal);
            } else {
                $query->whereRaw('1=0');
            }
        }


        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('village_id')) {
            $query->where('village_id', $request->input('village_id'));
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }

        $fopTasks = $query->with('team:id,name')->paginate(20)->withQueryString();

        // Get villages for area selector
        $villages = Village::orderBy('name', 'asc')->get();

        // Get POPs for cabang selector
        $pops = Pop::orderBy('name', 'asc')->get();

        // Get technicians for assignee selector
        $technicians = User::whereHas('role', function($q) {
            $q->where('code', 'teknisi');
        })->where('status', 'active')->orderBy('name', 'asc')->get();

        // Categories mapping using Enum
        $categories = collect(TaskType::cases())->mapWithKeys(function ($category) {
            return [$category->value => $category->label()];
        })->toArray();

        // Tipe task yang boleh dipakai
        $manualCategories = collect($categories)
            ->except(TaskType::autoOnlyValues())
            ->toArray();

        $canEditFopTaskType = auth()->user()->hasPermission('fop_tasks.update_sensitive');

        // Daftar Team
        $teams = FopTaskTeam::with(['members:id,name', 'fopTasks:id,team_id,status', 'fopTasks.technicians:id,name'])
            ->orderByDesc('work_date')
            ->limit(50)
            ->get()
            ->map(function (FopTaskTeam $team) {
                $activeCount = $team->fopTasks->filter(
                    fn ($t) => !in_array($t->status->value, [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                )->count();

                $workload = $team->fopTasks
                    ->flatMap(fn ($t) => $t->technicians)
                    ->countBy('id');

                return [
                    'id'         => $team->id,
                    'name'       => $team->name,
                    'work_date'  => $team->work_date->format('Y-m-d'),
                    'members'    => $team->members->map(fn ($m) => [
                        'id'    => $m->id,
                        'name'  => $m->name,
                        'count' => $workload->get($m->id, 0),
                    ])->values(),
                    'task_count' => $team->fopTasks->count(),
                    'is_active'  => $activeCount > 0,
                ];
            });

        return view('fop_tasks.history', compact('fopTasks', 'villages', 'pops', 'technicians', 'categories', 'manualCategories', 'canEditFopTaskType', 'teams'));
    }

    /**
     * Halaman Detail Riwayat (Task 10) — detail task, laporan (baca langsung dari
     * CustomerSurvey/TaskMaintenance/CustomerTechnicalDetail sesuai task_type,
     * BUKAN duplikasi ke task_reports), histori status+alasan, durasi/SLA per
     * siklus (dual-cycle).
     */
    public function showHistory(FopTask $fopTask)
    {
        $this->authorizeAccess();

        $fopTask->load([
            'village',
            'pop',
            'customer',
            'technicians',
            'team:id,name',
            'statusHistories.changedByUser:id,name',
            'task.report',
            'task.teamMembers.user:id,name',
            'task.maintenanceReport',
        ]);

        $survey = null;
        $installation = null;
        $technicalDetail = null;

        if ($fopTask->customer) {
            if ($fopTask->category === TaskType::SURVEY) {
                $survey = $fopTask->customer->surveys()->latest()->first();
            } elseif ($fopTask->category === TaskType::PEMASANGAN) {
                $installation = $fopTask->customer->installations()->latest()->first();
                $technicalDetail = $fopTask->customer->customerTechnicalDetail;
            }
        }

        $maintenance = $fopTask->task?->maintenanceReport;

        return view('fop_tasks.history_detail', compact('fopTask', 'survey', 'installation', 'technicalDetail', 'maintenance'));
    }

    /**
     * Auto-sync customers to FOP Task and calculate priority dynamically based on SLA.
     */
    private function autoSyncAndCalculatePriority()
    {
        // --- 1. Auto-Sync Survey ---
        $surveyCustomers = Customer::whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
            ->whereDoesntHave('fopTasks', function ($q) {
                $q->where('category', 'Survey')->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value]);
            })->get();

        foreach ($surveyCustomers as $c) {
            $taskNumber = $this->generateTaskNumber();

            FopTask::create([
                'task_number' => $taskNumber,
                'task_date' => now(),
                'category' => TaskType::SURVEY,
                'tugas' => 'Survey Pelanggan: ' . $c->full_name,
                'village_id' => $c->village_id ?? 1,
                'pop_id' => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'issue' => 'Auto-Sync dari antrean survey',
                'status' => TaskStatus::DRAFT,
                'priority' => FopTaskPriority::MEDIUM, // will be recalculated below
            ]);
        }

        // --- 2. Auto-Sync Installation ---
        $installCustomers = Customer::whereIn('status', ['waiting_installation', 'waiting_installations', 'surveyed'])
            ->whereDoesntHave('fopTasks', function ($q) {
                $q->where('category', 'PSB')->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value]);
            })->get();

        foreach ($installCustomers as $c) {
            $taskNumber = $this->generateTaskNumber();

            FopTask::create([
                'task_number' => $taskNumber,
                'task_date' => now(),
                'category' => TaskType::PEMASANGAN,
                'tugas' => 'Pemasangan Baru: ' . $c->full_name,
                'village_id' => $c->village_id ?? 1,
                'pop_id' => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'issue' => 'Auto-Sync dari antrean pemasangan',
                'status' => TaskStatus::DRAFT,
                'priority' => FopTaskPriority::MEDIUM, // will be recalculated below
            ]);
        }

        // --- 3. Dynamic Priority Update ---
        // Menggunakan eager loading (N+1 safe) agar query tidak dilooping
        $activeTasks = FopTask::with(['customer.tasks' => function($q) {
            $q->where('task_type', \App\Enums\TaskType::SURVEY->value)
              ->where('status', 'selesai')
              ->orderByDesc('completed_at');
        }])
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->whereIn('category', ['Survey', 'PSB'])
            ->whereNotNull('customer_id')
            ->get();
            
        $now = Carbon::now();

        foreach ($activeTasks as $task) {
            $customer = $task->customer;
            if (!$customer) continue;

            $totalSeconds = 0;
            $remainSeconds = 0;

            if ($task->category === TaskType::SURVEY) {
                $deadline = Carbon::parse($customer->created_at)->addDay();
                $totalSeconds = 86400; // 1x24 hours
                $remainSeconds = -$deadline->diffInSeconds($now, false);
            } elseif ($task->category === TaskType::PEMASANGAN) {
                // Ambil task survey dari relation yang sudah di-eager load (bukan query baru)
                $surveyTask = $customer->tasks->first();
                
                $refDate = $surveyTask?->completed_at ? Carbon::parse($surveyTask->completed_at) : Carbon::parse($customer->updated_at);
                $deadline = $refDate->addDays(3);
                $totalSeconds = 259200; // 3x24 hours
                $remainSeconds = -$deadline->diffInSeconds($now, false);
            }

            if ($totalSeconds > 0) {
                $percentage = ($remainSeconds / $totalSeconds) * 100;
                
                $newPriority = FopTaskPriority::LOW;
                if ($percentage < 0) {
                    $newPriority = FopTaskPriority::URGENT;
                } elseif ($percentage <= 25) {
                    $newPriority = FopTaskPriority::HIGH;
                } elseif ($percentage <= 50) {
                    $newPriority = FopTaskPriority::MEDIUM;
                }
                
                if ($task->priority !== $newPriority) {
                    $task->update(['priority' => $newPriority]);
                }
            }
        }
    }

    /**
     * Generate a unique sequential task number for the current year.
     *
     * Nomor urut dihitung di PHP (bukan `ORDER BY` SQL raw kayak
     * `SUBSTRING_INDEX`) biar portable — jalan di MySQL (prod) maupun SQLite
     * (test env), bukan cuma di salah satu driver.
     */
    private function generateTaskNumber()
    {
        $year = date('Y');
        $lastNum = FopTask::where('task_number', 'like', "TFOP-{$year}-%")
            ->pluck('task_number')
            ->map(fn ($taskNumber) => (int) substr($taskNumber, strrpos($taskNumber, '-') + 1))
            ->max() ?? 0;

        return sprintf('TFOP-%s-%04d', $year, $lastNum + 1);
    }
}
