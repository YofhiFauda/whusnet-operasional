<?php

namespace App\Http\Controllers;

use App\Models\FopTask;
use App\Models\Village;
use App\Models\User;
use App\Models\Pop;
use App\Enums\FopTaskPriority;
use App\Enums\FopTaskStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Enums\TaskType;
use App\Services\TaskService;

class FopTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeAccess();
        
        $this->autoSyncAndCalculatePriority();

        $query = FopTask::with(['village', 'technicians'])
            ->orderByRaw("CASE WHEN status IN ('Proses', 'Pending') THEN 1 ELSE 2 END")
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
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('village_id')) {
            $query->where('village_id', $request->input('village_id'));
        }

        $fopTasks = $query->paginate(20)->withQueryString();

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

        return view('fop_tasks.index', compact('fopTasks', 'villages', 'pops', 'technicians', 'categories', 'manualCategories', 'canEditFopTaskType'));
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
            'status' => ['required', 'string', Rule::enum(FopTaskStatus::class)],
            'priority' => ['required', 'string', Rule::enum(FopTaskPriority::class)],
            'pending_reason' => ['nullable', 'required_if:status,Pending', 'string', 'max:255'],
            'client_request_date' => ['nullable', 'required_if:status,Pending', 'date'],
            'technicians' => ['required', 'array', 'min:1'],
            'technicians.*' => ['exists:users,id'],
        ], [
            'pending_reason.required_if' => 'Alasan pending wajib diisi jika status Pending.',
            'client_request_date.required_if' => 'Tanggal request client wajib diisi jika status Pending.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $year  = date('Y');
            $count = FopTask::whereYear('created_at', $year)->count() + 1;
            $taskNumber = sprintf('TFOP-%s-%04d', $year, $count);

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

            if ($validated['status'] === FopTaskStatus::PENDING->value) {
                $fopTask->pending_reason = $validated['pending_reason'];
                $fopTask->client_request_date = $validated['client_request_date'];
            } elseif ($validated['status'] === FopTaskStatus::CANCEL->value) {
                $fopTask->cancelled_at = now();
            }

            $fopTask->save();

            if (!empty($validated['technicians'])) {
                $technicians = $validated['technicians'];
                $fopTask->technicians()->sync($technicians);

                $taskTitle = 'FOP: ' . $fopTask->tugas;
                if (count($technicians) > 1) {
                    $leadUser = \App\Models\User::find($technicians[0]);
                    $teamName = $leadUser ? 'Tim ' . strtok($leadUser->name, ' ') : 'Tim Gabungan';
                    $taskTitle = '[' . $teamName . '] ' . $taskTitle;
                }

                $taskData = [
                    'customer_id' => $fopTask->customer_id,
                    'pop_id' => $fopTask->pop_id,
                    'task_type' => $fopTask->category->value,
                    'title' => $taskTitle,
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

            return redirect()->route('fop-tasks.index')->with('success', "Task FOP {$taskNumber} berhasil dibuat.");
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
            'status' => ['sometimes', 'required', 'string', Rule::enum(FopTaskStatus::class)],
            'priority' => ['sometimes', 'required', 'string', Rule::enum(FopTaskPriority::class)],
            'pending_reason' => ['nullable', 'required_if:status,Pending', 'string', 'max:255'],
            'client_request_date' => ['nullable', 'required_if:status,Pending', 'date'],
            'technicians' => ['sometimes', 'required', 'array', 'min:1'],
            'technicians.*' => ['exists:users,id'],
        ], [
            'pending_reason.required_if' => 'Alasan pending wajib diisi jika status Pending.',
            'client_request_date.required_if' => 'Tanggal request client wajib diisi jika status Pending.',
        ]);

        // RBAC: hanya user dgn fop_tasks.update_sensitive yang boleh ubah Tipe Task.
        if (!auth()->user()->hasPermission('fop_tasks.update_sensitive')) {
            unset($validated['category']);
        }

        return DB::transaction(function () use ($validated, $fopTask, $request) {
            $oldValues = $fopTask->load('technicians')->toArray();

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

                if ($validated['status'] === FopTaskStatus::PENDING->value) {
                    $fopTask->pending_reason = $validated['pending_reason'] ?? $fopTask->pending_reason;
                    $fopTask->client_request_date = $validated['client_request_date'] ?? $fopTask->client_request_date;
                    $fopTask->cancelled_at = null;
                } elseif ($validated['status'] === FopTaskStatus::CANCEL->value || $validated['status'] === FopTaskStatus::SELESAI->value) {
                    if ($oldStatus !== FopTaskStatus::CANCEL->value && $validated['status'] === FopTaskStatus::CANCEL->value) {
                        $fopTask->cancelled_at = now();
                    }
                    if ($validated['status'] === FopTaskStatus::SELESAI->value && $oldStatus === FopTaskStatus::CANCEL->value) {
                        $fopTask->cancelled_at = null;
                    }
                    $fopTask->pending_reason = null;
                    $fopTask->client_request_date = null;
                } else { // Proses
                    $fopTask->pending_reason = null;
                    $fopTask->client_request_date = null;
                    $fopTask->cancelled_at = null;
                }
            }

            $fopTask->save();

            if ($request->has('technicians')) {
                $technicians = $validated['technicians'] ?? [];
                $fopTask->technicians()->sync($technicians);

                if (!empty($technicians) || $fopTask->task_id) {
                    $taskTitle = 'FOP: ' . $fopTask->tugas;
                    if (count($technicians) > 1) {
                        $leadUser = \App\Models\User::find($technicians[0]);
                        $teamName = $leadUser ? 'Tim ' . strtok($leadUser->name, ' ') : 'Tim Gabungan';
                        $taskTitle = '[' . $teamName . '] ' . $taskTitle;
                    }

                    $taskData = [
                        'customer_id' => $fopTask->customer_id,
                        'pop_id' => $fopTask->pop_id,
                        'task_type' => $fopTask->category->value,
                        'title' => $taskTitle,
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

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Task FOP {$fopTask->task_number} berhasil diperbarui.",
                    'task' => $fopTask
                ]);
            }

            return redirect()->route('fop-tasks.index')->with('success', "Task FOP {$fopTask->task_number} berhasil diperbarui.");
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

            $fopTask->technicians()->detach();
            $fopTask->delete();

            if (class_exists(AuditLog::class)) {
                AuditLog::log($fopTask, 'delete', $oldValues, null);
            }

            return redirect()->route('fop-tasks.index')->with('success', "Task FOP {$fopTask->task_number} berhasil dihapus.");
        });
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
     * Auto-sync customers to FOP Task and calculate priority dynamically based on SLA.
     */
    private function autoSyncAndCalculatePriority()
    {
        // --- 1. Auto-Sync Survey ---
        $surveyCustomers = Customer::whereIn('status', ['calon_pelanggan', 'waiting_survey', 'registered'])
            ->whereDoesntHave('fopTasks', function ($q) {
                $q->where('category', 'Survey')->whereIn('status', ['Proses', 'Pending']);
            })->get();

        foreach ($surveyCustomers as $c) {
            $year = date('Y');
            $count = FopTask::whereYear('created_at', $year)->count() + 1;
            $taskNumber = sprintf('TFOP-%s-%04d', $year, $count);
            
            FopTask::create([
                'task_number' => $taskNumber,
                'task_date' => now(),
                'category' => TaskType::SURVEY,
                'tugas' => 'Survey Pelanggan: ' . $c->full_name,
                'village_id' => $c->village_id ?? 1,
                'pop_id' => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'issue' => 'Auto-Sync dari antrean survey',
                'status' => FopTaskStatus::PROSES,
                'priority' => FopTaskPriority::MEDIUM, // will be recalculated below
            ]);
        }

        // --- 2. Auto-Sync Installation ---
        $installCustomers = Customer::whereIn('status', ['waiting_installation', 'waiting_installations', 'surveyed'])
            ->whereDoesntHave('fopTasks', function ($q) {
                $q->where('category', 'PSB')->whereIn('status', ['Proses', 'Pending']);
            })->get();

        foreach ($installCustomers as $c) {
            $year = date('Y');
            $count = FopTask::whereYear('created_at', $year)->count() + 1;
            $taskNumber = sprintf('TFOP-%s-%04d', $year, $count);

            FopTask::create([
                'task_number' => $taskNumber,
                'task_date' => now(),
                'category' => TaskType::PEMASANGAN,
                'tugas' => 'Pemasangan Baru: ' . $c->full_name,
                'village_id' => $c->village_id ?? 1,
                'pop_id' => $c->pop_id ?? 1,
                'customer_id' => $c->id,
                'issue' => 'Auto-Sync dari antrean pemasangan',
                'status' => FopTaskStatus::PROSES,
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
            ->whereIn('status', ['Proses', 'Pending'])
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
}
