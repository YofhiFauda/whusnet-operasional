<?php

namespace App\Http\Controllers;

use App\Enums\CReqVerificationStatus;
use App\Enums\TaskType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Task;
use App\Models\TaskCreqDetail;
use App\Services\EffectiveAccessService;
use App\Services\ManualCategoryInvoiceService;
use App\Support\ReasonValidationRule;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Antrean "Verifikasi Biaya C-REQ" — task C-REQ yang ditandai berbayar oleh
 * teknisi lewat Laporan C-REQ (`TaskMaintenanceController`, checkbox "Task
 * ini berbayar") menunggu disetujui/ditolak CS (role helpdesk).
 *
 * Pola meniru `CustomerRegistrationVerificationController` (index/show/aksi
 * tulis, POP-scoped, AuditLog). Sejak 2026-09-28 approve = "Setujui &
 * Terbitkan Tagihan": CS mengisi nominal di halaman ini, Tagihan Manual
 * terbit dan tertaut ke detail C-REQ dalam satu transaksi (lihat approve()).
 *
 * docs/plan/task-teknisi/rancangan-biaya-creq-verifikasi-cs.md
 */
class TaskCreqBillingController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status', CReqVerificationStatus::PENDING->value);
        if ($statusFilter !== 'all' && ! in_array($statusFilter, array_column(CReqVerificationStatus::cases(), 'value'), true)) {
            $statusFilter = CReqVerificationStatus::PENDING->value;
        }

        $search = trim((string) $request->query('q'));

        $baseQuery = Task::query()
            ->applyUserScope()
            ->where('task_type', TaskType::CREQ->value)
            ->whereHas('creqDetail', function ($q) {
                $q->where('is_billable', true);
            });

        // Summary Counts per status
        $statusCounts = (clone $baseQuery)
            ->join('task_creq_details', 'tasks.id', '=', 'task_creq_details.task_id')
            ->selectRaw("
                COUNT(CASE WHEN task_creq_details.verification_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN task_creq_details.verification_status = 'verified' THEN 1 END) as verified_count,
                COUNT(CASE WHEN task_creq_details.verification_status = 'rejected' THEN 1 END) as rejected_count,
                COUNT(*) as total_count
            ")
            ->first();

        $query = $baseQuery
            ->when($statusFilter !== 'all', function ($q) use ($statusFilter) {
                $q->whereHas('creqDetail', function ($sq) use ($statusFilter) {
                    $sq->where('verification_status', $statusFilter);
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('task_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('full_name', 'like', "%{$search}%")
                                ->orWhere('cid', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('primary_phone', 'like', "%{$search}%");
                        });
                });
            })
            ->with(['customer', 'pop', 'creqDetail.verifier', 'creqDetail.invoice'])
            ->latest('completed_at');

        $tasks = $query->paginate(15)->withQueryString();

        return view('tasks.creq-billing.index', compact('tasks', 'statusFilter', 'statusCounts', 'search'));
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorizeBillable($request, $task);

        $task->loadMissing(['customer.pop', 'customer.customerService', 'pop', 'creqDetail.verifier', 'creqDetail.invoice', 'maintenanceReport']);

        return view('tasks.creq-billing.show', compact('task'));
    }

    /**
     * "Setujui & Terbitkan Tagihan" — keputusan user 2026-09-28.
     *
     * Dulu Setujui cuma menandai Terverifikasi lalu melempar CS ke
     * /invoices/create: tagihan tidak tertaut ke task, dan kalau CS tidak
     * menyelesaikan form itu biaya C-REQ tercatat disetujui tapi tidak pernah
     * ditagih. Sekarang SATU transaksi: Tagihan Manual terbit, detail jadi
     * Terverifikasi, nomor tagihan tersimpan di detail. Gagal di titik mana
     * pun (mis. pelanggan belum punya layanan) = tidak ada yang berubah.
     *
     * Kategori tagihan diturunkan dari kategori C-REQ (bukan input klien);
     * nominal WAJIB diketik CS — tidak pernah diambil dari catatan teknisi.
     */
    public function approve(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeBillable($request, $task);

        $request->merge(RupiahInput::parseKeys($request->only(['amount']), 'amount'));

        $manualCategory = $task->creqDetail->category->toManualInvoiceCategory();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:99999999.99',
            'description' => 'required|string|max:1000',
            'manual_subtype_name' => [
                Rule::requiredIf($manualCategory->requiresSubtypeName()),
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        // Scope pelanggan (bukan cuma pop_id task) — sama dengan
        // InvoiceController::store(): tagihan terbit di POP pelanggan saat ini.
        $customer = Customer::query()->applyUserScope()->with('customerService')->find($task->customer_id);
        abort_unless($customer, 403, 'Anda tidak memiliki akses ke pelanggan task ini.');

        $invoice = DB::transaction(function () use ($task, $request, $customer, $manualCategory, $validated) {
            // lockForUpdate() DI DALAM transaksi — menutup celah TOCTOU dua
            // request approve/reject bersamaan (baca PENDING, dua-duanya
            // lolos abort_unless, lalu saling timpa / tagihan terbit dua kali).
            $detail = TaskCreqDetail::whereKey($task->creqDetail->id)->lockForUpdate()->firstOrFail();

            abort_unless($detail->verification_status === CReqVerificationStatus::PENDING, 422, 'Verifikasi biaya C-REQ ini sudah diproses.');

            $invoice = app(ManualCategoryInvoiceService::class)->issue(
                $customer,
                $manualCategory,
                $validated['manual_subtype_name'] ?? null,
                $validated['description'],
                $validated['amount'],
                auth()->id(),
            );

            $oldStatus = $detail->verification_status->value;

            $detail->update([
                'verification_status' => CReqVerificationStatus::VERIFIED->value,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'rejection_reason' => null,
                'invoice_id' => $invoice->id,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Verifikasi Biaya C-REQ',
                'action' => 'approve',
                'auditable_type' => TaskCreqDetail::class,
                'auditable_id' => $detail->id,
                'old_values' => ['verification_status' => $oldStatus],
                'new_values' => [
                    'verification_status' => CReqVerificationStatus::VERIFIED->value,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);

            return $invoice;
        });

        // PRG ke halaman Detail C-REQ ini sendiri — tagihan yang terbit
        // ditampilkan di sana beserta tautannya.
        return redirect()
            ->route('tasks.creq-billing.show', $task)
            ->with('success', "Biaya C-REQ task {$task->task_number} disetujui — Tagihan Manual {$invoice->invoice_number} terbit.");
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
