<?php

namespace App\Http\Controllers;

use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Events\SurveyCompleted;
use App\Events\SurveyStarted;
use App\Models\Customer;
use App\Models\CustomerSurvey;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Task;
use App\Models\WorkTool;
use App\Services\CustomerWorkflowService;
use App\Services\FileUploadService;
use App\Services\FopTaskProvisioningService;
use App\Services\TaskMaterialService;
use App\Services\TaskService;
use App\Services\TaskWorkToolService;
use App\Services\TelegramBotService;
use App\Support\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerSurveyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.view'), 403);

        // orWhere() dibungkus closure — sebelumnya orWhere() nempel di level
        // top query, jadi filter search() di bawah kena presedensi SQL
        // "AND lebih erat dari OR" (jadi search cuma efektif buat cabang
        // survey_in_progress, bukan waiting_survey). Sekalian jadi tempat aman
        // buat nambah scope teknisi di bawah tanpa kena bug presedensi yang sama.
        $query = Customer::with(['pop', 'village.district', 'latestSurvey.technician'])
            ->applyUserScope()
            ->where(function ($q) {
                $q->where('status', 'waiting_survey')->orWhere('status', 'survey_in_progress');
            });

        // Teknisi cuma boleh liat pelanggan yang Task Survey-nya PERNAH
        // dijadwalkan buat dirinya (jadi anggota tim) — bukan seluruh antrean.
        // NOC/FOP/Admin/Owner (hasFullAccess) tetap liat semua buat supervisi.
        // Sengaja dicek pakai role, bukan permission kayak task.view.own/all —
        // customers.detail.survey belum punya split permission serupa (lihat
        // config/rbac.php), dan bikin split baru buat 1 kasus ini overkill.
        if (! auth()->user()->hasFullAccess() && auth()->user()->hasRole('teknisi')) {
            $query->whereHas('tasks', function ($q) {
                $q->where('task_type', TaskType::SURVEY->value)
                    ->whereHas('teamMembers', fn ($tm) => $tm->where('user_id', auth()->id()));
            });
        }

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

    public function start(Request $request, Customer $customer, CustomerWorkflowService $workflowService, TaskService $taskService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.update'), 403);

        if ($customer->status === 'registered') {
            try {
                $workflowService->transition($customer, WorkflowTransition::WAITING_SURVEY, 'Otomatis transisi ke waiting_survey saat mulai survey');
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Gagal memproses transisi status: '.$e->getMessage());
            }
        }

        if ($customer->status !== 'waiting_survey') {
            return redirect()->back()->with('error', 'Status pelanggan tidak valid untuk memulai survey. Status saat ini: '.$customer->status);
        }

        $task = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::SURVEY->value)
            ->where('status', TaskStatus::TERJADWAL->value)
            ->latest('id')
            ->first();

        // WAJIB — sebelumnya cuma dicek kalau $task ketemu (`if ($task) {...}`),
        // jadi teknisi mana pun yang punya permission generik
        // customers.detail.survey.update bisa mulai survey pelanggan MANA PUN
        // tanpa pernah dijadwalkan FOP (bug RBAC — null Task paling sering
        // kejadian justru karena Task-nya masih Draft, BELUM dijadwalkan, bukan
        // berarti "gak perlu dicek"). hasFullAccess() tetap boleh override
        // buat intervensi manual Owner/Admin.
        abort_unless(
            auth()->user()->hasFullAccess()
                || ($task && $task->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda belum dijadwalkan untuk survey pelanggan ini — tunggu penjadwalan dari FOP sebelum memulai survey.'
        );

        $memberIds = $task ? $task->teamMembers()->pluck('user_id')->toArray() : [auth()->id()];
        if (! in_array(auth()->id(), $memberIds)) {
            $memberIds[] = auth()->id();
        }

        $activeTask = Task::where('status', TaskStatus::IN_PROGRESS->value)
            ->whereHas('teamMembers', fn ($q) => $q->whereIn('user_id', $memberIds))
            ->when($task, fn ($q) => $q->where('id', '!=', $task->id))
            ->first();

        if ($activeTask) {
            return redirect()->back()->with('error', "Tidak dapat memulai survey karena teknisi sedang mengerjakan task lain [{$activeTask->task_number}]. Selesaikan atau laporkan (pending) task sebelumnya terlebih dahulu.");
        }

        DB::transaction(function () use ($customer, $workflowService, $taskService, $task) {
            $survey = $customer->latestSurvey()->first();

            if (! $survey) {
                // Should not happen if assigned properly, but just in case
                $survey = new CustomerSurvey(['customer_id' => $customer->id]);
            }

            $survey->survey_status = 'pending'; // Or 'in_progress' if we add it to the enum
            $survey->started_at = now();
            if ($task) {
                $survey->fop_id = $task->fop_id ?? $task->created_by;
            }
            $survey->save();

            $workflowService->transition($customer, WorkflowTransition::SURVEY_IN_PROGRESS, 'Mulai proses survey lapangan');

            if ($task) {
                $taskService->start($task, auth()->user());
            }
        }, 3);

        // Trigger Event SurveyStarted
        try {
            event(new SurveyStarted($customer));
        } catch (\Exception $e) {
            Log::error('Gagal broadcast SurveyStarted: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Waktu survey telah dimulai.');
    }

    /**
     * Batalkan survey pelanggan langsung dari status (tanpa lewat form Lapor Survey
     * lengkap) — dipakai FOP/NOC/Admin buat nandain pelanggan tidak layak pasang
     * lebih cepat. Alasan wajib diisi. Reuse logic yang sama persis dengan cabang
     * `survey_status=failed` di store(): task survey di-cancel, customer di-reject.
     */
    public function cancel(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.reject'), 403);

        abort_unless(
            in_array($customer->status, ['waiting_survey', 'survey_in_progress']),
            422,
            'Survey pelanggan ini tidak bisa dibatalkan dari status saat ini: '.$customer->status
        );

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($customer, $validated, $workflowService) {
            $task = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::SURVEY->value)
                ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
                ->latest('id')
                ->first();

            if ($task) {
                app(TaskService::class)->cancel($task, auth()->user(), $validated['reason']);
            }

            $survey = $customer->latestSurvey()->first() ?? new CustomerSurvey(['customer_id' => $customer->id]);
            $survey->survey_status = 'failed';
            $survey->survey_note = $validated['reason'];
            $survey->save();

            $workflowService->transition($customer, WorkflowTransition::REJECTED, $validated['reason']);
        });

        return redirect()->back()->with('success', 'Survey pelanggan berhasil dibatalkan: tidak layak pasang.');
    }

    public function report(Customer $customer, Request $request)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.survey.update'), 403);

        // Halaman ini diakses dari beberapa entry point (Detail Task teknisi,
        // Dashboard Task Saya, Antrean Survey, Detail Pelanggan) — lihat
        // SafeUrl::resolveReturnTo() kenapa gak pakai url()->previous().
        $returnTo = SafeUrl::resolveReturnTo($request->query('return_to'), 'surveys.queue');

        if ($customer->status !== 'survey_in_progress') {
            return redirect()->route('surveys.queue')->with('error', 'Status pelanggan tidak valid untuk pelaporan survey.');
        }

        // Guard assignment sama kayak start() — permission generik
        // customers.detail.survey.update gak cukup, wajib jadi anggota tim
        // Task yang lagi jalan. Berlaku buat SEMUA non-full-access, termasuk
        // NOC (keputusan eksplisit: no exemption, biar konsisten sama
        // start()/store() — supervisi/koreksi data tetap lewat hasFullAccess).
        $task = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::SURVEY->value)
            ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
            ->latest('id')
            ->first();

        abort_unless(
            auth()->user()->hasFullAccess()
                || ($task && $task->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda bukan anggota tim yang ditugaskan untuk survey pelanggan ini.'
        );

        $survey = $customer->latestSurvey()->first();
        if (! $survey) {
            return redirect()->route('surveys.queue')->with('error', 'Data waktu mulai survey tidak ditemukan.');
        }

        // Master barang aktif buat dropdown material. Baris estimasi yang sudah
        // pernah disimpan ikut dikirim supaya laporan yang dibuka ulang gak
        // kehilangan isinya.
        $items = Item::active()->with('category')->orderBy('name')->get();
        $itemCategories = ItemCategory::options();
        $materialRows = app(TaskMaterialService::class)
            ->estimatesForCustomer($customer)
            ->map(fn ($row) => [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'item_type' => $row->item_type,
                'qty' => (float) $row->qty,
                'unit' => $row->unit,
                'note' => $row->note,
            ])
            ->all();

        // Checklist alat kerja — peralatan yang dibawa lalu dibawa pulang,
        // tabel & komponen terpisah dari material.
        $workToolService = app(TaskWorkToolService::class);
        $workTools = WorkTool::options();
        $workToolRows = $workToolService->rowsFor(
            $workToolService->resolveTaskForCustomer($customer, TaskType::SURVEY)
        );

        return view('surveys.report', compact('customer', 'survey', 'items', 'itemCategories', 'materialRows', 'workTools', 'workToolRows', 'returnTo'));
    }

    public function store(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
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

        // Guard assignment sama kayak start()/report() — SEMUA non-full-access
        // wajib jadi anggota tim Task yang lagi jalan, termasuk NOC (gak ada
        // pengecualian, keputusan eksplisit biar konsisten satu alur).
        $assignmentTask = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::SURVEY->value)
            ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
            ->latest('id')
            ->first();

        abort_unless(
            auth()->user()->hasFullAccess()
                || ($assignmentTask && $assignmentTask->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda bukan anggota tim yang ditugaskan untuk survey pelanggan ini.'
        );

        $validated = $request->validate([
            'survey_status' => 'required|string|in:pending,completed,failed',
            'required_tools' => 'nullable|string',
            'cable_estimation_meter' => 'required_if:survey_status,completed|nullable|integer|min:0',
            'nearest_odp' => 'required_if:survey_status,completed|nullable|string',
            'survey_photo' => 'required_if:survey_status,completed|nullable|image|max:2048',
            'house_photo' => 'required_if:survey_status,completed|nullable|image|max:2048',
            'survey_note' => 'required_if:survey_status,failed|nullable|string',
            'difficulty_level' => 'required_if:survey_status,completed|nullable|in:MUDAH,SEDANG,SULIT',
            // Opsional — cuma diisi kalau pelanggan minta tanggal tertentu.
            // after_or_equal:today: minta dipasang di tanggal yang sudah lewat
            // tidak punya arti, dan kalau lolos akan langsung bikin task lahir
            // dalam kondisi TERLAMBAT di papan FOP.
            'requested_installation_date' => 'nullable|date|after_or_equal:today',
            // Estimasi kebutuhan alat — daftar baris, bukan teks bebas.
            // Semuanya nullable per baris: baris kosong dibuang di
            // TaskMaterialService, bukan ditolak, supaya form repeatable yang
            // menyisakan baris kosong terakhir gak bikin submit gagal.
            'materials' => 'nullable|array',
            'materials.*.item_id' => 'nullable|integer|exists:items,id',
            'materials.*.item_name' => 'nullable|string|max:150',
            // Kategori divalidasi ke master, bukan ke daftar enum yang beku —
            // kategori buatan admin harus langsung bisa dipakai tanpa deploy.
            'materials.*.item_type' => ['nullable', 'string', Rule::exists('item_categories', 'code')->where('is_active', true)],
            'materials.*.qty' => 'nullable|numeric|min:0',
            'materials.*.unit' => 'nullable|string|max:20',
            'materials.*.note' => 'nullable|string|max:255',
            // Checklist alat kerja. Dua bagian karena checkbox tidak bisa
            // membawa catatan per-baris; digabung di TaskWorkToolService.
            'work_tools_ids' => 'nullable|array',
            'work_tools_ids.*' => 'nullable|integer|exists:work_tools,id',
            'work_tools_manual' => 'nullable|array',
            'work_tools_manual.*.tool_name' => 'nullable|string|max:100',
            'work_tools_manual.*.note' => 'nullable|string|max:255',
        ], [
            'survey_note.required_if' => 'Alasan tidak layak pasang wajib diisi.',
            'requested_installation_date.after_or_equal' => 'Tanggal request pemasangan tidak boleh sebelum hari ini.',
        ]);

        $difficulty = $validated['difficulty_level'] ?? null;
        $note = $difficulty ? ('Tingkat Kesulitan: '.$difficulty) : '';
        if (! empty($validated['survey_note'])) {
            $note .= ($note ? "\n" : '').'Catatan: '.$validated['survey_note'];
        }
        $validated['survey_note'] = $note;
        unset($validated['difficulty_level']);

        // Baris material dipisah dari payload survey — tujuannya tabel lain
        // (task_materials), bukan kolom customer_surveys.
        $materialRows = $validated['materials'] ?? [];
        unset($validated['materials']);

        // Idem untuk checklist alat kerja → task_work_tools.
        $workToolRows = app(TaskWorkToolService::class)->rowsFromRequest(
            $validated['work_tools_ids'] ?? [],
            $validated['work_tools_manual'] ?? []
        );
        unset($validated['work_tools_ids'], $validated['work_tools_manual']);

        // Estimasi kabel sudah punya kolom sendiri sejak lama dan tetap dipakai.
        // Nilainya diturunkan jadi satu baris material supaya teknisi gak diminta
        // mengisi angka yang sama dua kali — dan supaya perbandingan realisasi
        // di halaman verifikasi punya lawan tanding untuk kabel.
        $cableMeter = $validated['cable_estimation_meter'] ?? null;

        // Kategori baris dicek lewat master kalau item_id dikirim — form memang
        // tidak selalu menyertakan item_type untuk barang terdaftar (kategorinya
        // ikut master). Kalau cuma membaca item_type mentah, baris dropcore dari
        // master tak terdeteksi dan kabel tercatat dua kali.
        // Sejak kategori pindah ke tabel, nilai yang dibandingkan sudah string
        // code biasa — cast enum yang dulu bikin banding selalu false sudah
        // dilepas dari model.
        // Kolom di-prefix tabel: tanpa itu `whereIn('id', ...)` jadi ambigu
        // begitu join item_categories ikut (dua tabel sama-sama punya `id`).
        $masterTypes = Item::whereIn('items.id', collect($materialRows)->pluck('item_id')->filter())
            ->join('item_categories', 'item_categories.id', '=', 'items.item_category_id')
            ->pluck('item_categories.code', 'items.id');

        $hasCableRow = collect($materialRows)->contains(function ($row) use ($masterTypes) {
            $type = ! empty($row['item_id'])
                ? ($masterTypes[$row['item_id']] ?? null)
                : ($row['item_type'] ?? null);

            return $type === ItemCategory::CODE_KABEL_DROPCORE;
        });

        if ($cableMeter > 0 && ! $hasCableRow) {
            array_unshift($materialRows, [
                'item_id' => null,
                'item_name' => 'Kabel Dropcore',
                'item_type' => ItemCategory::CODE_KABEL_DROPCORE,
                'qty' => $cableMeter,
                'unit' => 'meter',
                'note' => 'Otomatis dari estimasi kabel survey',
            ]);
        }

        if ($request->hasFile('survey_photo')) {
            $validated['survey_photo'] = FileUploadService::uploadSurveyPhoto($request->file('survey_photo'), $customer, 'odp');
        }

        if ($request->hasFile('house_photo')) {
            $validated['house_photo'] = FileUploadService::uploadSurveyPhoto($request->file('house_photo'), $customer, 'house');
        }

        DB::transaction(function () use ($customer, $validated, $materialRows, $workToolRows, $workflowService) {
            $survey = $customer->latestSurvey()->first();

            if (! $survey) {
                $survey = new CustomerSurvey(['customer_id' => $customer->id]);
            }

            $survey->fill($validated);

            $task = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::SURVEY->value)
                ->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::PENDING->value])
                ->latest('id')
                ->first();

            if (! $survey->completed_at) {
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

                $surveyorsText = "Petugas Survey {$memberIndex} - ".auth()->user()->name;

                $otherMembers = $teamMembers->filter(fn ($m) => $m->user_id != $currentUserId)->values();
                if ($otherMembers->isNotEmpty()) {
                    $survey->surveyor_2_id = $otherMembers[0]->user_id;
                }
                if ($otherMembers->count() > 1) {
                    $survey->surveyor_3_id = $otherMembers[1]->user_id;
                }
            } else {
                $surveyorsText = 'Petugas Survey 1 - '.auth()->user()->name;
            }

            $survey->surveyors = $surveyorsText;

            $survey->save();

            // Estimasi material menempel di FopTask SURVEY pelanggan ini. Kalau
            // anchor-nya belum ada, DIBUAT sekarang juga — bukan dilewat.
            // Melewatnya membuang estimasi material + checklist alat yang barusan
            // diisi teknisi tanpa satu pun pesan error, dan halaman Verifikasi
            // Admin selamanya menampilkan seksi kosong (gejala nyata di
            // produksi: 1791 survey, 0 baris task_materials).
            $materialService = app(TaskMaterialService::class);
            $surveyFopTask = $materialService->resolveTaskFor($customer, TaskType::SURVEY)
                ?? app(FopTaskProvisioningService::class)->ensureForCustomer($customer, TaskType::SURVEY);

            if ($surveyFopTask) {
                $materialService->sync($surveyFopTask, MaterialKind::ESTIMASI, $materialRows, auth()->id());
                // Anchor yang sama dipakai checklist alat — dilewat dengan alasan
                // yang sama kalau FopTask belum terbentuk.
                app(TaskWorkToolService::class)->sync($surveyFopTask, $workToolRows, auth()->id());
            }

            if ($validated['survey_status'] === 'completed' && $customer->status === 'survey_in_progress') {
                // Selesaikan task survey jika ada
                if ($task) {
                    app(TaskService::class)->complete($task, auth()->user());
                }

                $workflowService->transition($customer, WorkflowTransition::WAITING_ACC, 'Survey lapangan selesai dilaporkan');
                try {
                    event(new SurveyCompleted($customer));
                } catch (\Exception $e) {
                    Log::error('Gagal broadcast SurveyCompleted: '.$e->getMessage());
                }

                try {
                    $telegram = app(TelegramBotService::class);
                    $message = "✅ <b>Survey Selesai</b>\n";
                    $message .= "Pelanggan: {$customer->full_name}\n";
                    $message .= "No. HP: {$customer->primary_phone}\n";
                    $message .= "Alamat: {$customer->address}\n";
                    $message .= 'Menunggu Verifikasi Admin untuk Pemasangan.';
                    $telegram->sendMessage($message);
                } catch (\Exception $e) {
                    Log::error('Gagal mengirim notifikasi Telegram: '.$e->getMessage());
                }
            }

            if ($validated['survey_status'] === 'failed' && $customer->status === 'survey_in_progress') {
                // Tutup task survey sebagai dibatalkan — pelanggan tidak layak pasang
                if ($task) {
                    app(TaskService::class)->cancel($task, auth()->user(), $survey->survey_note);
                }

                $workflowService->transition($customer, WorkflowTransition::REJECTED, $survey->survey_note);
            }
        });

        if ($validated['survey_status'] === 'failed') {
            return redirect(SafeUrl::resolveReturnTo($request->input('return_to'), 'surveys.queue'))
                ->with('success', 'Survey selesai dilaporkan: pelanggan tidak layak pasang.');
        }

        return redirect(SafeUrl::resolveReturnTo($request->input('return_to'), 'verifications.queue'))
            ->with('success', 'Data survey berhasil disimpan.');
    }
}
