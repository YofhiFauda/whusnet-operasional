<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\SubscriptionStatus;
use App\Models\User;
use App\Models\Village;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Query builder + render bersama untuk TIGA halaman daftar pelanggan yang
 * beda route, beda permission, beda view: List Data Pelanggan
 * (`customers.view`), List Pelanggan Putus (`customers.terminated.view`), dan
 * List Pelanggan Gagal (`customers.failed.view`).
 *
 * Ditaruh di trait, BUKAN di CustomerController, supaya
 * CustomerTerminatedController/CustomerFailedController tidak perlu extend
 * CustomerController cuma demi satu method: extend berarti dua controller
 * halaman-daftar ikut mewarisi ~3700 baris method tulis (store/update/destroy/
 * import/aktivasi) yang sama sekali bukan urusannya. Sekarang keduanya extend
 * Controller biasa dan cuma menarik apa yang dipakai.
 *
 * $forcedStatusGroup dikunci dari controller (bukan dari query string) supaya
 * grup tidak bisa "dipaksa ganti" lewat URL di route yang permission-nya beda.
 */
trait RendersCustomerList
{
    protected function renderCustomerList(
        Request $request,
        ?string $forcedStatusGroup = null,
        string $view = 'customers.index'
    ): View {
        $search = trim((string) $request->query('search', ''));
        $statusGroup = $forcedStatusGroup ?? trim((string) $request->query('status_group', ''));
        // Default to empty string '' (Semua active & suspend) if not specified
        $status = $request->query('status', '');
        // Fase 5.4 — filter wilayah multi-pilih (dropdown Kecamatan + Desa).
        // Terima district_id[]/village_id[] (array) maupun tunggal (kompat lama).
        $districtIds = array_values(array_filter(
            (array) $request->query('district_id', []),
            fn ($v) => $v !== '' && $v !== null
        ));
        $villageIds = array_values(array_filter(
            (array) $request->query('village_id', []),
            fn ($v) => $v !== '' && $v !== null
        ));
        $packageId = $request->query('package_id', '');
        // Fase 5.4b — filter POP multi (dropdown Cabang + Mini POP).
        // pop_id[] = cabang (customers.pop_id), mini_pop_id[] = Mini POP (OLT).
        $popIds = array_values(array_filter(
            (array) $request->query('pop_id', []),
            fn ($v) => $v !== '' && $v !== null
        ));
        $miniPopIds = array_values(array_filter(
            (array) $request->query('mini_pop_id', []),
            fn ($v) => $v !== '' && $v !== null
        ));
        $completenessStatus = $request->query('completeness_status', '');
        $collectorId = $request->query('collector_id', '');

        // Fase 5.6 — batasi kolom yang ditarik untuk daftar (G/row bloat).
        // `customers` punya ~45 kolom termasuk banyak yang TIDAK dipakai di list
        // (teknis: ont_sn/odp/olt/vlan; foto rumah/kontrak; npwp/
        // company; lat/long; kontrak/diskon/pajak; sales/agent/referral). Select
        // hanya yang dirender + accessor (display_id butuh cid/customer_code/
        // distribution_id/status + relasi) + FK untuk eager load. FK WAJIB ikut,
        // kalau tidak relasi belongsTo-nya gagal dimuat.
        $query = Customer::query()
            ->applyUserScope()
            ->select([
                'id', 'person_id',
                'customer_code', 'old_customer_id', 'old_request_id', 'cid',
                'full_name', 'primary_phone', 'email', 'identity_number', 'gender',
                'status', 'data_completeness_status', 'registration_date',
                'rejected_at', 'terminated_at', 'address',
                'pop_id', 'distribution_id', 'mini_pop_id', 'collector_id',
                'city_id', 'district_id', 'village_id', 'internet_package_id',
                'created_at', 'updated_at',
            ])
            ->with(['city', 'district', 'village', 'internetPackage', 'subscriptionStatus', 'pop', 'distribution', 'customerAddress', 'customerService', 'customerDevice', 'latestInvoice', 'latestPayment', 'collector:id,name']);

        // Search filter — Fase 5.3. Diarahkan per BENTUK input, bukan LIKE '%x%'
        // di 8 kolom sekaligus (yang memaksa full scan tiap ketik):
        //  - ada '@'  → email (identifier, prefix)
        //  - ada digit → kode/HP/NIK/CID → PREFIX 'x%' (sargable, pakai index)
        //  - selainnya → nama → substring '%x%' (nama memang butuh potongan tengah,
        //                cuma 1 kolom, jauh lebih murah dari 8-kolom OR)
        // Konsekuensi UX yang disengaja: query nama tidak lagi mencocokkan kode,
        // dan sebaliknya — orang mencari BY nama ATAU BY kode, bukan campur.
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                if (str_contains($search, '@')) {
                    $q->where('email', 'like', "{$search}%");
                } elseif (preg_match('/\d/', $search)) {
                    $q->where('customer_code', 'like', "{$search}%")
                        ->orWhere('cid', 'like', "{$search}%")
                        ->orWhere('old_customer_id', 'like', "{$search}%")
                        ->orWhere('old_request_id', 'like', "{$search}%")
                        ->orWhere('primary_phone', 'like', "{$search}%")
                        ->orWhere('identity_number', 'like', "{$search}%");
                } else {
                    $q->where('full_name', 'like', "%{$search}%");
                }
            });
        }

        // Status filter
        if ($status !== '') {
            $query->where('status', $status);
        } elseif ($statusGroup !== '') {
            $statuses = match ($statusGroup) {
                // Fase survey s/d siap-pemasangan. Semua status antara sengaja
                // masuk sini: tanpa ini `survey_in_progress` & `waiting_acc` gak
                // dipetakan ke grup mana pun DAN bukan default (active/suspended),
                // jadi pelanggan yang lagi diproses — atau yang dikembalikan dari
                // "Pelanggan Gagal" ke tahap ini — lenyap dari SEMUA daftar.
                'survey' => ['waiting_survey', 'survey_in_progress', 'surveyed', 'waiting_acc'],
                // Fase pemasangan s/d verifikasi admin. Alasan sama:
                // `installation_in_progress`, `verification_admin`,
                // `revision_installation` dulu invisible di mana-mana.
                'verification' => ['waiting_installation', 'installation_in_progress', 'installed', 'verification_admin', 'revision_installation'],
                'failed' => ['failed', 'rejected', 'gagal'],
                'terminated' => ['terminated', 'putus'],
                default => []
            };
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        } else {
            // Default view shows only active & suspended customers
            $query->whereIn('status', ['active', 'suspended']);
        }

        // District filter (multi)
        if (! empty($districtIds)) {
            $query->whereIn('district_id', $districtIds);
        }

        // Village filter (multi)
        if (! empty($villageIds)) {
            $query->whereIn('village_id', $villageIds);
        }

        // Service package filter
        if ($packageId !== '') {
            $query->where('internet_package_id', $packageId);
        }

        // POP filter (Cabang multi)
        if (! empty($popIds)) {
            $query->whereIn('pop_id', $popIds);
        }

        // Mini POP filter (multi)
        if (! empty($miniPopIds)) {
            $query->whereIn('mini_pop_id', $miniPopIds);
        }

        // Completeness status filter
        if ($completenessStatus !== '') {
            $query->where('data_completeness_status', $completenessStatus);
        }

        // Kolektor filter — 'none' = belum ada kolektor sama sekali, angka =
        // kolektor tertentu. docs/plan/analisa-billing-tagihan-pembayaran-
        // kolektor.md §B-3 (cara admin lihat "pelanggan ini kolektornya siapa").
        if ($collectorId === 'none') {
            $query->whereNull('collector_id');
        } elseif ($collectorId !== '') {
            $query->where('collector_id', $collectorId);
        }

        if ($statusGroup === 'failed') {
            // Fase 5.1 — urut pakai kolom nyata rejected_at. Versi lama memakai
            // subquery JSON berkorelasi ke audit_logs di ORDER BY (dieksekusi
            // sekali per baris pelanggan, scan+parse JSON) — O(pelanggan ×
            // audit_logs), timeout begitu audit membesar. Kolom diisi di
            // CustomerWorkflowService, import, dan command backfill.
            $query->orderByDesc('rejected_at');
        } elseif ($statusGroup === 'terminated') {
            $query->orderByDesc('terminated_at');
        } else {
            $query->orderBy('customer_code', 'asc');
        }

        // Baris per halaman — staf yang memproses ratusan baris lebih butuh
        // melihat banyak sekaligus. Whitelist supaya tidak bisa disetel ke nilai
        // ekstrem lewat query string.
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $customers = $query->paginate($perPage)->withQueryString();

        // Untuk grup "failed" (Pelanggan Gagal), ambil alasan & tanggal penolakan
        // dari AuditLog transisi terakhir ke status 'rejected' per customer.
        if ($statusGroup === 'failed' && $customers->count() > 0) {
            $customerIds = $customers->pluck('id')->all();
            $rejectLogs = AuditLog::where('auditable_type', Customer::class)
                ->whereIn('auditable_id', $customerIds)
                ->where('module', 'Customer Workflow')
                ->where('action', 'status_transition')
                ->whereJsonContains('new_values->status', 'rejected')
                ->orderByDesc('created_at')
                ->get()
                ->unique('auditable_id')
                ->keyBy('auditable_id');

            foreach ($customers as $customer) {
                $log = $rejectLogs->get($customer->id);
                $note = $log?->new_values['note'] ?? null;
                $customer->reject_reason = $note ? preg_replace('/^Ditolak:\s*/', '', $note) : '-';
                $customer->rejected_at = $log?->created_at;
                $customer->status_before_reject = $log?->old_values['status'] ?? null;
            }
        }

        // Untuk grup "terminated" (Putus Langganan), ambil alasan & tanggal
        // pemutusan dari AuditLog terminate per customer.
        if ($statusGroup === 'terminated' && $customers->count() > 0) {
            $customerIds = $customers->pluck('id')->all();
            $terminateLogs = AuditLog::where('auditable_type', Customer::class)
                ->whereIn('auditable_id', $customerIds)
                ->where('module', 'customers')
                ->where('action', 'terminate')
                ->orderByDesc('created_at')
                ->get()
                ->unique('auditable_id')
                ->keyBy('auditable_id');

            foreach ($customers as $customer) {
                $log = $terminateLogs->get($customer->id);
                $customer->termination_reason = $log?->new_values['reason'] ?? '-';
                $customer->terminated_at = $log?->created_at;
                $customer->device_retrieved_at = $customer->customerDevice?->device_retrieved_at;
            }
        }

        // Data for filter selects. Fase 5.4 — kecamatan pakai combobox typeahead
        // (/api/wilayah/districts), jadi TIDAK memuat seluruh district. Cuma
        // resolve label kecamatan yang SEDANG terpilih untuk chip awal.
        $selectedDistricts = empty($districtIds)
            ? collect()
            : District::whereIn('id', $districtIds)->orderBy('name')->get(['id', 'name']);
        $selectedVillages = empty($villageIds)
            ? collect()
            : Village::whereIn('id', $villageIds)->with('district:id,name')->orderBy('name')->get(['id', 'name', 'district_id']);
        $packages = InternetPackage::orderBy('name')->get();
        // Fase 5.4b — POP pakai dropdown filter (Cabang + Mini POP) via endpoint
        // /api/pop/*, jadi TIDAK memuat seluruh POP. Resolve label yang terpilih
        // saja untuk chip awal (tetap lewat forUser → aman scope).
        $selectedCabang = empty($popIds)
            ? collect()
            : Pop::forUser()->whereIn('id', $popIds)->orderBy('name')->get(['id', 'name']);
        $selectedMini = empty($miniPopIds)
            ? collect()
            : Pop::forUser()->whereIn('id', $miniPopIds)->with('parent:id,name')->orderBy('name')->get(['id', 'name', 'parent_id']);
        $subscriptionStatuses = SubscriptionStatus::query()
            ->where('is_active', true)
            ->orderBy('workflow_order')
            ->get();
        $collectorOptions = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', 'kolektor'))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Customer count by status (for badge list / submenus)
        $statusCounts = Customer::applyUserScope()->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Total is active + suspended customers
        $totalCustomers = ($statusCounts['active'] ?? 0) + ($statusCounts['suspended'] ?? 0);

        // Jumlah pelanggan aktif dengan invoice lewat tempo (untuk summary strip)
        $overdueCount = Invoice::whereHas('customer', function ($q) {
            $q->applyUserScope()->where('status', 'active');
        })
            ->where('invoice_status', '!=', InvoiceStatus::LUNAS->value)
            ->where('due_date', '<', now())
            ->count();

        return view($view, compact(
            'customers',
            'selectedDistricts',
            'selectedVillages',
            'selectedCabang',
            'selectedMini',
            'packages',
            'statusCounts',
            'totalCustomers',
            'overdueCount',
            'subscriptionStatuses',
            'search',
            'status',
            'statusGroup',
            'districtIds',
            'villageIds',
            'packageId',
            'popIds',
            'miniPopIds',
            'completenessStatus',
            'collectorId',
            'collectorOptions'
        ));
    }
}
