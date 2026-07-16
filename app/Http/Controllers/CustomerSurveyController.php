<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerSurveyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.view'), 403);

        $query = Customer::with(['village.district', 'latestSurvey.technician'])
            ->where('status', 'waiting_survey')
            ->orWhere('status', 'survey_in_progress');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%")
                  ->orWhere('primary_phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('surveys.queue', compact('customers'));
    }

    public function start(Request $request, Customer $customer, \App\Services\CustomerWorkflowService $workflowService, \App\Services\TaskService $taskService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.update'), 403);

        if ($customer->status === 'registered') {
            try {
                $workflowService->transition($customer, \App\Enums\WorkflowTransition::WAITING_SURVEY, 'Otomatis transisi ke waiting_survey saat mulai survey');
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Gagal memproses transisi status: ' . $e->getMessage());
            }
        }

        if ($customer->status !== 'waiting_survey') {
            return redirect()->back()->with('error', 'Status pelanggan tidak valid untuk memulai survey. Status saat ini: ' . $customer->status);
        }

        $task = \App\Models\Task::where('customer_id', $customer->id)
            ->where('task_type', \App\Enums\TaskType::SURVEY->value)
            ->where('status', \App\Enums\TaskStatus::TERJADWAL->value)
            ->latest('id')
            ->first();

        if ($task) {
            abort_unless($task->teamMembers->pluck('user_id')->contains(auth()->id()), 403);
        }

        $memberIds = $task ? $task->teamMembers()->pluck('user_id')->toArray() : [];
        if (!in_array(auth()->id(), $memberIds)) {
            $memberIds[] = auth()->id();
        }

        $activeTask = \App\Models\Task::where('status', \App\Enums\TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $memberIds))
            ->when($task, fn ($q) => $q->where('id', '!=', $task->id))
            ->first();

        if ($activeTask) {
            return redirect()->back()->with('error', "Tidak dapat memulai survey karena teknisi sedang mengerjakan task lain [{$activeTask->task_number}]. Selesaikan atau laporkan (pending) task sebelumnya terlebih dahulu.");
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($customer, $workflowService, $taskService, $task) {
            $survey = $customer->latestSurvey()->first();
            
            if (!$survey) {
                // Should not happen if assigned properly, but just in case
                $survey = new CustomerSurvey(['customer_id' => $customer->id]);
            }
            
            $survey->survey_status = 'pending'; // Or 'in_progress' if we add it to the enum
            $survey->started_at = now();
            if ($task) {
                $survey->fop_id = $task->fop_id ?? $task->created_by;
            }
            $survey->save();

            $workflowService->transition($customer, \App\Enums\WorkflowTransition::SURVEY_IN_PROGRESS, 'Mulai proses survey lapangan');

            if ($task) {
                $taskService->start($task, auth()->user());
            }
        }, 3);

        // Trigger Event SurveyStarted
        try {
            event(new \App\Events\SurveyStarted($customer));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Gagal broadcast SurveyStarted: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Waktu survey telah dimulai.');
    }

    /**
     * Batalkan survey pelanggan langsung dari status (tanpa lewat form Lapor Survey
     * lengkap) — dipakai FOP/NOC/Admin buat nandain pelanggan tidak layak pasang
     * lebih cepat. Alasan wajib diisi. Reuse logic yang sama persis dengan cabang
     * `survey_status=failed` di store(): task survey di-cancel, customer di-reject.
     */
    public function cancel(Request $request, Customer $customer, \App\Services\CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.reject'), 403);

        abort_unless(
            in_array($customer->status, ['waiting_survey', 'survey_in_progress']),
            422,
            'Survey pelanggan ini tidak bisa dibatalkan dari status saat ini: ' . $customer->status
        );

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $validated, $workflowService) {
            $task = \App\Models\Task::where('customer_id', $customer->id)
                ->where('task_type', \App\Enums\TaskType::SURVEY->value)
                ->whereNotIn('status', [\App\Enums\TaskStatus::SELESAI->value, \App\Enums\TaskStatus::DIBATALKAN->value])
                ->latest('id')
                ->first();

            if ($task) {
                app(\App\Services\TaskService::class)->cancel($task, auth()->user(), $validated['reason']);
            }

            $survey = $customer->latestSurvey()->first() ?? new CustomerSurvey(['customer_id' => $customer->id]);
            $survey->survey_status = 'failed';
            $survey->survey_note = $validated['reason'];
            $survey->save();

            $workflowService->transition($customer, \App\Enums\WorkflowTransition::REJECTED, $validated['reason']);
        });

        return redirect()->back()->with('success', 'Survey pelanggan berhasil dibatalkan: tidak layak pasang.');
    }

    public function report(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.update'), 403);

        if ($customer->status !== 'survey_in_progress') {
            return redirect()->route('surveys.queue')->with('error', 'Status pelanggan tidak valid untuk pelaporan survey.');
        }

        $survey = $customer->latestSurvey()->first();
        if (!$survey) {
            return redirect()->route('surveys.queue')->with('error', 'Data waktu mulai survey tidak ditemukan.');
        }

        return view('surveys.report', compact('customer', 'survey'));
    }

    public function store(Request $request, Customer $customer, \App\Services\CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.update'), 403);

        // Data survey hanya boleh diubah selama tahap survey lapangan berjalan.
        // Setelah lewat tahap ini (masuk antrean verifikasi/pemasangan/aktif dst),
        // perubahan hanya diizinkan untuk role dengan permission validate (Admin/Verifikator).
        abort_unless(
            $customer->status === 'survey_in_progress'
                || auth()->user()->hasPermission('customers.detail.survey.validate'),
            403,
            'Data survey pelanggan ini sudah melewati tahap survey dan tidak dapat diubah oleh role Anda.'
        );

        $validated = $request->validate([
            'survey_status'           => 'required|string|in:pending,completed,failed',
            'required_tools'          => 'nullable|string',
            'cable_estimation_meter'  => 'required_if:survey_status,completed|nullable|integer|min:0',
            'nearest_odp'             => 'required_if:survey_status,completed|nullable|string',
            'survey_photo'            => 'required_if:survey_status,completed|nullable|image|max:2048',
            'house_photo'             => 'required_if:survey_status,completed|nullable|image|max:2048',
            'survey_note'             => 'required_if:survey_status,failed|nullable|string',
            'difficulty_level'        => 'required_if:survey_status,completed|nullable|in:MUDAH,SEDANG,SULIT',
        ], [
            'survey_note.required_if' => 'Alasan tidak layak pasang wajib diisi.',
        ]);

        $difficulty = $validated['difficulty_level'] ?? null;
        $note = $difficulty ? ("Tingkat Kesulitan: " . $difficulty) : '';
        if (!empty($validated['survey_note'])) {
            $note .= ($note ? "\n" : '') . "Catatan: " . $validated['survey_note'];
        }
        $validated['survey_note'] = $note;
        unset($validated['difficulty_level']);

        if ($request->hasFile('survey_photo')) {
            $validated['survey_photo'] = \App\Services\FileUploadService::uploadSurveyPhoto($request->file('survey_photo'), $customer, 'odp');
        }

        if ($request->hasFile('house_photo')) {
            $validated['house_photo'] = \App\Services\FileUploadService::uploadSurveyPhoto($request->file('house_photo'), $customer, 'house');
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($customer, $validated, $workflowService) {
            $survey = $customer->latestSurvey()->first();
            
            if (!$survey) {
                $survey = new CustomerSurvey(['customer_id' => $customer->id]);
            }

            $survey->fill($validated);
            
            $task = \App\Models\Task::where('customer_id', $customer->id)
                ->where('task_type', \App\Enums\TaskType::SURVEY->value)
                ->whereIn('status', [\App\Enums\TaskStatus::IN_PROGRESS->value, \App\Enums\TaskStatus::PENDING->value])
                ->latest('id')
                ->first();

            if (!$survey->completed_at) {
                $completedAt = now();
                $survey->completed_at = $completedAt;
                $survey->end_date = $completedAt->toDateString();
                $survey->end_time = $completedAt->toTimeString();
                if ($survey->started_at) {
                    $survey->duration_minutes = $survey->started_at->diffInMinutes($completedAt);
                    $survey->survey_date = $survey->started_at->toDateString();
                    $survey->start_time = $survey->started_at->toTimeString();
                } else {
                    $survey->survey_date = $completedAt->toDateString();
                    $survey->start_time = $completedAt->toTimeString();
                }
            }

            $survey->technician_id = auth()->id();

            $surveyorsText = null;
            if ($task) {
                $survey->fop_id = $task->fop_id ?? $task->created_by;
                
                $teamMembers = $task->teamMembers()->orderBy('id')->get();
                $currentUserId = auth()->id();
                
                $memberIndex = 1;
                foreach ($teamMembers as $idx => $member) {
                    if ($member->user_id == $currentUserId) {
                        $memberIndex = $idx + 1;
                        break;
                    }
                }
                
                $surveyorsText = "Petugas Survey {$memberIndex} - " . auth()->user()->name;
                
                $otherMembers = $teamMembers->filter(fn($m) => $m->user_id != $currentUserId)->values();
                if ($otherMembers->isNotEmpty()) {
                    $survey->surveyor_2_id = $otherMembers[0]->user_id;
                }
                if ($otherMembers->count() > 1) {
                    $survey->surveyor_3_id = $otherMembers[1]->user_id;
                }
            } else {
                $surveyorsText = "Petugas Survey 1 - " . auth()->user()->name;
            }

            $survey->surveyors = $surveyorsText;
            
            $survey->save();

            if ($validated['survey_status'] === 'completed' && $customer->status === 'survey_in_progress') {
                // Selesaikan task survey jika ada
                if ($task) {
                    app(\App\Services\TaskService::class)->complete($task, auth()->user());
                }

                $workflowService->transition($customer, \App\Enums\WorkflowTransition::WAITING_ACC, 'Survey lapangan selesai dilaporkan');
                try {
                    event(new \App\Events\SurveyCompleted($customer));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Gagal broadcast SurveyCompleted: ' . $e->getMessage());
                }

                try {
                    $telegram = app(\App\Services\TelegramBotService::class);
                    $message = "✅ <b>Survey Selesai</b>\n";
                    $message .= "Pelanggan: {$customer->full_name}\n";
                    $message .= "No. HP: {$customer->phone}\n";
                    $message .= "Alamat: {$customer->address}\n";
                    $message .= "Menunggu Verifikasi Admin untuk Pemasangan.";
                    $telegram->sendMessage($message);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Gagal mengirim notifikasi Telegram: ' . $e->getMessage());
                }
            }

            if ($validated['survey_status'] === 'failed' && $customer->status === 'survey_in_progress') {
                // Tutup task survey sebagai dibatalkan — pelanggan tidak layak pasang
                if ($task) {
                    app(\App\Services\TaskService::class)->cancel($task, auth()->user(), $survey->survey_note);
                }

                $workflowService->transition($customer, \App\Enums\WorkflowTransition::REJECTED, $survey->survey_note);
            }
        });

        if ($validated['survey_status'] === 'failed') {
            return redirect()->route('surveys.queue')->with('success', 'Survey selesai dilaporkan: pelanggan tidak layak pasang.');
        }

        return redirect()->route('verifications.queue')->with('success', 'Data survey berhasil disimpan.');
    }
}
