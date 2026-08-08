<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CustomerWorkflowService;
use App\Services\EffectiveAccessService;
use App\Services\InitialInvoiceService;
use App\Services\TaskMaterialService;
use App\Services\TeknisiWorkloadService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerVerificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.view'), 403);

        $statuses = [
            'waiting_acc',
            'surveyed',
            'waiting_installation',
            'installation_in_progress',
            'revision_installation',
            'installed',
            'verification_admin',
        ];

        $query = Customer::with([
            'village.district',
            'pop',
            'customerService',
            'latestInstallation.technician',
            'latestSurvey',
            'tasks' => function ($q) {
                $q->where('task_type', TaskType::SURVEY->value)
                    ->where('status', TaskStatus::SELESAI->value)
                    ->orderByDesc('completed_at')
                    ->limit(1);
            },
        ])
            ->applyUserScope()
            ->whereIn('status', $statuses);

        // Teknisi cuma boleh liat pelanggan yang Task Pemasangan-nya PERNAH
        // dijadwalkan buat dirinya — bukan seluruh antrean verifikasi/pemasangan.
        // NOC/FOP/Admin/Owner (hasFullAccess) tetap liat semua buat supervisi.
        // Lihat catatan sama di CustomerSurveyController::index().
        if (! auth()->user()->hasFullAccess() && auth()->user()->hasRole('teknisi')) {
            $query->whereHas('tasks', function ($q) {
                $q->where('task_type', TaskType::PEMASANGAN->value)
                    ->whereHas('teamMembers', fn ($tm) => $tm->where('user_id', auth()->id()));
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate(15);

        // Pakai hasAllPopAccess(), bukan `! empty($allowedPopIds)`: getAllowedPopIds()
        // mengembalikan array kosong untuk ALL_POP *dan* untuk user yang scope-nya
        // belum di-setup. Menafsirkan kosong = "jangan filter" membuat user tanpa
        // scope melihat seluruh teknisi lintas cabang (CLAUDE.md, bagian POP Scope).
        $accessService = app(EffectiveAccessService::class);
        $user = auth()->user();
        $teknisiList = app(TeknisiWorkloadService::class)->summarize(
            $accessService->hasAllPopAccess($user),
            $accessService->getAllowedPopIds($user),
        );

        return view('verifications.queue', compact('customers', 'teknisiList'));
    }

    /**
     * Fragment 3 <td> (STATUS, WAKTU LIVE, ACTION) satu baris antrean —
     * dipanggil dari verifications/queue.blade.php saat Echo nangkep
     * App\Events\CustomerVerificationStatusChanged, biar baris di layar admin
     * lain ikut sinkron tanpa reload (docs/plan/analisa-realtime-spa-
     * operasional.md §2.1 no. 10). Guard sama seperti showAdmin() — endpoint
     * ini dipanggil by ID langsung, bukan lewat query index() yang sudah scope.
     */
    public function row(Customer $customer)
    {
        $user = auth()->user();
        $hasPermission = $user->hasPermission('customers.detail.installation.validate')
            || $user->hasPermission('customers.detail.installation.view')
            || $user->hasPermission('customers.detail.survey.view')
            || $user->hasPermission('customers.detail.survey.update')
            || $user->hasPermission('customers.view')
            || $user->hasPermission('*');
        abort_unless($hasPermission, 403);

        $this->authorizeCustomerPopScope($user, $customer);

        if (! $user->hasFullAccess() && $user->hasRole('teknisi')) {
            $isAssigned = Task::where('customer_id', $customer->id)
                ->whereIn('task_type', [TaskType::SURVEY->value, TaskType::PEMASANGAN->value])
                ->whereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id))
                ->exists();

            abort_unless($isAssigned, 403, 'Anda bukan anggota tim yang ditugaskan untuk pelanggan ini.');
        }

        // Pelanggan sudah keluar dari cakupan antrean (mis. baru diaktifkan
        // atau ditolak) — kosong berarti "hapus baris ini", bukan error.
        $queueStatuses = [
            'waiting_acc', 'surveyed', 'waiting_installation', 'installation_in_progress',
            'revision_installation', 'installed', 'verification_admin',
        ];
        if (! in_array($customer->status, $queueStatuses, true)) {
            return response('', 204);
        }

        $customer->load([
            'latestInstallation',
            'tasks' => function ($q) {
                $q->where('task_type', TaskType::SURVEY->value)
                    ->where('status', TaskStatus::SELESAI->value)
                    ->orderByDesc('completed_at')
                    ->limit(1);
            },
        ]);

        return view('verifications.partials.queue-status-cells', ['customer' => $customer]);
    }

    public function showAdmin(Customer $customer)
    {
        $user = auth()->user();
        $hasPermission = $user->hasPermission('customers.detail.installation.validate')
            || $user->hasPermission('customers.detail.installation.view')
            || $user->hasPermission('customers.detail.survey.view')
            || $user->hasPermission('customers.detail.survey.update')
            || $user->hasPermission('customers.view')
            || $user->hasPermission('*');
        abort_unless($hasPermission, 403);

        $this->authorizeCustomerPopScope($user, $customer);

        // Guard assignment — halaman ini diakses LANGSUNG lewat URL
        // /verifications/{customer}/admin, gak lewat query index() yang udah
        // di-scope (lihat SurveyInstallationQueueScopeTest). Tanpa guard di
        // sini, teknisi yang gak punya customers.view tetep bisa nebak/ketik
        // ID pelanggan siapa pun dan buka detail Verifikasi & Pemasangan-nya,
        // nembus batasan "cuma liat yang dijadwalkan buat dirinya" (keluhan
        // #1). Dicek ke task SURVEY *atau* PEMASANGAN karena halaman ini
        // dipakai buat pelanggan di berbagai tahap (waiting_acc s/d
        // verification_admin) — task yang relevan beda-beda tergantung tahap.
        if (! $user->hasFullAccess() && $user->hasRole('teknisi')) {
            $isAssigned = Task::where('customer_id', $customer->id)
                ->whereIn('task_type', [TaskType::SURVEY->value, TaskType::PEMASANGAN->value])
                ->whereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id))
                ->exists();

            abort_unless($isAssigned, 403, 'Anda bukan anggota tim yang ditugaskan untuk pelanggan ini.');
        }

        $customer->loadMissing([
            'customerDevice',
            'customerTechnicalDetail',
            'latestInstallation.technician',
            'latestInstallation.technician2',
            'latestInstallation.technician3',
            'latestInstallation.fop',
            'latestSurvey.technician',
            'latestSurvey.surveyor2',
            'latestSurvey.surveyor3',
            'customerService',
            'internetPackage',
            'pop',
            'village.district',
            'city',
        ]);

        // Selisih estimasi vs realisasi material — inti nilai bisnis pencatatan
        // material sebelum modul Inventory ada. Kosong untuk pelanggan lama yang
        // laporannya dibuat sebelum fitur ini.
        $materialVariance = app(TaskMaterialService::class)->varianceForCustomer($customer);

        return view('verifications.admin', compact('customer', 'materialVariance'));
    }

    public function processToTeam(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        // Validasi status customer: harus waiting_acc atau surveyed
        abort_unless(
            in_array($customer->status, ['waiting_acc', 'surveyed']),
            422,
            'Status customer tidak valid untuk diproses ke TIM.'
        );

        try {
            DB::beginTransaction();

            // A. Create/Update record di customer_installations
            $installation = $customer->installations()
                ->whereIn('installation_status', ['scheduled', 'in_progress'])
                ->latest()
                ->first();

            if (! $installation) {
                $customer->installations()->create([
                    'installation_status' => 'scheduled',
                    'fop_id' => auth()->id(),
                    'assigned_at' => now(),
                ]);
            }

            // B. Transition Customer status to waiting_installation
            // Ini secara otomatis membuat Task Pemasangan dengan status 'pending' di CustomerWorkflowService
            $workflowService->transition($customer, WorkflowTransition::WAITING_INSTALLATION, 'Survey disetujui. Diproses ke TIM Pemasangan');

            // Task Pemasangan yang baru (atau yang udah ada) dari transition di
            // atas — dipakai buat notif FOP di bawah, BUKAN dibuat di sini
            // (CustomerWorkflowService::transition() yang bikin, idempotent
            // kalau task PENDING/TERJADWAL/IN_PROGRESS udah ada).
            $installTaskForNotif = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::PEMASANGAN->value)
                ->whereIn('status', [TaskStatus::PENDING->value, TaskStatus::TERJADWAL->value, TaskStatus::IN_PROGRESS->value])
                ->latest()
                ->first();

            // C. Otomatis setujui (approve) Task Survey yang terkait
            $surveyTask = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::SURVEY->value)
                ->where('status', TaskStatus::SELESAI->value)
                ->where('fop_review_status', 'pending')
                ->latest()
                ->first();
            if ($surveyTask) {
                $surveyTask->update([
                    'fop_review_status' => 'approved',
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            if ($surveyTask) {
                $this->notifyTaskTeam($surveyTask, 'Survey Disetujui: '.$surveyTask->task_number,
                    "Laporan survey Anda untuk {$customer->full_name} disetujui admin, diproses ke tim pemasangan.",
                    NotificationType::SUCCESS
                );
            }

            // FOP yang assign tim buat Task Pemasangan, BUKAN admin verifikasi
            // yang barusan aksi di sini — tanpa ini FOP cuma tau ada kerjaan
            // baru lewat cek dashboard manual (docs/plan/analisa-status-
            // implementasi-notifikasi.md §8.2 & §8.5, gap dilaporkan user
            // 2026-08-06). Cuma notif kalau task-nya BENERAN baru dibuat di
            // transition ini (belum pernah punya tim) — kalau udah ada tim
            // (mis. task lama dipakai ulang lewat jalur revisi), FOP udah
            // pernah kebagian notif assignment sebelumnya, jangan spam ulang.
            if ($installTaskForNotif && $customer->pop_id && ! $installTaskForNotif->teamMembers()->exists()) {
                $this->notifyRoleUsersInPop('fop', $customer->pop_id,
                    'Task Pemasangan Baru: '.$installTaskForNotif->task_number,
                    "Survey {$customer->full_name} disetujui, Task Pemasangan menunggu tim ditugaskan.",
                    route('tasks.show', $installTaskForNotif->id)
                );
            }

            return redirect()->back()->with('success', 'Survey berhasil disetujui. Pelanggan beralih ke status Menunggu Pemasangan.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal memproses: '.$e->getMessage());
        }
    }

    public function finalVerify(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        // Hanya field yang benar-benar diinput admin yang divalidasi di sini.
        // subtotal/discount/ppn/total_amount memang dikirim form (input readonly
        // hasil hitungan JavaScript), tapi SENGAJA tidak dipakai — readonly cuma
        // penghalang UI, POST manual bisa mengirim nominal apa pun. Nominal
        // otoritatif dihitung ulang di InitialInvoiceService di bawah.
        //
        // `prorate_amount_override` PENGECUALIAN: admin boleh menimpa hasil
        // hitung prorata otomatis (nego harga / koreksi pembulatan). Server
        // tetap menjaga floor 0 lewat InitialInvoiceService::calculate() —
        // jangan biarkan negatif lolos ke invoice.
        //
        // `billing_period` dan `due_date` juga tidak lagi diterima dari klien:
        // keduanya turunan matematis dari tanggal aktivasi, bukan keputusan
        // admin. Waktu masih diinput terpisah, admin bisa mengirim periode Juni
        // untuk prorata Juli — invoice tercetak dengan periode yang berbeda dari
        // bulan yang sebenarnya ditagih, dan bulan yang dilewati
        // GenerateMonthlyInvoicesCommand (dari `activation_date` = `issue_date`)
        // bukan bulan yang tertulis di tagihan.
        $validated = $request->validate([
            'issue_date' => 'required|date',
            'extra_installation_fee' => 'nullable|numeric|min:0',
            'extra_cable_fee' => 'nullable|numeric|min:0',
            'extra_pole_fee' => 'nullable|numeric|min:0',
            'other_fee' => 'nullable|numeric|min:0',
            'prorate_amount_override' => 'nullable|numeric|min:0',
        ]);

        $service = $customer->customerService;
        if (! $service) {
            return redirect()->back()->with('error', 'Data layanan pelanggan tidak ditemukan.');
        }

        $issueDate = Carbon::parse($validated['issue_date']);

        // Tagihan awal dibayar di tempat saat aktivasi, bukan menunggu tempo.
        // Tempo tanggal 10 hanya berlaku untuk tagihan bulanan
        // (GenerateMonthlyInvoicesCommand), jangan disamakan.
        $billingPeriod = $issueDate->format('Y-m');
        $dueDate = $issueDate->format('Y-m-d');

        $billing = app(InitialInvoiceService::class)->calculate(
            $service,
            $validated['issue_date'],
            $validated
        );

        $pop = $customer->pop;
        if (! $pop || ! $pop->cid_prefix) {
            return redirect()->back()->with('error', 'Konfigurasi POP/Cabang pelanggan belum lengkap.');
        }

        try {
            DB::beginTransaction();

            // 1. Generate Invoice
            $invoiceNumber = 'INV-'.now()->format('Ymd').'-'.strtoupper(uniqid());

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => InvoiceType::AWAL->value,
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'customer_service_id' => $service->id,
                'internet_package_id' => $service->internet_package_id,
                'billing_period' => $billingPeriod,
                'issue_date' => $validated['issue_date'],
                'due_date' => $dueDate,
                'subtotal' => $billing['subtotal'],
                'discount' => $billing['discount'],
                'ppn' => $billing['ppn'],
                'prorate_amount' => $billing['prorate_amount'],
                'extra_installation_fee' => $billing['extra_installation_fee'],
                'extra_cable_fee' => $billing['extra_cable_fee'],
                'extra_pole_fee' => $billing['extra_pole_fee'],
                'other_fee' => $billing['other_fee'],
                'total_amount' => $billing['total_amount'],
                'remaining_amount' => $billing['total_amount'],
                'paid_amount' => 0,
                'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                'created_by' => auth()->id(),
            ]);

            // 2. Activate Customer
            $customer->loadMissing(['customerTechnicalDetail', 'distribution', 'village']);
            $cid = $pop->generateComplexCid($customer, $customer->distribution);

            $oldValues = [
                'cid' => $customer->cid,
                'status' => $customer->status,
                'data_completeness_status' => $customer->data_completeness_status,
                'service_status' => $service->service_status,
                'billing_status' => $service->billing_status,
                'activation_date' => $service->activation_date?->format('Y-m-d'),
            ];

            $customer->update([
                'cid' => $cid,
                'status' => 'active',
                'data_completeness_status' => 'siap_billing',
            ]);

            // `activation_date` WAJIB ditimpa di sini, tidak boleh dipertahankan.
            // Saat pendaftaran nilainya diisi `registration_date` (lihat
            // CustomerController::store) — itu tanggal DAFTAR, bukan tanggal
            // layanan menyala. Kalau dibiarkan, pelanggan yang daftar Juni lalu
            // aktif 21 Juli punya activation_date Juni, sementara invoice AWAL
            // berperiode Juli. GenerateMonthlyInvoicesCommand melewati bulan
            // aktivasi berdasarkan kolom ini, jadi bulan Juli tidak dilewati dan
            // pelanggan menerima invoice BULANAN Juli DI ATAS invoice AWAL Juli.
            // Dua lapis penjaga sisanya tidak menangkap ini: keduanya (query
            // `alreadyExists` di command dan InvoiceObserver::creating) di-scope
            // per `invoice_type`, sedangkan AWAL vs BULANAN jenisnya beda. Tabel
            // `invoices` juga tidak punya unique index (migration duplicate_guard
            // hanya memasangnya di `payments`).
            //
            // Sumbernya `issue_date`, bukan `now()`: itu tanggal yang dipakai
            // InitialInvoiceService menghitung prorata. Basis prorata dan penanda
            // bulan aktivasi harus tanggal yang sama, kalau tidak celah dobel
            // tagih tadi terbuka lagi lewat pintu lain (verifikasi mundur/maju).
            $service->update([
                'service_status' => 'aktif',
                'billing_status' => 'active',
                'activation_date' => $validated['issue_date'],
                'activated_by_name' => auth()->user()->name,
                'activated_by_user_id' => auth()->id(),
                'activation_time' => $service->activation_time ?? now()->format('H:i:s'),
            ]);

            $newValues = [
                'cid' => $cid,
                'status' => 'active',
                'data_completeness_status' => 'siap_billing',
                'service_status' => 'aktif',
                'billing_status' => 'active',
                'activation_date' => $validated['issue_date'],
            ];

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Data Pelanggan',
                'action' => 'activate_from_verification',
                'auditable_type' => get_class($customer),
                'auditable_id' => $customer->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            // Optionally notify telegram
            try {
                $telegram = app(TelegramBotService::class);
                $message = "🎉 <b>Pelanggan Aktif (Dari Verifikasi)</b>\n";
                $message .= "Pelanggan: {$customer->full_name}\n";
                $message .= "CID: {$cid}\n";
                $message .= 'Tagihan Awal: Rp '.number_format($billing['total_amount'], 0, ',', '.')."\n";
                $message .= 'Diaktifkan oleh: '.auth()->user()->name;
                $telegram->sendMessage($message);
            } catch (\Exception $e) {
                // Ignore telegram errors
            }

            // Otomatis setujui (approve) Task Pemasangan yang terkait
            $installTask = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::PEMASANGAN->value)
                ->where('status', TaskStatus::SELESAI->value)
                ->where('fop_review_status', 'pending')
                ->latest()
                ->first();
            if ($installTask) {
                $installTask->update([
                    'fop_review_status' => 'approved',
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            if ($installTask) {
                $this->notifyTaskTeam($installTask, 'Pelanggan Diaktifkan: '.$installTask->task_number,
                    "Pemasangan {$customer->full_name} disetujui admin, pelanggan resmi aktif (CID {$cid}).",
                    NotificationType::SUCCESS
                );
            }

            // Customer Lifecycle: pendaftar asli (Sales/CS yang mendaftarkan,
            // `customers.created_by`) dikasih tau pelanggannya resmi aktif —
            // sebelumnya nol notif buat transisi besar status pelanggan
            // (docs/plan/analisa-status-implementasi-notifikasi.md §5).
            $this->notifyCustomerCreatorIfDifferentActor($customer, 'Pelanggan Aktif: '.$customer->full_name,
                "Pelanggan {$customer->full_name} (CID {$cid}) resmi aktif, tagihan awal sudah terbit.",
                NotificationType::SUCCESS
            );

            return redirect()->route('verifications.queue')->with('success', 'Pelanggan berhasil diaktifkan dan tagihan pertama dibuat.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function reject(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        // Tahap ditentukan dari status SEBELUM transition (transition di bawah
        // mengubah customer->status jadi 'rejected', jadi harus direkam duluan).
        $isInstallStage = in_array($customer->status, [
            'installation_in_progress', 'revision_installation', 'installed', 'verification_admin',
        ], true);

        try {
            DB::beginTransaction();

            $workflowService->transition($customer, WorkflowTransition::REJECTED, 'Ditolak: '.$request->reason);

            // Otomatis tolak (reject) Task Survey atau Pemasangan yang terkait,
            // tergantung tahap penolakan. Reject di tahap survey TIDAK boleh
            // menyentuh Task Pemasangan (belum tentu ada), begitu juga sebaliknya.
            $rejectedTaskType = $isInstallStage
                ? TaskType::PEMASANGAN
                : TaskType::SURVEY;

            $rejectedTask = Task::where('customer_id', $customer->id)
                ->where('task_type', $rejectedTaskType->value)
                ->where('status', TaskStatus::SELESAI->value)
                ->where('fop_review_status', 'pending')
                ->latest()
                ->first();
            if ($rejectedTask) {
                $rejectedTask->update([
                    'fop_review_status' => 'rejected',
                    'reject_reason' => $request->reason,
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            if ($rejectedTask) {
                $this->notifyTaskTeam($rejectedTask, 'Laporan Ditolak: '.$rejectedTask->task_number,
                    "Laporan {$rejectedTaskType->label()} Anda untuk {$customer->full_name} ditolak admin. Alasan: {$request->reason}",
                    NotificationType::ERROR
                );
            }

            return redirect()->back()->with('success', 'Pelanggan berhasil ditolak.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function revisi(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Mark the latest installation as failed or needing revision
            $latestInstallation = $customer->latestInstallation;
            if ($latestInstallation) {
                $latestInstallation->update([
                    'installation_status' => 'in_progress', // Maintain in_progress so technician can update it
                    'installation_note' => 'REVISI ADMIN: '.$request->reason."\n\n".($latestInstallation->installation_note ?? ''),
                ]);
            }

            $workflowService->transition($customer, WorkflowTransition::REVISION_INSTALLATION, 'Revisi Pemasangan: '.$request->reason);

            // Otomatis kembalikan (revert) Task Pemasangan yang terkait ke status In Progress
            $installTask = Task::where('customer_id', $customer->id)
                ->where('task_type', TaskType::PEMASANGAN->value)
                ->where('status', TaskStatus::SELESAI->value)
                ->where('fop_review_status', 'pending')
                ->latest()
                ->first();
            if ($installTask) {
                $installTask->update([
                    'status' => TaskStatus::IN_PROGRESS->value,
                    'fop_review_status' => 'rejected',
                    'reject_reason' => $request->reason,
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();

            if ($installTask) {
                $this->notifyTaskTeam($installTask, 'Pemasangan Perlu Revisi: '.$installTask->task_number,
                    "Pemasangan {$customer->full_name} diminta admin buat direvisi. Alasan: {$request->reason}",
                    NotificationType::WARNING
                );
            }

            return redirect()->route('verifications.queue')->with('success', 'Pelanggan dikembalikan ke antrean pemasangan untuk revisi.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Guard per-record — index() sudah di-scope applyUserScope(), tapi row()/
     * showAdmin() diakses LANGSUNG lewat ID (bukan lewat query index()), jadi
     * scope-nya wajib dicek ulang di sini. Tanpa ini, user ber-scope
     * selected_pop/pop_tree tinggal tebak/iterasi ID pelanggan buat baca
     * antrean verifikasi POP lain (IDOR, lihat docs/plan/analisa-celah-scope-pop.md).
     */
    private function authorizeCustomerPopScope(User $user, Customer $customer): void
    {
        $access = app(EffectiveAccessService::class);

        if ($access->hasAllPopAccess($user)) {
            return;
        }

        abort_unless(
            in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true),
            403,
            'Anda tidak memiliki akses ke pelanggan di POP ini.'
        );
    }

    /**
     * Notif hasil verifikasi ke teknisi yang laporannya diperiksa — sebelumnya
     * approve/reject/revisi di sini gak ngasih tau siapa pun sama sekali
     * (docs/plan/analisa-status-implementasi-notifikasi.md §5). Pola sama
     * dengan TaskController::notifyTeamMembers().
     */
    private function notifyTaskTeam(Task $task, string $title, string $message, NotificationType $type): void
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

    /**
     * Semua user berrole $roleCode yang punya akses ke POP $popId (lewat
     * EffectiveAccessService scope: all_pop, atau selected_pop/pop_tree yang
     * nyakup POP ini) — pola sama persis `TicketService::usersWithRoleInPop()`
     * / query "notify FOP users" di `TaskService::complete()`, disalin bukan
     * diekstrak jadi shared service karena cuma dipakai 1 titik di kelas ini
     * (lihat CLAUDE.md: hindari abstraksi sebelum dibutuhkan).
     *
     * @return Collection<int, User>
     */
    private function usersWithRoleInPop(string $roleCode, int $popId): Collection
    {
        return User::whereHas('role', fn ($q) => $q->where('code', $roleCode))
            ->where(function ($query) use ($popId) {
                $query->whereHas('roleScopes', fn ($q) => $q->where('scope_type', ScopeType::ALL_POP->value))
                    ->orWhereHas('roleScopes', fn ($q) => $q->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                        ->whereHas('targets', fn ($t) => $t->where('pop_id', $popId))
                    );
            })
            ->get();
    }

    private function notifyRoleUsersInPop(string $roleCode, int $popId, string $title, string $message, string $actionUrl): void
    {
        foreach ($this->usersWithRoleInPop($roleCode, $popId) as $user) {
            $user->notify(new AppNotification(
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                type: NotificationType::INFO
            ));
        }
    }

    /**
     * Notif ke pendaftar asli pelanggan (`customers.created_by`), skip kalau
     * yang aksi sekarang orangnya sendiri — pola sama
     * `TicketService::notifyCreatorIfDifferentActor()`.
     */
    private function notifyCustomerCreatorIfDifferentActor(Customer $customer, string $title, string $message, NotificationType $type): void
    {
        $creator = $customer->creator ?? ($customer->created_by ? User::find($customer->created_by) : null);

        if (! $creator || $creator->id === auth()->id()) {
            return;
        }

        $creator->notify(new AppNotification(
            title: $title,
            message: $message,
            actionUrl: route('customers.show', $customer->id),
            type: $type
        ));
    }
}
