<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerWorkflowService;
use Illuminate\Http\Request;
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
            'verification_admin'
        ];

        $query = Customer::with([
            'village.district', 
            'pop', 
            'customerService', 
            'latestInstallation.technician', 
            'latestSurvey',
            'tasks' => function ($q) {
                $q->where('task_type', \App\Enums\TaskType::SURVEY->value)
                  ->where('status', \App\Enums\TaskStatus::SELESAI->value)
                  ->orderByDesc('completed_at')
                  ->limit(1);
            }
        ])->whereIn('status', $statuses);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate(15);

        $accessService = app(\App\Services\EffectiveAccessService::class);
        $allowedPopIds = $accessService->getAllowedPopIds(auth()->user());
        $teknisiList = $this->getTeknisiList($allowedPopIds);

        return view('verifications.queue', compact('customers', 'teknisiList'));
    }

    public function showAdmin(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        if (!in_array($customer->status, ['installed', 'verification_admin'])) {
            return redirect()->route('verifications.queue')
                ->with('error', 'Pelanggan tidak dalam status Verifikasi Admin.');
        }

        $customer->loadMissing([
            'customerDevice',
            'customerTechnicalDetail',
            'latestInstallation',
            'customerService',
            'internetPackage',
            'pop',
            'village.district',
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

            if (!$installation) {
                $customer->installations()->create([
                    'installation_status' => 'scheduled',
                    'fop_id'              => auth()->id(),
                    'assigned_at'         => now(),
                ]);
            }

            // B. Transition Customer status to waiting_installation
            // Ini secara otomatis membuat Task Pemasangan dengan status 'pending' di CustomerWorkflowService
            $workflowService->transition($customer, \App\Enums\WorkflowTransition::WAITING_INSTALLATION, 'Survey disetujui. Diproses ke TIM Pemasangan');

            // C. Otomatis setujui (approve) Task Survey yang terkait
            $surveyTask = \App\Models\Task::where('customer_id', $customer->id)
                ->where('task_type', \App\Enums\TaskType::SURVEY->value)
                ->where('status', \App\Enums\TaskStatus::SELESAI->value)
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
            return redirect()->back()->with('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }

    public function finalVerify(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        $validated = $request->validate([
            'billing_period' => 'required|string',
            'issue_date'     => 'required|date',
            'due_date'       => 'required|date',
            'subtotal'       => 'required|numeric',
            'discount'       => 'required|numeric',
            'ppn'            => 'required|numeric',
            'prorate_amount' => 'required|numeric',
            'extra_installation_fee' => 'nullable|numeric',
            'extra_cable_fee' => 'nullable|numeric',
            'extra_pole_fee' => 'nullable|numeric',
            'total_amount'   => 'required|numeric'
        ]);

        $service = $customer->customerService;
        if (!$service) {
            return redirect()->back()->with('error', 'Data layanan pelanggan tidak ditemukan.');
        }

        $pop = $customer->pop;
        if (!$pop || !$pop->cid_prefix) {
            return redirect()->back()->with('error', 'Konfigurasi POP/Cabang pelanggan belum lengkap.');
        }

        try {
            DB::beginTransaction();

            // 1. Generate Invoice
            $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . strtoupper(uniqid());

            $invoice = \App\Models\Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => \App\Enums\InvoiceType::AWAL->value,
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'customer_service_id' => $service->id,
                'internet_package_id' => $service->internet_package_id,
                'billing_period' => $validated['billing_period'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $validated['subtotal'],
                'discount' => $validated['discount'],
                'ppn' => $validated['ppn'],
                'prorate_amount' => $validated['prorate_amount'],
                'extra_installation_fee' => $validated['extra_installation_fee'] ?? 0,
                'extra_cable_fee' => $validated['extra_cable_fee'] ?? 0,
                'extra_pole_fee' => $validated['extra_pole_fee'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'remaining_amount' => $validated['total_amount'],
                'paid_amount' => 0,
                'invoice_status' => 'belum_dibayar',
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
            ];

            $customer->update([
                'cid' => $cid,
                'status' => 'active',
                'customer_status' => 'aktif',
                'data_completeness_status' => 'siap_billing',
            ]);

            $service->update([
                'service_status' => 'aktif',
                'billing_status' => 'active',
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
            ];

            \App\Models\AuditLog::create([
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
                $telegram = app(\App\Services\TelegramBotService::class);
                $message = "🎉 <b>Pelanggan Aktif (Dari Verifikasi)</b>\n";
                $message .= "Pelanggan: {$customer->full_name}\n";
                $message .= "CID: {$cid}\n";
                $message .= "Tagihan Awal: Rp " . number_format($validated['total_amount'], 0, ',', '.') . "\n";
                $message .= "Diaktifkan oleh: " . auth()->user()->name;
                $telegram->sendMessage($message);
            } catch (\Exception $e) {
                // Ignore telegram errors
            }

            // Otomatis setujui (approve) Task Pemasangan yang terkait
            $installTask = \App\Models\Task::where('customer_id', $customer->id)
                ->where('task_type', \App\Enums\TaskType::PEMASANGAN->value)
                ->where('status', \App\Enums\TaskStatus::SELESAI->value)
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
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);
        
        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        try {
            DB::beginTransaction();
            
            $workflowService->transition($customer, \App\Enums\WorkflowTransition::REJECTED, 'Ditolak: ' . $request->reason);

            // Otomatis tolak (reject) Task Survey yang terkait
            $surveyTask = \App\Models\Task::where('customer_id', $customer->id)
                ->where('task_type', \App\Enums\TaskType::SURVEY->value)
                ->where('status', \App\Enums\TaskStatus::SELESAI->value)
                ->where('fop_review_status', 'pending')
                ->latest()
                ->first();
            if ($surveyTask) {
                $surveyTask->update([
                    'fop_review_status' => 'rejected',
                    'reject_reason' => $request->reason,
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Pelanggan berhasil ditolak.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function revisi(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);
        
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            DB::beginTransaction();
            
            // Mark the latest installation as failed or needing revision
            $latestInstallation = $customer->latestInstallation;
            if ($latestInstallation) {
                $latestInstallation->update([
                    'installation_status' => 'in_progress', // Maintain in_progress so technician can update it
                    'installation_note' => "REVISI ADMIN: " . $request->reason . "\n\n" . ($latestInstallation->installation_note ?? '')
                ]);
            }

            $workflowService->transition($customer, \App\Enums\WorkflowTransition::REVISION_INSTALLATION, 'Revisi Pemasangan: ' . $request->reason);

            // Otomatis kembalikan (revert) Task Pemasangan yang terkait ke status In Progress
            $installTask = \App\Models\Task::where('customer_id', $customer->id)
                ->where('task_type', \App\Enums\TaskType::PEMASANGAN->value)
                ->where('status', \App\Enums\TaskStatus::SELESAI->value)
                ->where('fop_review_status', 'pending')
                ->latest()
                ->first();
            if ($installTask) {
                $installTask->update([
                    'status' => \App\Enums\TaskStatus::IN_PROGRESS->value,
                    'fop_review_status' => 'rejected',
                    'reject_reason' => $request->reason,
                    'updated_by' => auth()->id(),
                ]);
            }

            DB::commit();
            return redirect()->route('verifications.queue')->with('success', 'Pelanggan dikembalikan ke antrean pemasangan untuk revisi.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    private function getTeknisiList(array $allowedPopIds): \Illuminate\Support\Collection
    {
        $today = \Illuminate\Support\Carbon::today();
        $query = User::with(['roleScopes.targets'])
            ->whereHas('role', fn ($q) => $q->where('code', 'teknisi'))
            ->orderBy('name');

        if (!empty($allowedPopIds)) {
            $query->whereHas('roleScopes.targets', fn ($q) => $q->whereIn('pop_id', $allowedPopIds));
        }

        return $query->get()->map(function (User $teknisi) use ($today) {
            // Task in progress aktif
            $activeTask = \App\Models\Task::with('customer')
                ->whereHas('teamMembers', fn ($q) => $q->where('user_id', $teknisi->id))
                ->where('status', \App\Enums\TaskStatus::IN_PROGRESS->value)
                ->latest('started_at')
                ->first();

            // Hitung task terjadwal hari ini + task terjadwal/in_progress overdue
            $taskCount = \App\Models\Task::whereHas('teamMembers', fn ($q) => $q->where('user_id', $teknisi->id))
                ->where(function ($q) use ($today) {
                    $q->where(function ($q1) use ($today) {
                        $q1->where('status', \App\Enums\TaskStatus::TERJADWAL->value)
                           ->whereDate('scheduled_at', $today);
                    })
                    ->orWhere(function ($q2) use ($today) {
                        $q2->whereIn('status', [\App\Enums\TaskStatus::TERJADWAL->value, \App\Enums\TaskStatus::IN_PROGRESS->value])
                           ->whereDate('scheduled_at', '<', $today);
                    });
                })
                ->count();

            $status = 'standby';
            if ($activeTask) {
                $status = 'aktif';
            } elseif ($taskCount > 0) {
                $status = 'terjadwal';
            }

            return [
                'id'          => $teknisi->id,
                'name'        => $teknisi->name,
                'status'      => $status,
                'task_count'  => $taskCount,
            ];
        });
    }
}
