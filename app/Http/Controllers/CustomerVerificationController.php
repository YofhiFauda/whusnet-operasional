<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Services\CustomerWorkflowService;
use App\Services\EffectiveAccessService;
use App\Services\InitialInvoiceService;
use App\Services\TeknisiWorkloadService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        ])->whereIn('status', $statuses);

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

        return view('verifications.admin', compact('customer'));
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
        // subtotal/discount/ppn/prorate_amount/total_amount memang dikirim form
        // (input readonly hasil hitungan JavaScript), tapi SENGAJA tidak dipakai —
        // readonly cuma penghalang UI, POST manual bisa mengirim nominal apa pun.
        // Nominal otoritatif dihitung ulang di InitialInvoiceService di bawah.
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
                'customer_status' => $customer->customer_status,
                'data_completeness_status' => $customer->data_completeness_status,
                'service_status' => $service->service_status,
                'billing_status' => $service->billing_status,
                'activation_date' => $service->activation_date?->format('Y-m-d'),
            ];

            $customer->update([
                'cid' => $cid,
                'status' => 'active',
                'customer_status' => 'aktif',
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
                'customer_status' => 'aktif',
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

            return redirect()->route('verifications.queue')->with('success', 'Pelanggan dikembalikan ke antrean pemasangan untuk revisi.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }
}
