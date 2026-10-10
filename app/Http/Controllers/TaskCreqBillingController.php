<?php

namespace App\Http\Controllers;

use App\Enums\CReqVerificationStatus;
use App\Enums\ManualInvoiceCategory;
use App\Enums\TaskType;
use App\Helpers\FormatHelper;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InventorySerialSale;
use App\Models\Task;
use App\Models\TaskBillingItem;
use App\Models\TaskCreqDetail;
use App\Models\TaskMaintenanceBillingDetail;
use App\Services\EffectiveAccessService;
use App\Services\ManualCategoryInvoiceService;
use App\Services\TaskWorkToolService;
use App\Support\LikeSearch;
use App\Support\ReasonValidationRule;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $search = LikeSearch::sanitize((string) $request->query('q'));

        // OR dua tipe task berbayar — C-REQ (task_creq_details) & MTN polos
        // (task_maintenance_billing_details). Dua tabel detail BEDA, jadi
        // tiap cabang OR wajib cek task_type DULU sebelum whereHas ke
        // relasi tipe itu sendiri — whereHas tunggal lintas tipe akan salah
        // sasaran (riwayat bug sejenis: ADHOC-144, join status ambigu).
        $baseQuery = Task::query()->applyUserScope()->where(function ($q) {
            $q->where(fn ($cq) => $cq->where('task_type', TaskType::CREQ->value)->whereHas('creqDetail', fn ($sq) => $sq->where('is_billable', true)))
                ->orWhere(fn ($mq) => $mq->where('task_type', TaskType::MAINTENANCE->value)->whereHas('maintenanceBillingDetail', fn ($sq) => $sq->where('is_billable', true)));
        });

        // Summary Counts per status — DUA count query terpisah (satu per
        // tabel detail, tidak bisa lagi satu join tunggal sejak ada dua
        // tabel) lalu di-SUM di PHP. Bentuk return TETAP objek 4 properti
        // (pending_count/verified_count/rejected_count/total_count) — view
        // index.blade.php baca langsung properti itu, kontraknya tidak berubah.
        $creqCounts = (clone $baseQuery)
            ->where('task_type', TaskType::CREQ->value)
            ->join('task_creq_details', 'tasks.id', '=', 'task_creq_details.task_id')
            ->selectRaw("
                COUNT(CASE WHEN task_creq_details.verification_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN task_creq_details.verification_status = 'verified' THEN 1 END) as verified_count,
                COUNT(CASE WHEN task_creq_details.verification_status = 'rejected' THEN 1 END) as rejected_count,
                COUNT(*) as total_count
            ")
            ->first();

        $mtnCounts = (clone $baseQuery)
            ->where('task_type', TaskType::MAINTENANCE->value)
            ->join('task_maintenance_billing_details', 'tasks.id', '=', 'task_maintenance_billing_details.task_id')
            ->selectRaw("
                COUNT(CASE WHEN task_maintenance_billing_details.verification_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN task_maintenance_billing_details.verification_status = 'verified' THEN 1 END) as verified_count,
                COUNT(CASE WHEN task_maintenance_billing_details.verification_status = 'rejected' THEN 1 END) as rejected_count,
                COUNT(*) as total_count
            ")
            ->first();

        $statusCounts = (object) [
            'pending_count' => (int) $creqCounts->pending_count + (int) $mtnCounts->pending_count,
            'verified_count' => (int) $creqCounts->verified_count + (int) $mtnCounts->verified_count,
            'rejected_count' => (int) $creqCounts->rejected_count + (int) $mtnCounts->rejected_count,
            'total_count' => (int) $creqCounts->total_count + (int) $mtnCounts->total_count,
        ];

        $query = $baseQuery
            ->when($statusFilter !== 'all', function ($q) use ($statusFilter) {
                // Status dicek ke RELASI MILIK TIPE itu sendiri, bukan
                // whereHas tunggal — supaya task C-REQ pending tidak ikut
                // nyangkut lolos filter `status=verified` cuma karena ada
                // task MTN verified lain yang whereHas-nya kebetulan match.
                $q->where(function ($sq) use ($statusFilter) {
                    $sq->where(fn ($cq) => $cq->where('task_type', TaskType::CREQ->value)->whereHas('creqDetail', fn ($ssq) => $ssq->where('verification_status', $statusFilter)))
                        ->orWhere(fn ($mq) => $mq->where('task_type', TaskType::MAINTENANCE->value)->whereHas('maintenanceBillingDetail', fn ($ssq) => $ssq->where('verification_status', $statusFilter)));
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
            ->with(['customer', 'pop', 'creqDetail.verifier', 'creqDetail.invoice', 'maintenanceBillingDetail.verifier', 'maintenanceBillingDetail.invoice'])
            ->latest('completed_at');

        $tasks = $query->paginate(15)->withQueryString();

        return view('tasks.creq-billing.index', compact('tasks', 'statusFilter', 'statusCounts', 'search'));
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorizeBillable($request, $task);

        // creqDetail.* DAN maintenanceBillingDetail.* dimuat sekaligus — show.blade.php
        // membaca salah satunya tergantung task_type (lihat $isCreqTask di sana).
        // Tanpa ini task MTN tetap benar (relasi lazy-load sendiri), tapi N+1
        // query verifier/invoice tiap kali halaman ini dibuka untuk task MTN.
        $task->loadMissing(['customer.pop', 'customer.customerService', 'pop', 'creqDetail.verifier', 'creqDetail.invoice', 'maintenanceBillingDetail.verifier', 'maintenanceBillingDetail.invoice', 'maintenanceReport']);

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
     * Kategori tagihan diturunkan dari kategori C-REQ untuk task C-REQ;
     * task MTN polos (tidak punya kategori C-REQ) selalu `PERBAIKAN` — satu
     * nilai hardcode, bukan ditebak dari billing_note. Nominal WAJIB diketik
     * CS lewat breakdown per-item `items[]` (Task 4, 2026-10-10) — tidak
     * pernah diambil dari catatan teknisi.
     */
    public function approve(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeBillable($request, $task);

        // Breakdown Harga Modem + Jasa (ownership modem sewa/beli, Task 6,
        // 2026-10-09): kalau laporan teknisi "Beli dari Gudang" sudah
        // menulis InventorySerialSale buat task ini (Task 1-3), breakdown
        // `items[]` WAJIB menyertakan satu baris dengan
        // `inventory_serial_sale_id` = $sale->id — lihat validasi di bawah.
        // Rantainya: Task::fopTask() (hasOne, FK fop_tasks.task_id) ->
        // FopTask -> InventorySerialSale.fop_task_id. Dipakai resolveTaskFor()
        // (sama persis yang dipakai show.blade.php buat anchor material/SN)
        // karena itu SATU-SATUNYA jalur Task->FopTask yang sudah tahan kalau
        // suatu saat ada >1 FopTask menunjuk task yang sama (hasOne Eloquent
        // diam-diam ambil salah satu; query langsung + latest('id') di sana
        // eksplisit).
        $fopTask = app(TaskWorkToolService::class)->resolveTaskFor($task);
        $sale = $fopTask ? InventorySerialSale::where('fop_task_id', $fopTask->id)->first() : null;

        // Parse rupiah per baris SEBELUM validasi — array dinamis, jadi
        // RupiahInput::parseKeys() (dibuat buat key flat) tidak cocok di
        // sini, loop manual per index.
        $items = $request->input('items', []);
        foreach ($items as $i => $row) {
            $items[$i]['amount'] = RupiahInput::parse($row['amount'] ?? null);
        }
        $request->merge(['items' => $items]);

        $manualCategory = $task->task_type === TaskType::CREQ
            ? $task->creqDetail->category->toManualInvoiceCategory()
            : ManualInvoiceCategory::PERBAIKAN;

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:150',
            // min:0.01, bukan min:0, untuk SEMUA baris termasuk baris modem
            // auto-seed — samakan dengan invarian
            // `InventoryService::recordSerialSale()` (harga > 0): modem yang
            // sudah terjual tidak boleh "dikoreksi" jadi Rp0 di sini. Baris
            // lain (jasa dll) boleh Rp0 asal bukan baris modemnya sendiri.
            'items.*.amount' => 'required|numeric|min:0.01|max:99999999.99',
            'items.*.inventory_serial_sale_id' => ['nullable', 'integer', Rule::in($sale ? [$sale->id] : [])],
            'manual_subtype_name' => [
                Rule::requiredIf($manualCategory->requiresSubtypeName()),
                'nullable',
                'string',
                'max:150',
            ],
        ]);

        // Guard SEBELUM transaksi dimulai — kalau sale row ada, breakdown
        // wajib menyertakan baris modemnya. Dicek di sini (bukan di dalam
        // DB::transaction()) supaya tidak ada tulisan apa pun ke DB
        // (task_billing_items, invoice, InventorySerialSale::price) kalau
        // baris modem hilang — CS yang lupa menyertakannya ditolak bersih,
        // bukan rollback setengah jalan.
        if ($sale && ! collect($validated['items'])->contains(fn ($item) => (int) ($item['inventory_serial_sale_id'] ?? 0) === $sale->id)) {
            throw ValidationException::withMessages([
                'items' => 'Baris "Jual Modem" wajib ada — task ini mencatat penjualan modem, breakdown tidak boleh menghilangkan barisnya.',
            ]);
        }

        $amount = collect($validated['items'])->sum('amount');
        $description = collect($validated['items'])
            ->map(fn ($item) => "{$item['name']}: Rp".FormatHelper::rupiahInput($item['amount']))
            ->implode(' + ');

        // Scope pelanggan (bukan cuma pop_id task) — sama dengan
        // InvoiceController::store(): tagihan terbit di POP pelanggan saat ini.
        $customer = Customer::query()->applyUserScope()->with('customerService')->find($task->customer_id);
        abort_unless($customer, 403, 'Anda tidak memiliki akses ke pelanggan task ini.');

        // Model+ID detail yang dikunci & di-update beda per task_type — SATU
        // tabel detail untuk C-REQ (task_creq_details), SATU lagi untuk MTN
        // polos (task_maintenance_billing_details). Resolusinya di sini,
        // SEKALI, dipakai lagi di dalam closure transaksi di bawah supaya
        // tidak ada cabang if/else kedua yang bisa menyimpang dari ini.
        $detailModelClass = $task->task_type === TaskType::CREQ ? TaskCreqDetail::class : TaskMaintenanceBillingDetail::class;
        $detailId = $task->task_type === TaskType::CREQ ? $task->creqDetail->id : $task->maintenanceBillingDetail->id;

        $invoice = DB::transaction(function () use ($task, $request, $customer, $manualCategory, $validated, $amount, $description, $sale, $detailModelClass, $detailId) {
            // lockForUpdate() DI DALAM transaksi — menutup celah TOCTOU dua
            // request approve/reject bersamaan (baca PENDING, dua-duanya
            // lolos abort_unless, lalu saling timpa / tagihan terbit dua kali).
            $detail = $detailModelClass::whereKey($detailId)->lockForUpdate()->firstOrFail();

            abort_unless($detail->verification_status === CReqVerificationStatus::PENDING, 422, 'Verifikasi biaya ini sudah diproses.');

            // Harga modem final (CS boleh koreksi nilai yang dicatat teknisi)
            // ditulis balik ke InventorySerialSale::price DI DALAM transaksi
            // yang sama dengan penerbitan Invoice — kalau salah satu gagal,
            // keduanya ikut batal (sale TIDAK boleh berubah tanpa tagihan
            // yang terbit, dan sebaliknya).
            if ($sale) {
                $modemItem = collect($validated['items'])->firstWhere('inventory_serial_sale_id', $sale->id);
                $sale->update(['price' => $modemItem['amount']]);
            }

            $invoice = app(ManualCategoryInvoiceService::class)->issue(
                $customer,
                $manualCategory,
                $validated['manual_subtype_name'] ?? null,
                $description,
                $amount,
                auth()->id(),
            );

            // Breakdown per-item (Task 4) — satu baris task_billing_items
            // per item di request, SEMUA menunjuk invoice yang baru terbit.
            // Ditulis SETELAH invoice ada (butuh invoice_id), masih di dalam
            // transaksi yang sama (invoice tanpa rinciannya = cacat, harus
            // ikut rollback bareng).
            foreach ($validated['items'] as $item) {
                TaskBillingItem::create([
                    'task_id' => $task->id,
                    'invoice_id' => $invoice->id,
                    'name' => $item['name'],
                    'amount' => $item['amount'],
                    'inventory_serial_sale_id' => $item['inventory_serial_sale_id'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

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
                'auditable_type' => $detailModelClass,
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
            ->with('success', "Biaya task {$task->task_number} disetujui — Tagihan Manual {$invoice->invoice_number} terbit.");
    }

    public function reject(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeBillable($request, $task);

        $validated = $request->validate([
            'reason' => ReasonValidationRule::required(1000),
        ]);

        // Sama resolusi model+ID detail dengan approve() — lihat komentar di
        // sana.
        $detailModelClass = $task->task_type === TaskType::CREQ ? TaskCreqDetail::class : TaskMaintenanceBillingDetail::class;
        $detailId = $task->task_type === TaskType::CREQ ? $task->creqDetail->id : $task->maintenanceBillingDetail->id;

        DB::transaction(function () use ($task, $validated, $request, $detailModelClass, $detailId) {
            // Sama alasan lockForUpdate() di approve() — cegah reject
            // menimpa approve (atau sebaliknya) yang diproses bersamaan.
            $detail = $detailModelClass::whereKey($detailId)->lockForUpdate()->firstOrFail();

            abort_unless($detail->verification_status === CReqVerificationStatus::PENDING, 422, 'Verifikasi biaya ini sudah diproses.');

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
                'auditable_type' => $detailModelClass,
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
            ->with('success', "Biaya task {$task->task_number} ditolak.");
    }

    /**
     * Task harus C-REQ ATAU MTN berbayar (bukan sekadar task tipe itu apa
     * pun) & di dalam POP scope aktor — sama pagar yang dipakai `index()` di
     * atas, tapi wajib dicek ulang di sini karena route model binding tidak
     * lewat query yang sudah `applyUserScope()`.
     */
    private function authorizeBillable(Request $request, Task $task): void
    {
        $task->loadMissing(['creqDetail', 'maintenanceBillingDetail']);

        // `task_type` dicast ke enum `TaskType` (lihat Task::$casts) — match
        // dicocokkan ke `->value` (string), bukan ke instance enum langsung,
        // supaya tidak diam-diam selalu jatuh ke `default` (enum !== string
        // di bawah strict comparison yang dipakai `match`).
        $isBillable = match ($task->task_type->value) {
            TaskType::CREQ->value => (bool) $task->creqDetail?->is_billable,
            TaskType::MAINTENANCE->value => (bool) $task->maintenanceBillingDetail?->is_billable,
            default => false,
        };

        abort_unless($isBillable, 404, 'Task ini bukan task berbayar yang menunggu verifikasi.');

        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        // (int) cast — sama dengan TaskPolicy::withinPopScope(); tanpa cast,
        // pop_id string ('5' !== 5) bikin CS yang scope-nya sah kena 403.
        if (! $access->hasAllPopAccess($user) && ! in_array((int) $task->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Task ini di luar POP scope Anda.');
        }
    }
}
