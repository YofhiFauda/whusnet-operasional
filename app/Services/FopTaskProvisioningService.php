<?php

namespace App\Services;

use App\Enums\FopTaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan FopTask otomatis untuk kategori SURVEY & PSB (Pemasangan).
 *
 * Dua kategori itu memang TIDAK bisa dipilih manual (`TaskType::autoOnlyValues()`)
 * — satu-satunya jalur lahirnya adalah antrean pelanggan. Sebelumnya logika ini
 * cuma hidup di dalam `FopTaskController::autoSyncAndCalculatePriority()`, yang
 * hanya jalan saat papan `/fop-tasks` dibuka. Akibatnya fatal dan senyap:
 * `task_materials` dan `task_work_tools` WAJIB punya `fop_task_id`, jadi kalau
 * teknisi mengisi laporan survey/pemasangan sebelum ada yang membuka papan FOP,
 * estimasi material dan checklist alat yang dia input dibuang tanpa pesan error
 * (lihat `if ($fopTask)` di CustomerSurveyController & CustomerInstallationController).
 *
 * Karena itu pembuatan FopTask dipindah ke sini dan dipanggil dari titik-titik
 * deterministik: registrasi pelanggan, transisi status ke waiting_survey /
 * waiting_installation, dan sebagai jaring pengaman tepat sebelum laporan
 * teknisi disimpan. Papan FOP tetap memanggil service yang sama untuk pelanggan
 * lama yang terlanjur tidak punya anchor.
 */
class FopTaskProvisioningService
{
    /**
     * Pastikan pelanggan punya FopTask aktif untuk satu kategori otomatis.
     *
     * Idempoten: task yang sudah ada (dan belum selesai/batal) dipakai ulang,
     * jadi aman dipanggil dari banyak titik pada satu alur yang sama.
     */
    public function ensureForCustomer(Customer $customer, TaskType $category): ?FopTask
    {
        if (! in_array($category, [TaskType::SURVEY, TaskType::PEMASANGAN], true)) {
            return null;
        }

        $existing = $this->activeTaskFor($customer, $category);

        if ($existing) {
            return $existing;
        }

        // Dibungkus transaksi + lock supaya dua request yang datang bersamaan
        // (mis. teknisi submit laporan pas FOP membuka papan) tidak melahirkan
        // dua TFOP untuk pelanggan & kategori yang sama.
        return DB::transaction(function () use ($customer, $category) {
            $existing = $this->activeTaskFor($customer, $category, lock: true);

            if ($existing) {
                return $existing;
            }

            $customer->loadMissing(['pop', 'latestSurvey']);

            return FopTask::create([
                'task_number' => $this->generateTaskNumber(),
                'task_date' => now(),
                'category' => $category,
                // Identitas pelanggan yang dipakai seluruh sistem, bukan label
                // tipe tiket generik (lihat CLAUDE.md § sinkronisasi).
                'tugas' => $customer->display_id.'_'.$customer->full_name,
                // Sengaja TIDAK pakai fallback `?? 1` seperti kode auto-sync lama:
                // kedua kolom nullable, dan menembak id 1 yang belum tentu ada
                // melempar FK error. Di jalur simpan laporan teknisi, error itu
                // menggagalkan seluruh laporan — persis yang mau dihindari.
                'village_id' => $customer->village_id,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'issue' => $category === TaskType::SURVEY
                    ? 'Auto-Sync dari antrean survey'
                    : 'Auto-Sync dari antrean pemasangan',
                'status' => TaskStatus::DRAFT,
                // Prioritas final dihitung ulang oleh papan FOP berdasarkan SLA;
                // MEDIUM cuma nilai awal supaya kolomnya tidak kosong.
                'priority' => FopTaskPriority::MEDIUM,
                // Turunan dari customer_surveys.requested_installation_date —
                // cuma bermakna untuk PSB.
                'client_request_date' => $category === TaskType::PEMASANGAN
                    ? $customer->latestSurvey?->requested_installation_date
                    : null,
            ]);
        });
    }

    /**
     * FopTask aktif milik pelanggan untuk satu kategori.
     *
     * "Aktif" = belum selesai dan belum dibatalkan — definisi yang sama dipakai
     * papan FOP saat memutuskan perlu auto-sync atau tidak, dan oleh
     * TaskMaterialService::resolveTaskFor() saat mencari anchor material.
     */
    private function activeTaskFor(Customer $customer, TaskType $category, bool $lock = false): ?FopTask
    {
        $query = FopTask::where('customer_id', $customer->id)
            ->where('category', $category->value)
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Nomor TFOP berikutnya. Format wajib identik dengan
     * `TicketService::generateFopTaskNumber()` — keduanya menulis ke deret yang
     * sama (CLAUDE.md § penomoran).
     */
    public function generateTaskNumber(): string
    {
        $year = date('Y');
        $lastNum = FopTask::where('task_number', 'like', "TFOP-{$year}-%")
            ->pluck('task_number')
            ->map(fn ($taskNumber) => (int) substr($taskNumber, strrpos($taskNumber, '-') + 1))
            ->max() ?? 0;

        return sprintf('TFOP-%s-%04d', $year, $lastNum + 1);
    }
}
