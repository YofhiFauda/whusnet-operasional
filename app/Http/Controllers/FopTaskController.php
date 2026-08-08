<?php

namespace App\Http\Controllers;

use App\Enums\FopTaskPriority;
use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Events\FopTaskUpdated;
use App\Events\TaskScheduled;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\FopTaskStatusHistory;
use App\Models\FopTaskTeam;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppNotification;
use App\Services\CustomerWorkflowService;
use App\Services\EffectiveAccessService;
use App\Services\FopTaskTeamService;
use App\Services\TaskService;
use App\Support\ReasonValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

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
                    ->where('status', TaskStatus::SELESAI->value)
                    ->select('id', 'customer_id', 'task_type', 'status', 'completed_at');
            },
            // MTN/C-REQ yang asalnya dari Ticketing — dipakai modal Edit biar
            // form-nya nampilin CID/data pelanggan yang sama kayak /tickets,
            // bukan form generik. pop_id/status/distribution_id WAJIB ikut
            // ke-select — dipakai Customer::getDisplayIdAttribute() (lihat
            // catatan yang sama di TicketController::index()).
            'ticket:id,ticket_number,fop_task_id,customer_id,detail_keluhan,catatan_teknis,customer_name,customer_address,customer_phone,customer_odp,customer_package,customer_device,customer_latitude,customer_longitude',
            'ticket.customer:id,cid,customer_code,pop_id,status,distribution_id',
            'ticket.customer.pop:id,name,cid_prefix',
        ])
            ->applyUserScope()
            ->whereNotIn('status', [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])
            ->orderByRaw('CASE WHEN client_request_date IS NOT NULL AND client_request_date >= ? THEN 1 ELSE 0 END', [now()->addDay()->toDateString()])
            ->orderByRaw(
                'CASE priority '.self::priorityOrderCaseSql().' ELSE 5 END',
                self::priorityOrderBindings()
            )
            ->orderByRaw(
                'CASE WHEN category IN ('.implode(',', array_fill(0, count(TaskType::autoOnlyValues()), '?')).') THEN created_at END ASC',
                TaskType::autoOnlyValues()
            )
            ->orderByRaw(
                'CASE WHEN category NOT IN ('.implode(',', array_fill(0, count(TaskType::autoOnlyValues()), '?')).') THEN created_at END DESC',
                TaskType::autoOnlyValues()
            );

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
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

        // display_id itu accessor (bukan kolom) — gak otomatis ikut kebawa pas
        // di-json_encode() buat modal Edit, jadi WAJIB di-append manual per baris.
        foreach ($fopTasks as $fopTask) {
            $fopTask->ticket?->customer?->append('display_id');
        }

        // Get villages for area selector
        $villages = Village::orderBy('name', 'asc')->get();

        // Get POPs for cabang selector
        $pops = Pop::orderBy('name', 'asc')->get();

        // Get technicians for assignee selector
        $technicians = User::whereHas('role', function ($q) {
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
                    fn ($t) => ! in_array($t->status->value, [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                )->count();

                $workload = $team->fopTasks
                    ->flatMap(fn ($t) => $t->technicians)
                    ->countBy('id');

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'work_date' => $team->work_date->format('Y-m-d'),
                    'members' => $team->members->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'count' => $workload->get($m->id, 0),
                    ])->values(),
                    'task_count' => $team->fopTasks->count(),
                    'is_active' => $activeCount > 0,
                ];
            });

        $teamConflicts = $this->currentTeamConflicts();

        // Daftar task ringkas buat dropdown "Task Tujuan" di modal Switch Teknisi — query
        // TERPISAH dari $fopTasks (yang kena filter/paginate dari request), soalnya "Task
        // Tujuan" harus nampilin SEMUA task aktif (lintas team, lintas filter/halaman
        // berapapun), bukan cuma yang lagi kebetulan tampil di tabel.
        // Hanya kolom yang benar-benar dipakai map di bawah, dan hanya task yang
        // punya task_date: switchTeam() menolak task tanpa task_date (team tujuan
        // wajib se-tanggal), jadi task tanpa tanggal tidak pernah bisa jadi target
        // dan cuma membebani JSON yang di-render ke halaman.
        $switchTargetTasks = FopTask::applyUserScope()
            ->whereNotIn('status', [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])
            ->whereNotNull('task_date')
            ->with('technicians:id,name')
            ->get(['id', 'task_number', 'tugas', 'task_date'])
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
     * Fragment 3 <td> (Teknisi/Team/Status) satu baris — dipanggil dari
     * fop_tasks/index.blade.php saat Echo nangkep App\Events\FopTaskUpdated,
     * biar baris di layar FOP lain ikut sinkron tanpa reload
     * (docs/plan/analisa-realtime-spa-operasional.md §2.2 no. 13). Task yang
     * udah keluar cakupan papan (selesai/dibatalkan — index() nge-exclude
     * lewat whereNotIn) balikin 204, biar baris dihapus di klien.
     */
    public function row(FopTask $fopTask)
    {
        $this->authorizeAccess();
        $this->authorizeFopTaskScope($fopTask);

        if (in_array($fopTask->status, [TaskStatus::SELESAI, TaskStatus::DIBATALKAN], true)) {
            return response('', 204);
        }

        $fopTask->load([
            'technicians',
            'team:id,name',
            'task:id,scheduled_at,status,report_deferred,fop_review_status',
            'ticket:id',
        ]);

        return view('fop_tasks.partials.row-cells', ['task' => $fopTask]);
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
            'pending_reason' => ReasonValidationRule::requiredIf('status', 'pending', 255),
            'client_request_date' => ['nullable', 'required_if:status,pending', 'date'],
            'technicians' => ['required', 'array', 'min:1'],
            'technicians.*' => ['exists:users,id'],
        ], [
            'pending_reason.required_if' => 'Alasan pending wajib diisi jika status Pending.',
            'client_request_date.required_if' => 'Tanggal request client wajib diisi jika status Pending.',
        ]);

        return DB::transaction(function () use ($validated) {
            $taskNumber = $this->generateTaskNumber();

            $fopTask = new FopTask;
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

            if (! empty($validated['technicians'])) {
                $technicians = $validated['technicians'];
                $fopTask->technicians()->sync($technicians);

                // Title dibuat polos dulu — prefix "[Nama Team]" diisi oleh
                // FopTaskTeamService::rebuildTeamsForDate() begitu team-nya kebentuk
                // (dipanggil beberapa baris di bawah), bukan ditebak di sini.
                //
                // description CUMA dari issue, JANGAN digabung sama fop_task->notes —
                // notes itu pointer sistem pendek (mis. "Ticket TKT-… — dikirim oleh
                // …", lihat TicketService::composeFopNotes()), bukan konten buat
                // dibaca teknisi. Digabung bikin box "Issue / Keluhan" di halaman
                // Task teknisi (tasks/show.blade.php) kecampur metadata sistem.
                $taskData = [
                    'customer_id' => $fopTask->customer_id,
                    'pop_id' => $fopTask->pop_id,
                    'task_type' => $fopTask->category->value,
                    'title' => 'FOP: '.$fopTask->tugas,
                    'description' => $fopTask->issue,
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
        $this->authorizeFopTaskScope($fopTask);

        // Task 14 — record existing Survey/Pemasangan cuma dibolehin submit balik
        // category yang sama (hidden input di form tetap ngirim nilai existing biar
        // field lain di form yang sama tetap bisa disave), TAPI gak boleh diubah jadi
        // tipe lain sama sekali. Kunci customer_id/pop_id/village_id-nya sendiri
        // dicek di bawah (setelah validasi), berbasis diff, bukan cuma presence.
        $lockedExistingCategory = $fopTask->category?->value;
        $categoryAllowedValues = in_array($lockedExistingCategory, TaskType::autoOnlyValues(), true)
            ? array_unique(array_merge(TaskType::manualValues(), [$lockedExistingCategory]))
            : TaskType::manualValues();

        $validated = $request->validate([
            'category' => ['sometimes', 'required', 'string', Rule::in($categoryAllowedValues)],
            'task_date' => ['sometimes', 'required', 'date'],
            'tugas' => ['sometimes', 'required', 'string', 'max:255'],
            'village_id' => ['sometimes', 'required', 'exists:villages,id'],
            'pop_id' => ['sometimes', 'required', 'exists:pops,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'issue' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'required', 'string', Rule::enum(FopTaskPriority::class)],
            'pending_reason' => ReasonValidationRule::requiredIf('status', 'pending', 255),
            'client_request_date' => ['nullable', 'required_if:status,pending', 'date'],
            'cancel_reason' => ReasonValidationRule::requiredIf('status', 'dibatalkan', 500),
            'technicians' => ['sometimes', 'required', 'array', 'min:1'],
            'technicians.*' => ['exists:users,id'],
        ], [
            'pending_reason.required_if' => 'Alasan pending wajib diisi jika status Pending.',
            'client_request_date.required_if' => 'Tanggal request client wajib diisi jika status Pending.',
            'cancel_reason.required_if' => 'Alasan pembatalan wajib diisi.',
        ]);

        // Task 14 — record existing Survey/Pemasangan gak boleh diubah sama sekali di
        // category/customer_id/pop_id/village_id, terlepas dari permission
        // fop_tasks.update_sensitive (Kebutuhan poin 14 & 15). Dicek berbasis DIFF
        // (bukan cuma presence) karena form tetap ngirim balik nilai existing lewat
        // hidden input buat field lain yang gak dikunci tetap bisa disave.
        if (in_array($lockedExistingCategory, TaskType::autoOnlyValues(), true)) {
            $lockedFieldChanged = (array_key_exists('category', $validated) && $validated['category'] !== $lockedExistingCategory)
                || (array_key_exists('customer_id', $validated) && (string) $validated['customer_id'] !== (string) $fopTask->customer_id)
                || (array_key_exists('pop_id', $validated) && (string) $validated['pop_id'] !== (string) $fopTask->pop_id)
                || (array_key_exists('village_id', $validated) && (string) $validated['village_id'] !== (string) $fopTask->village_id);

            if ($lockedFieldChanged) {
                abort(422, 'Task Survey/Pemasangan tidak bisa diedit dari sini — hanya bisa diubah lewat alur Registrasi Pelanggan.');
            }
        }

        // RBAC: hanya user dgn fop_tasks.update_sensitive yang boleh ubah Tipe Task & Prioritas.
        if (! auth()->user()->hasPermission('fop_tasks.update_sensitive')) {
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
            && in_array($effectiveCategory, [TaskType::SURVEY->value, TaskType::PEMASANGAN->value], true)
        ) {
            abort(422, 'Task SRV/PSB gak bisa dibatalkan dari sini — batalkan lewat halaman Pelanggan (tab Survey/Pemasangan).');
        }

        // Task 12 — cancel (task_type NON-SRV/PSB, yang di atas udah diblok) butuh
        // permission eksplisit `fop_tasks.cancel`, bukan cuma `fop_tasks.update` biasa.
        if (
            ($validated['status'] ?? null) === TaskStatus::DIBATALKAN->value
            && ! auth()->user()->hasPermission('fop_tasks.cancel')
        ) {
            abort(403, 'Anda tidak memiliki hak akses untuk membatalkan Task FOP.');
        }

        return DB::transaction(function () use ($validated, $fopTask, $request) {
            $oldValues = $fopTask->load('technicians')->toArray();
            $oldTaskDate = $fopTask->task_date ? $fopTask->task_date->copy() : null;
            $oldTechnicianIds = collect($oldValues['technicians'] ?? [])->pluck('id')->sort()->values()->all();

            // Ditangkap SEBELUM field apa pun diubah — dipakai belakangan buat
            // deteksi transisi Draft→Terjadwal akibat assign teknisi (lihat blok
            // technicians di bawah). $oldStatus di blok status hanya keisi kalau
            // request kirim 'status', sedangkan modal Edit SELALU ngirim 'status'
            // = nilai lama (form itu gak punya pilihan ubah status manual), jadi
            // gak bisa dipakai buat bedain "status beneran mau diubah user" vs
            // "cuma echo balik nilai existing".
            $originalStatus = $fopTask->status instanceof TaskStatus ? $fopTask->status->value : $fopTask->status;

            if (isset($validated['category'])) {
                $fopTask->category = $validated['category'];
            }
            if (isset($validated['task_date'])) {
                $fopTask->task_date = $validated['task_date'];
            }
            if (isset($validated['tugas'])) {
                $fopTask->tugas = $validated['tugas'];
            }
            if (array_key_exists('village_id', $validated)) {
                $fopTask->village_id = $validated['village_id'];
            }
            if (array_key_exists('pop_id', $validated)) {
                $fopTask->pop_id = $validated['pop_id'];
            }
            if (array_key_exists('customer_id', $validated)) {
                $fopTask->customer_id = $validated['customer_id'];
            }
            if (array_key_exists('issue', $validated)) {
                $fopTask->issue = $validated['issue'];
            }
            if (array_key_exists('notes', $validated)) {
                $fopTask->notes = $validated['notes'];
            }
            if (isset($validated['priority'])) {
                $fopTask->priority = $validated['priority'];
            }

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
                        $fopTask->cancel_reason = $validated['cancel_reason'] ?? null;
                    }
                    if ($validated['status'] === TaskStatus::SELESAI->value && $oldStatus === TaskStatus::DIBATALKAN->value) {
                        $fopTask->cancelled_at = null;
                        $fopTask->cancel_reason = null;
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

            // Riwayat status sisi FOP buat cancel manual HARUS ditulis di sini.
            // TaskObserver — yang biasanya nulis fop_task_status_history — udah
            // keburu early-return begitu FopTask berstatus `dibatalkan`
            // (TaskObserver.php baris 42, guard biar cancel manual gak
            // ke-overwrite sync otomatis), jadi sebelum ini pembatalan gak
            // ninggalin jejak sama sekali di riwayat, termasuk buat tiket yang
            // belum punya Task eksekusi (tiket Ticketing yang masih Draft).
            if (isset($validated['status'])
                && $validated['status'] === TaskStatus::DIBATALKAN->value
                && $oldStatus !== TaskStatus::DIBATALKAN->value
            ) {
                FopTaskStatusHistory::create([
                    'fop_task_id' => $fopTask->id,
                    'from_status' => $oldStatus,
                    'to_status' => TaskStatus::DIBATALKAN->value,
                    'changed_by' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }

            // Cancel dari sisi FOP harus nembus ke Task eksekusi teknisi juga —
            // kalau enggak, Task tetap Terjadwal/Sedang Dikerjakan di Riwayat
            // Task & /tasks-saya walau tiket FOP-nya udah Cancel.
            if (isset($validated['status'])
                && $validated['status'] === TaskStatus::DIBATALKAN->value
                && $oldStatus !== TaskStatus::DIBATALKAN->value
                && $fopTask->task_id
            ) {
                $linkedTask = $fopTask->task;
                if ($linkedTask && ! in_array($linkedTask->status, [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])) {
                    // Cascade ke Task eksekusi tetap harus nembus policy, bukan
                    // main panggil TaskService langsung. Guard SRV/PSB di atas
                    // baca `FopTask.category`; yang dibatalin di sini `Task`,
                    // jadi tipenya dicek ulang terhadap Task-nya sendiri —
                    // lihat TaskPolicy::cancelViaFopTask().
                    $this->authorize('cancelViaFopTask', $linkedTask);

                    app(TaskService::class)->cancel($linkedTask, auth()->user(), $validated['cancel_reason'] ?? "Dibatalkan dari Task FOP {$fopTask->task_number}.");
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

                // BUG FIX: tiket yang asalnya Draft (biasanya dari Ticketing —
                // belum ada teknisi/Task eksekusi) dan baru di-assign teknisi di
                // SINI (lewat modal Edit di tabel, bukan form Create) sebelumnya
                // status-nya gak pernah ikut naik ke Terjadwal — modal Edit
                // ngirim 'status' hidden = nilai lama ('draft'), dan blok status
                // di atas cuma nulis ulang nilai yang sama. Akibatnya Task
                // eksekusi kebuat + teknisi ke-assign beneran, tapi
                // fop_tasks.status nyangkut 'draft' selamanya, dan Ticket yang
                // nyambung ke tiket ini macet di bucket "Ticket Masuk"
                // (Ticket::scopeInBucket() baca kolom ini apa adanya).
                if ($originalStatus === TaskStatus::DRAFT->value
                    && $fopTask->status === TaskStatus::DRAFT
                    && ! empty($technicians)
                ) {
                    $fopTask->status = TaskStatus::TERJADWAL;
                    $fopTask->save();

                    FopTaskStatusHistory::create([
                        'fop_task_id' => $fopTask->id,
                        'from_status' => TaskStatus::DRAFT->value,
                        'to_status' => TaskStatus::TERJADWAL->value,
                        'changed_by' => auth()->id(),
                        'changed_at' => now(),
                    ]);
                }

                if (! empty($technicians) || $fopTask->task_id) {
                    // Title dibuat polos — prefix "[Nama Team]" diisi oleh
                    // FopTaskTeamService::rebuildTeamsForDate() setelah ini (bukan ditebak di sini).
                    //
                    // description CUMA dari issue — lihat komentar senada di store()
                    // di atas soal kenapa fop_task->notes gak boleh ikut digabung.
                    $taskData = [
                        'customer_id' => $fopTask->customer_id,
                        'pop_id' => $fopTask->pop_id,
                        'task_type' => $fopTask->category->value,
                        'title' => 'FOP: '.$fopTask->tugas,
                        'description' => $fopTask->issue,
                        'team_member_ids' => $technicians,
                        'scheduled_at' => $fopTask->task_date,
                        'conflict_override' => true,
                    ];

                    if (! $fopTask->task_id && ! empty($technicians)) {
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
                if ($oldTaskDate && ! $oldTaskDate->isSameDay($fopTask->task_date)) {
                    app(FopTaskTeamService::class)->rebuildTeamsForDate($oldTaskDate);
                }

                $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($fopTask->task_date));
                $technicianCount = $request->has('technicians') ? count($validated['technicians'] ?? []) : $fopTask->technicians()->count();
                if ($technicianCount > 1) {
                    $conflicts = $teamResult['conflicts'];
                }
            }

            FopTaskUpdated::dispatch($fopTask->fresh());

            if ($request->wantsJson()) {
                if (! empty($conflicts)) {
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

            if (! empty($conflicts)) {
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
        $this->authorizeFopTaskScope($fopTask);

        // SRV/PSB gak boleh dihapus dari sini SAMA SEKALI — di bawah ini,
        // destroy() beneran mentransisikan customer ke status 'rejected' (efek
        // samping yang udah ada dari awal, bukan baru), jadi hapus tiket ini
        // sengaja punya konsekuensi bisnis nyata di luar sekadar hapus baris —
        // harus lewat halaman Pelanggan biar disengaja, bukan kesenggol gak sadar.
        if (in_array($fopTask->category, [TaskType::SURVEY, TaskType::PEMASANGAN], true)) {
            abort(422, 'Task Survey/Pemasangan tidak bisa dihapus dari sini — bakal otomatis mengubah status pelanggan jadi Gagal. Kelola lewat halaman Pelanggan.');
        }

        // MTN/C-REQ yang asalnya dari Ticketing gak boleh dihapus — tiketnya
        // ada catatan pengirim (ticket_histories) yang harus tetap ke-trace.
        // Toleransi salah input tetap ada lewat Cancel (fop_tasks.cancel),
        // BUKAN Hapus. MTN/C-REQ yang dibuat manual langsung di sini (gak
        // punya $fopTask->ticket) tetap boleh dihapus seperti biasa.
        if ($fopTask->ticket) {
            abort(422, 'Task ini berasal dari Ticketing — gak bisa dihapus dari sini. Batalkan lewat tombol Cancel kalau salah input data.');
        }

        return DB::transaction(function () use ($fopTask) {
            $oldValues = $fopTask->load('technicians')->toArray();

            // Jika merupakan task auto-sync (Survey / PSB), ubah status customer menjadi REJECTED agar tidak terbuat lagi secara otomatis
            if ($fopTask->customer_id && in_array($fopTask->category->value, TaskType::autoOnlyValues(), true)) {
                $customer = $fopTask->customer;
                if ($customer && $customer->status !== 'rejected') {
                    try {
                        app(CustomerWorkflowService::class)->transition(
                            $customer,
                            WorkflowTransition::REJECTED,
                            'Tiket FOP dihapus oleh FOP.'
                        );
                    } catch (\Exception $e) {
                        Log::warning('Could not transition customer to rejected during FopTask deletion: '.$e->getMessage());
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
        $this->authorizeFopTaskScope($fopTask);

        $validated = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:fop_task_teams,id'],
        ]);

        if (! $fopTask->task_date) {
            return back()->withErrors(['team_id' => 'Task ini belum punya tanggal jadwal.']);
        }

        return DB::transaction(function () use ($validated, $fopTask, $request) {
            $workDate = $fopTask->task_date->toDateString();
            $workDateStart = $fopTask->task_date->copy()->startOfDay();
            $workDateEnd = $fopTask->task_date->copy()->endOfDay();

            if (! empty($validated['team_id'])) {
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
                // task_date bertipe datetime (bukan date) — rentang eksplisit
                // supaya index task_date bisa dipakai; whereDate() membungkusnya
                // jadi DATE(task_date) dan membuat index itu tidak pernah terpilih.
                $otherTasks = FopTask::where('id', '!=', $fopTask->id)
                    ->whereBetween('task_date', [$workDateStart, $workDateEnd])
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

                    FopTaskUpdated::dispatch($otherTask->fresh());
                }
            }

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'assign_to_team', $oldValues, $fopTask->toArray());
            }

            $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate($fopTask->task_date->copy());
            $team->refresh();

            FopTaskUpdated::dispatch($fopTask->fresh());

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
        $this->authorizeFopTaskScope($fromTask);
        $this->authorizeFopTaskScope($toTask);

        if (! $fromTask->technicians->contains('id', $validated['technician_id'])) {
            return $this->switchTechnicianError($request, 'technician_id', 'Teknisi yang dipilih bukan anggota Task asal.');
        }

        if ($toTask->technicians->contains('id', $validated['technician_id'])) {
            return $this->switchTechnicianError($request, 'technician_id', 'Teknisi tersebut sudah ada di Task tujuan.');
        }

        if ((int) $validated['replacement_technician_id'] === (int) $validated['technician_id']) {
            return $this->switchTechnicianError($request, 'replacement_technician_id', 'Pengganti tidak boleh teknisi yang sama dengan yang dipindah.');
        }

        if (! $fromTask->task_date || ! $toTask->task_date || ! $fromTask->task_date->isSameDay($toTask->task_date)) {
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

        $conflicts = DB::transaction(function () use ($validated, $fromTask, $toTask) {
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

            // from/to task_date SELALU sama (udah divalidasi intra-hari di atas) — cukup
            // rebuild SEKALI. Manggil rebuildTeamsForDate() 2x buat tanggal yang sama bikin
            // pass ke-2 gak nemu lagi konflik/team yang udah kehapus sama cleanup di pass
            // pertama (lihat analisa-sync-execution-task.md), teknisi jadi ke-merge salah.
            $teamResult = app(FopTaskTeamService::class)->rebuildTeamsForDate($fromTask->task_date->copy());

            return $teamResult['conflicts'];
        });

        $this->notifySwitchedTechnician(
            (int) $validated['technician_id'],
            "Anda dipindahkan dari task \"{$fromTask->tugas}\" ke task \"{$toTask->tugas}\".",
        );
        $this->notifySwitchedTechnician(
            (int) $validated['replacement_technician_id'],
            "Anda ditugaskan sebagai pengganti pada task \"{$fromTask->tugas}\".",
        );

        // Broadcast SETELAH transaction commit (sama pola dengan TaskService::create/
        // update()) — Reverb push nyaris instan, dan listener frontend langsung fetch()
        // partial task via request HTTP baru (koneksi DB terpisah). Kalau broadcast masih
        // di dalam transaction yang belum commit, request itu gak nemu row-nya (404 diam-
        // diam, cuma console.warn) — kartu gak pernah muncul tanpa reload manual.
        // switchTechnician() sengaja bypass TaskService::update()/notifyTeam() (lihat
        // komentar syncSwitchedExecutionTask()), jadi broadcast realtime-nya manual:
        //   - technician_id keluar dari fromTask, masuk ke toTask
        //   - replacement_technician_id masuk gantiin di fromTask
        $this->broadcastSwitchedExecutionTask($fromTask, 'removed', [(int) $validated['technician_id']]);
        $this->broadcastSwitchedExecutionTask($fromTask, 'created', [(int) $validated['replacement_technician_id']]);
        $this->broadcastSwitchedExecutionTask($toTask, 'created', [(int) $validated['technician_id']]);

        FopTaskUpdated::dispatch($fromTask->fresh());
        FopTaskUpdated::dispatch($toTask->fresh());

        if ($request->wantsJson()) {
            if (! empty($conflicts)) {
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
        if (! $fopTask->task_id || ! $fopTask->task) {
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
     * Broadcast TaskScheduled ke Task eksekusi punya $fopTask, target user id eksplisit
     * (bukan $task->teamMembers) — dipakai oleh switchTechnician() yang gak lewat
     * TaskService::update() sama sekali. Guard null sama seperti syncSwitchedExecutionTask().
     *
     * @param  array<int>  $targetUserIds
     */
    private function broadcastSwitchedExecutionTask(FopTask $fopTask, string $eventType, array $targetUserIds): void
    {
        if (! $fopTask->task_id || ! $fopTask->task) {
            return;
        }

        // afterCommit() — pola sama kayak TaskService::notifyTeam(), jaga-jaga kalau
        // method ini suatu saat dipanggil dari dalam transaction lain. Aman juga
        // dipanggil di luar transaction (jalan langsung).
        $task = $fopTask->task;
        DB::afterCommit(fn () => broadcast(new TaskScheduled($task, $eventType, $targetUserIds)));
    }

    /**
     * Notifikasi in-app buat teknisi yang kena switch (keluar atau masuk).
     */
    private function notifySwitchedTechnician(int $userId, string $message): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $user->notify(new AppNotification(
            title: 'Switch Teknisi',
            message: $message,
            actionUrl: route('fop-tasks.index'),
            type: NotificationType::INFO
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
        if (! $user) {
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
     * Guard per-record — authorizeAccess() di atas cuma cek permission generik
     * (fop_tasks.*), gak pernah cek pop_id. Tanpa ini, index()/history() yang
     * sudah di-scope applyUserScope() bisa ditembus lewat URL langsung
     * (/fop-tasks/{id}, update/destroy/showHistory/assignToTeam) — user tinggal
     * tebak/iterasi ID buat baca/ubah task FOP di POP lain (IDOR, lihat
     * docs/plan/analisa-celah-scope-pop.md temuan #12).
     */
    protected function authorizeFopTaskScope(FopTask $fopTask): void
    {
        $user = Auth::user();
        if ($user->hasRole('owner', 'atasan') || $user->hasFullAccess()) {
            return;
        }

        $access = app(EffectiveAccessService::class);
        if ($access->hasAllPopAccess($user)) {
            return;
        }

        if (! in_array((int) $fopTask->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Anda tidak memiliki akses ke Task FOP di POP ini.');
        }
    }

    /**
     * Display a listing of completed and cancelled FOP tasks.
     */
    public function history(Request $request)
    {
        $this->authorizeAccess();

        $query = FopTask::with(['village', 'technicians', 'task:id,status,report_deferred'])
            ->applyUserScope()
            ->whereIn('status', [TaskStatus::SELESAI, TaskStatus::DIBATALKAN])
            ->orderBy('updated_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
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
        $technicians = User::whereHas('role', function ($q) {
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
                    fn ($t) => ! in_array($t->status->value, [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                )->count();

                $workload = $team->fopTasks
                    ->flatMap(fn ($t) => $t->technicians)
                    ->countBy('id');

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'work_date' => $team->work_date->format('Y-m-d'),
                    'members' => $team->members->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'count' => $workload->get($m->id, 0),
                    ])->values(),
                    'task_count' => $team->fopTasks->count(),
                    'is_active' => $activeCount > 0,
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
        $this->authorizeFopTaskScope($fopTask);

        $fopTask->load([
            'village',
            'pop',
            // Detail Registrasi (SRV/PSB/MTN-C-REQ native) butuh data pelanggan
            // lengkap — bukan cuma nama, biar setara sama Data Pelanggan yang
            // ditampilkan buat MTN/C-REQ asal Ticketing di bawah.
            'customer.pop',
            'customer.customerAddress',
            'customer.customerTechnicalDetail',
            'customer.internetPackage',
            'customer.customerDevice',
            'technicians',
            'team:id,name',
            'statusHistories.changedByUser:id,name',
            'task.report',
            'task.teamMembers.user:id,name',
            'task.maintenanceReport',
            // MTN & C-REQ yang asalnya dari Ticketing — detail keluhan/catatan
            // teknis/data pelanggan/lampiran/riwayat harus mengikuti apa yang
            // dilihat di /tickets, bukan cuma issue 255 char yang kepotong.
            'ticket.creator:id,name',
            'ticket.attachments.uploader:id,name',
            'ticket.histories.actor:id,name',
            // Kategori Issue (Master Issue) — belum ditampilkan sama sekali
            // di Detail Task, padahal udah ada field-nya sejak Master Issue
            // ditambah (docs/plan/RANCANGAN_MASTER_ISSUE_TICKETING.md).
            'ticket.issueCategory:id,name',
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
        // 'pop' WAJIB ikut eager-load — Customer::getDisplayIdAttribute() butuh
        // relasi ini buat resolve CID (format tugas "{CID}_{Nama}" di bawah).
        $surveyCustomers = Customer::whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
            ->with('pop')
            ->whereDoesntHave('fopTasks', function ($q) {
                $q->where('category', TaskType::SURVEY->value)->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value]);
            })->get();

        foreach ($surveyCustomers as $c) {
            $taskNumber = $this->generateTaskNumber();

            FopTask::create([
                'task_number' => $taskNumber,
                'task_date' => now(),
                'category' => TaskType::SURVEY,
                'tugas' => $c->display_id.'_'.$c->full_name,
                'village_id' => $c->village_id ?? 1,
                'pop_id' => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'issue' => 'Auto-Sync dari antrean survey',
                'status' => TaskStatus::DRAFT,
                'priority' => FopTaskPriority::MEDIUM, // will be recalculated below
            ]);
        }

        // --- 2. Auto-Sync Installation ---
        // 'latestSurvey' ikut di-eager-load karena tanggal request pemasangan
        // pelanggan disimpan di sana (customer_surveys.requested_installation_date).
        $installCustomers = Customer::whereIn('status', ['waiting_installation', 'waiting_installations', 'surveyed'])
            ->with(['pop', 'latestSurvey'])
            ->whereDoesntHave('fopTasks', function ($q) {
                $q->where('category', TaskType::PEMASANGAN->value)->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value]);
            })->get();

        foreach ($installCustomers as $c) {
            $taskNumber = $this->generateTaskNumber();

            FopTask::create([
                'task_number' => $taskNumber,
                'task_date' => now(),
                'category' => TaskType::PEMASANGAN,
                'tugas' => $c->display_id.'_'.$c->full_name,
                'village_id' => $c->village_id ?? 1,
                'pop_id' => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'issue' => 'Auto-Sync dari antrean pemasangan',
                'status' => TaskStatus::DRAFT,
                'priority' => FopTaskPriority::MEDIUM, // will be recalculated below
                'client_request_date' => $c->latestSurvey?->requested_installation_date,
            ]);
        }

        // --- 2b. Refresh tanggal request pemasangan ---
        // client_request_date pada task PEMASANGAN adalah nilai TURUNAN dari
        // customer_surveys.requested_installation_date — bukan data milik FopTask.
        // Di-refresh tiap sync karena FopTaskController::update() sengaja me-null-kan
        // field ini tiap status keluar dari Pending. Tanpa langkah ini, tanggal
        // request pelanggan hilang begitu FOP menjadwalkan task, dan urutan papan
        // (orderByRaw client_request_date di index()) langsung salah.
        //
        // Jalur Pending manual untuk kategori NON-Pemasangan tidak tersentuh —
        // di sana client_request_date tetap milik FOP sepenuhnya.
        $psbTasks = FopTask::with('customer.latestSurvey')
            ->where('category', TaskType::PEMASANGAN->value)
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->whereNotNull('customer_id')
            ->get();

        foreach ($psbTasks as $task) {
            $requested = $task->customer?->latestSurvey?->requested_installation_date;

            $current = $task->client_request_date?->toDateString();
            $target = $requested?->toDateString();

            if ($current !== $target) {
                $task->update(['client_request_date' => $requested]);
            }
        }

        // --- 3. Dynamic Priority Update ---
        // Menggunakan eager loading (N+1 safe) agar query tidak dilooping
        $activeTasks = FopTask::with(['task:id,scheduled_at', 'customer.tasks' => function ($q) {
            $q->where('task_type', TaskType::SURVEY->value)
                ->where('status', TaskStatus::SELESAI->value)
                ->orderByDesc('completed_at');
        }])
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->whereIn('category', TaskType::autoOnlyValues())
            ->whereNotNull('customer_id')
            ->get();

        $now = Carbon::now();

        foreach ($activeTasks as $task) {
            $customer = $task->customer;
            if (! $customer) {
                continue;
            }

            // Task yang tanggal request pelanggannya masih di masa depan TIDAK
            // dihitung SLA-nya. Pelanggan sendiri yang minta tanggal itu, jadi
            // "telat" belum punya arti sampai harinya tiba. Tanpa guard ini, PSB
            // request 3 minggu ke depan naik jadi URGENT dalam beberapa hari dan
            // menutupi task yang benar-benar harus dikerjakan hari ini.
            //
            // Kondisinya diambil dari FopTask (bukan ditulis ulang di sini) supaya
            // badge prioritas dan countdown timer gak pernah pakai syarat beda.
            if ($task->isScheduledForFutureClientDate()) {
                if ($task->priority !== FopTaskPriority::LOW) {
                    $task->update(['priority' => FopTaskPriority::LOW]);
                }

                continue;
            }

            $totalSeconds = 0;
            $remainSeconds = 0;

            // SLA-window diambil dari TaskType::defaultHandlingSlaHours() (satu
            // sumber kebenaran, sama yg dipakai FopTask.php) — bukan literal
            // 86400/259200 hand-roll lagi, biar gak divergen kalau default
            // SLA per-tipe diubah di masa depan.
            if ($task->category === TaskType::SURVEY) {
                $slaHours = $task->category->defaultHandlingSlaHours();
                $deadline = Carbon::parse($customer->created_at)->addHours($slaHours);
                $totalSeconds = $slaHours * 3600;
                $remainSeconds = -$deadline->diffInSeconds($now, false);
            } elseif ($task->category === TaskType::PEMASANGAN) {
                if ($task->usesClientRequestDeadline()) {
                    // Tanggal request pelanggan sudah tiba/lewat. Deadline & durasinya
                    // diambil dari FopTask (endOfDay tanggal request, jendela 1 hari)
                    // supaya identik dengan angka yang ditampilkan countdown timer.
                    // Kalau dihitung ulang di sini, prioritas dan timer bisa beda.
                    $deadline = $task->slaDeadline();
                    $totalSeconds = $task->slaTotalSeconds();
                } else {
                    $slaHours = $task->category->defaultHandlingSlaHours();
                    // Ambil task survey dari relation yang sudah di-eager load (bukan query baru)
                    $surveyTask = $customer->tasks->first();

                    $refDate = $surveyTask?->completed_at ? Carbon::parse($surveyTask->completed_at) : Carbon::parse($customer->updated_at);
                    $deadline = $refDate->addHours($slaHours);
                    $totalSeconds = $slaHours * 3600;
                }

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
     * Bagian "WHEN ? THEN n" dari CASE SQL sorting priority, digenerate dari
     * FopTaskPriority::sortOrder() — bukan literal string per value lagi
     * (lihat ANALISA_REDUNDANSI_LOGIC.md §5). Kalau enum direname/ditambah
     * case baru, ini otomatis ikut, gak perlu sentuh raw SQL manual.
     */
    private static function priorityOrderCaseSql(): string
    {
        return collect(FopTaskPriority::cases())
            ->sortBy(fn (FopTaskPriority $p) => $p->sortOrder())
            ->map(fn (FopTaskPriority $p) => "WHEN ? THEN {$p->sortOrder()}")
            ->implode(' ');
    }

    /**
     * Binding value buat priorityOrderCaseSql(), urutannya harus sama persis.
     */
    private static function priorityOrderBindings(): array
    {
        return collect(FopTaskPriority::cases())
            ->sortBy(fn (FopTaskPriority $p) => $p->sortOrder())
            ->map(fn (FopTaskPriority $p) => $p->value)
            ->all();
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
