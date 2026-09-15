<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Memuat semua data yang dibutuhkan halaman `verifications/admin.blade.php`
 * — eager-load relasi + material/alat kerja per tahap. DIEKSTRAK dari
 * `CustomerVerificationController::showAdmin()` supaya dipakai bareng
 * `BusinessDevelopmentVerificationController::show()` (ADHOC-67 susulan) —
 * halaman BD me-reuse view yang SAMA PERSIS, cuma tab "Verifikasi"-nya
 * beda isi (form BD vs form CS), jadi keduanya butuh data yang sama.
 *
 * SENGAJA cuma data loading, TANPA otorisasi — dua pemanggil punya gerbang
 * permission yang beda (CS: `customers.detail.installation.validate` dkk;
 * BD: `canInstallationFeeBeValidatedBy()` dinamis per kategori paket), jadi
 * pengecekannya tetap di masing-masing controller.
 */
class CustomerVerificationDetailService
{
    /**
     * @return array{materialVariance: mixed, surveyMaterials: mixed, installationMaterials: Collection, installedSerials: Collection, surveyWorkTools: mixed, installationWorkTools: mixed, initialInvoice: ?Invoice, pendingInitialInvoice: ?array}
     */
    public function load(Customer $customer): array
    {
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
            'latestSurvey.fop',
            'customerService.internetPackage',
            'internetPackage',
            'pop',
            'village.district',
            'city',
        ]);

        // Selisih estimasi vs realisasi material — inti nilai bisnis pencatatan
        // material sebelum modul Inventory ada. Kosong untuk pelanggan lama yang
        // laporannya dibuat sebelum fitur ini.
        $materialService = app(TaskMaterialService::class);
        $materialVariance = $materialService->varianceForCustomer($customer);

        // Baris material mentah per tahap — tabel variance saja tidak cukup:
        // variance mengagregasi dan membuang catatan per baris, padahal yang
        // diinput teknisi adalah daftar barang beserta catatannya. Estimasi
        // ditampilkan di tab Survey (di situ diinputnya), realisasi di tab
        // Pemasangan.
        $surveyMaterials = $materialService->estimatesForCustomer($customer);
        $installationFopTask = $materialService->resolveTaskFor($customer, TaskType::PEMASANGAN);
        $installationMaterials = $installationFopTask
            ? $installationFopTask->materials()->terpakai()->orderBy('id')->get()
            : collect();

        // Perangkat Aktif (ADHOC-54) — SENGAJA gak nyampur ke $installationMaterials
        // di atas: unit SERIALIZED gak pernah masuk task_materials (§3.4
        // rancangan-ui.md, FOP wajib liat gabungan Aktif+Pasif tapi dua
        // sumber data beda). `inventory_serials` yang nunjuk fop_task_id ini
        // yang jadi sumber "perangkat aktif apa yang dipasang di sini".
        $installedSerials = $installationFopTask
            ? $installationFopTask->inventorySerials()->with('item')->get()
            : collect();

        // Checklist alat kerja diinput teknisi di form Survey DAN form Pemasangan,
        // tapi ditulis ke task_work_tools — bukan ke kolom customer_surveys /
        // customer_installations. Tanpa dibaca eksplisit di sini, halaman
        // verifikasi cuma menampilkan teks bebas `required_tools` dan admin
        // kehilangan daftar alat yang sebenarnya dicatat.
        $workToolService = app(TaskWorkToolService::class);
        $surveyWorkTools = $workToolService->rowsFor(
            $workToolService->resolveTaskForCustomer($customer, TaskType::SURVEY)
        );
        $installationWorkTools = $workToolService->rowsFor(
            $workToolService->resolveTaskForCustomer($customer, TaskType::PEMASANGAN)
        );

        // Tagihan Awal SUDAH terbit (pelanggan non-Bisnis, atau pelanggan
        // Bisnis yang BD-nya sudah selesai) — dipakai tab "Verifikasi"
        // cabang BD supaya BD melihat hasil verifikasi CS yang sesungguhnya
        // (tanggal aktivasi, prorata, total tagihan real dari DB), bukan
        // cuma "Ringkasan Layanan" statis dari master paket.
        $initialInvoice = Invoice::where('customer_id', $customer->id)
            ->whereIn('invoice_type', [InvoiceType::AWAL->value, InvoiceType::REAKTIVASI->value])
            ->latest()
            ->first();

        // Kategori Bisnis yang MASIH menunggu BD: Invoice AWAL sengaja BELUM
        // terbit (`CustomerVerificationController::finalVerify()` cuma
        // menitipkan snapshot hitungannya di sini, lihat migration
        // `add_pending_initial_invoice_to_customers_table`) — dipakai tab
        // BD supaya tetap menampilkan angka yang CS hitung/konfirmasi ke
        // pelanggan walau invoice-nya belum ada, ditandai "Belum Terbit" di
        // view. NULL kalau $initialInvoice sudah ada (tidak relevan lagi)
        // atau customer belum pernah di-finalVerify sama sekali.
        $pendingInitialInvoice = $initialInvoice ? null : $customer->pending_initial_invoice;

        return compact(
            'materialVariance',
            'surveyMaterials',
            'installationMaterials',
            'installedSerials',
            'surveyWorkTools',
            'installationWorkTools',
            'initialInvoice',
            'pendingInitialInvoice'
        );
    }
}
