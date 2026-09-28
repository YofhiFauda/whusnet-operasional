<?php

namespace App\Http\Controllers;

use App\Enums\CReqCategory;
use App\Enums\CReqVerificationStatus;
use App\Enums\TaskType;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Services\EffectiveAccessService;
use App\Support\ReasonValidationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean "Verifikasi Biaya C-REQ" — task C-REQ yang ditandai berbayar oleh
 * teknisi lewat Laporan C-REQ (`TaskMaintenanceController`, checkbox "Task
 * ini berbayar") menunggu disetujui/ditolak CS (role helpdesk) sebelum boleh
 * diteruskan ke Tagihan Manual (`InvoiceController::create()`).
 *
 * Pola meniru `CustomerRegistrationVerificationController` (index/show/aksi
 * tulis, POP-scoped, AuditLog) — approve TIDAK membuat invoice otomatis,
 * cuma menandai verified & mengarahkan ke form Tagihan Manual dengan
 * pelanggan + kategori + catatan sudah terisi (CS tetap yang submit).
 *
 * docs/plan/task-teknisi/rancangan-biaya-creq-verifikasi-cs.md
 */
class TaskCreqBillingController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status', CReqVerificationStatus::PENDING->value);
        if (! in_array($statusFilter, array_column(CReqVerificationStatus::cases(), 'value'), true)) {
            $statusFilter = CReqVerificationStatus::PENDING->value;
        }

        $tasks = Task::query()
            ->applyUserScope()
            ->where('task_type', TaskType::CREQ->value)
            ->whereHas('creqDetail', function ($q) use ($statusFilter) {
                $q->where('is_billable', true)->where('verification_status', $statusFilter);
            })
            ->with(['customer', 'pop', 'creqDetail'])
            ->latest('completed_at')
            ->paginate(15)
            ->withQueryString();

        return view('tasks.creq-billing.index', compact('tasks', 'statusFilter'));
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorizeBillable($request, $task);

        $task->loadMissing(['customer.pop', 'pop', 'creqDetail.verifier', 'maintenanceReport']);

        return view('tasks.creq-billing.show', compact('task'));
    }

    public function approve(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeBillable($request, $task);

        $detail = DB::transaction(function () use ($task, $request) {
            // lockForUpdate() DI DALAM transaksi — menutup celah TOCTOU dua
            // request approve/reject bersamaan (baca PENDING, dua-duanya
            // lolos abort_unless, lalu saling timpa hasil verifikasi).
            $detail = TaskCreqDetail::whereKey($task->creqDetail->id)->lockForUpdate()->firstOrFail();

            abort_unless($detail->verification_status === CReqVerificationStatus::PENDING, 422, 'Verifikasi biaya C-REQ ini sudah diproses.');

            $oldStatus = $detail->verification_status->value;

            $detail->update([
                'verification_status' => CReqVerificationStatus::VERIFIED->value,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Verifikasi Biaya C-REQ',
                'action' => 'approve',
                'auditable_type' => TaskCreqDetail::class,
                'auditable_id' => $detail->id,
                'old_values' => ['verification_status' => $oldStatus],
                'new_values' => ['verification_status' => CReqVerificationStatus::VERIFIED->value],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            return $detail;
        });

        $category = $detail->category;

        return redirect()
            ->route('invoices.create', [
                'customer_id' => $task->customer_id,
                'manual_category' => $category->toManualInvoiceCategory()->value,
                'manual_subtype_name' => $category === CReqCategory::LAINNYA ? $detail->category_custom_name : null,
                'description' => $detail->billing_note,
            ])
            ->with('success', "Biaya C-REQ task {$task->task_number} disetujui — lanjutkan isi Tagihan Manual.");
    }

    public function reject(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeBillable($request, $task);

        $validated = $request->validate([
            'reason' => ReasonValidationRule::required(1000),
        ]);

        DB::transaction(function () use ($task, $validated, $request) {
            // Sama alasan lockForUpdate() di approve() — cegah reject
            // menimpa approve (atau sebaliknya) yang diproses bersamaan.
            $detail = TaskCreqDetail::whereKey($task->creqDetail->id)->lockForUpdate()->firstOrFail();

            abort_unless($detail->verification_status === CReqVerificationStatus::PENDING, 422, 'Verifikasi biaya C-REQ ini sudah diproses.');

            $oldStatus = $detail->verification_status->value;

            $detail->update([
                'verification_status' => CReqVerificationStatus::REJECTED->value,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'rejection_reason' => $validated['reason'],
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Verifikasi Biaya C-REQ',
                'action' => 'reject',
                'auditable_type' => TaskCreqDetail::class,
                'auditable_id' => $detail->id,
                'old_values' => ['verification_status' => $oldStatus],
                'new_values' => ['verification_status' => CReqVerificationStatus::REJECTED->value, 'reason' => $validated['reason']],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('tasks.creq-billing.index')
            ->with('success', "Biaya C-REQ task {$task->task_number} ditolak.");
    }

    /**
     * Task harus C-REQ berbayar (bukan sekadar C-REQ apa pun) & di dalam POP
     * scope aktor — sama pagar yang dipakai `index()` di atas, tapi wajib
     * dicek ulang di sini karena route model binding tidak lewat query yang
     * sudah `applyUserScope()`.
     */
    private function authorizeBillable(Request $request, Task $task): void
    {
        $task->loadMissing('creqDetail');

        abort_unless(
            $task->task_type === TaskType::CREQ && $task->creqDetail?->is_billable,
            404,
            'Task ini bukan C-REQ berbayar yang menunggu verifikasi.'
        );

        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        // (int) cast — sama dengan TaskPolicy::withinPopScope(); tanpa cast,
        // pop_id string ('5' !== 5) bikin CS yang scope-nya sah kena 403.
        if (! $access->hasAllPopAccess($user) && ! in_array((int) $task->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Task ini di luar POP scope Anda.');
        }
    }
}
