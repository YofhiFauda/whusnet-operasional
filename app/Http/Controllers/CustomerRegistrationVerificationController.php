<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CustomerWorkflowService;
use App\Services\EffectiveAccessService;
use App\Services\FopTaskProvisioningService;
use App\Support\ReasonValidationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean "Verifikasi Registrasi" (ADHOC-73) — pelanggan hasil Registrasi
 * (non-Skip-Survey) berhenti di `WorkflowTransition::REGISTERED` TANPA
 * Task/FopTask Survey apa pun sampai Admin/CS menyetujui di sini. Sebelumnya
 * `CustomerController::store()` langsung bikin Task+FopTask Survey seketika
 * submit, jadi FOP kebanjiran entri yang belum ditinjau siapa pun.
 *
 * Skip Survey TIDAK tersentuh sama sekali (dikonfirmasi user) — jalur itu
 * transisi langsung `REGISTERED → WAITING_INSTALLATION` + Task PEMASANGAN
 * di `store()`, tidak pernah singgah di status `registered` cukup lama untuk
 * muncul di antrean ini.
 *
 * Pola meniru `BusinessDevelopmentVerificationController` (index/show/aksi
 * tulis, POP-scoped, AuditLog, notifikasi in-app) — beda di titik approve
 * TERPISAH dari reject sebagai permission statis (bukan gerbang view + logic
 * dinamis), karena keduanya aksi independen yang wajar dipisah PIC-nya.
 *
 * Lihat docs/plan/pendaftaran-pelanggan/analisa-verifikasi-registrasi.md.
 */
class CustomerRegistrationVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        $query = Customer::with(['pop', 'customerService.internetPackage', 'village.district'])
            ->where('status', WorkflowTransition::REGISTERED->value)
            ->when(! $access->hasAllPopAccess($user), function ($q) use ($access, $user) {
                $q->whereIn('pop_id', $access->getAllowedPopIds($user));
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('identity_number', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at')->paginate(15)->withQueryString();

        return view('customer-registration-verifications.index', compact('customers'));
    }

    public function show(Request $request, Customer $customer): View
    {
        $customer->loadMissing(['pop', 'customerAddress', 'customerService.internetPackage', 'documents', 'village.district']);

        abort_unless($customer->status === WorkflowTransition::REGISTERED->value, 404, 'Pelanggan ini tidak sedang menunggu Verifikasi Registrasi.');

        $this->authorizePopScope($request, $customer);

        return view('customer-registration-verifications.show', compact('customer'));
    }

    public function approve(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->status === WorkflowTransition::REGISTERED->value, 404, 'Pelanggan ini tidak sedang menunggu Verifikasi Registrasi.');
        $this->authorizePopScope($request, $customer);

        DB::transaction(function () use ($customer, $request) {
            // Logic dipindah PERSIS dari CustomerController::store() (sebelum
            // ADHOC-73) — Task antrean (Survey) + FopTask anchor-nya. FopTask
            // wajib ada sebelum Laporan Survey disubmit: dia anchor
            // task_materials & task_work_tools (lihat FopTaskProvisioningService).
            $year = date('Y');
            $count = Task::whereYear('created_at', $year)->count() + 1;
            Task::create([
                'task_number' => sprintf('TASK-%s-%04d', $year, $count),
                'task_type' => TaskType::SURVEY->value,
                'title' => 'Survey Calon Pelanggan: '.$customer->full_name,
                'description' => null,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::PENDING->value,
                'created_by' => auth()->id() ?? 1,
                'updated_by' => auth()->id() ?? 1,
            ]);

            app(FopTaskProvisioningService::class)->ensureForCustomer($customer, TaskType::SURVEY);

            $oldStatus = $customer->status;
            app(CustomerWorkflowService::class)->transition($customer, WorkflowTransition::WAITING_SURVEY, 'Registrasi diverifikasi & disetujui Admin/CS');

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Data Pelanggan',
                'action' => 'approve_registration',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => WorkflowTransition::WAITING_SURVEY->value],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            $this->notifyCreator($customer, 'Registrasi Disetujui: '.$customer->full_name,
                "Registrasi {$customer->full_name} disetujui — pelanggan masuk antrean survey.");
        });

        return redirect()
            ->route('customer-registration-verifications.index')
            ->with('success', "Registrasi {$customer->full_name} disetujui, pelanggan masuk antrean survey.");
    }

    public function reject(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($customer->status === WorkflowTransition::REGISTERED->value, 404, 'Pelanggan ini tidak sedang menunggu Verifikasi Registrasi.');
        $this->authorizePopScope($request, $customer);

        $validated = $request->validate([
            'reason' => ReasonValidationRule::required(1000),
        ]);

        DB::transaction(function () use ($customer, $validated, $request) {
            $oldStatus = $customer->status;
            app(CustomerWorkflowService::class)->transition($customer, WorkflowTransition::REJECTED, $validated['reason']);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Data Pelanggan',
                'action' => 'reject_registration',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => WorkflowTransition::REJECTED->value, 'reason' => $validated['reason']],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            $this->notifyCreator($customer, 'Registrasi Ditolak: '.$customer->full_name,
                "Registrasi {$customer->full_name} ditolak. Alasan: {$validated['reason']}");
        });

        return redirect()
            ->route('customer-registration-verifications.index')
            ->with('success', "Registrasi {$customer->full_name} ditolak.");
    }

    private function authorizePopScope(Request $request, Customer $customer): void
    {
        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        if (! $access->hasAllPopAccess($user) && ! in_array($customer->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Pelanggan ini di luar POP scope Anda.');
        }
    }

    private function notifyCreator(Customer $customer, string $title, string $message): void
    {
        $creator = $customer->creator ?? ($customer->created_by ? User::find($customer->created_by) : null);
        if ($creator && $creator->id !== auth()->id()) {
            $creator->notify(new AppNotification(
                title: $title,
                message: $message,
                actionUrl: route('customers.show', $customer->id),
                type: NotificationType::INFO
            ));
        }
    }
}
