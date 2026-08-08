<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Task;
use App\Models\TaskWorkTool;
use App\Models\WorkTool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penulisan & pembacaan checklist alat kerja per FopTask.
 *
 * Semua tulis-menulis lewat sini — alasannya sama dengan TaskMaterialService:
 * tiga form laporan (Survey, Pemasangan, Maintenance) menulis ke tabel yang
 * sama, dan aturan "hapus lalu tulis ulang dalam satu transaksi" harus persis
 * sama di tiga jalur.
 */
class TaskWorkToolService
{
    /**
     * Ganti seluruh checklist alat milik satu FopTask.
     *
     * Hapus-dan-tulis-ulang, bukan sync pivot: baris "lainnya" (alat di luar
     * master) tidak punya id master untuk dicocokkan, jadi tidak ada identitas
     * stabil yang bisa dipakai upsert.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function sync(FopTask $fopTask, array $rows, ?int $recordedBy = null): void
    {
        DB::transaction(function () use ($fopTask, $rows, $recordedBy) {
            $fopTask->workTools()->delete();

            foreach ($this->normalizeRows($rows) as $normalized) {
                TaskWorkTool::create([
                    'fop_task_id' => $fopTask->id,
                    'customer_id' => $fopTask->customer_id,
                    'work_tool_id' => $normalized['work_tool_id'],
                    'tool_name' => $normalized['tool_name'],
                    'note' => $normalized['note'],
                    'recorded_by' => $recordedBy,
                ]);
            }
        });
    }

    /**
     * Rakit baris dari payload form.
     *
     * Form mengirim dua bagian terpisah — checkbox master (`work_tools_ids`)
     * dan input bebas (`work_tools_manual`) — karena checkbox tidak bisa
     * membawa catatan per-baris. Penggabungannya ditaruh di sini supaya tiga
     * controller tidak masing-masing menulis ulang bentuk yang sama.
     *
     * @param  array<int, mixed>  $ids
     * @param  array<int, array<string, mixed>>  $manual
     * @return array<int, array<string, mixed>>
     */
    public function rowsFromRequest(array $ids, array $manual): array
    {
        $rows = collect($ids)
            ->filter()
            ->map(fn ($id) => ['work_tool_id' => (int) $id])
            ->all();

        foreach ($manual as $row) {
            $rows[] = [
                'work_tool_id' => null,
                'tool_name' => $row['tool_name'] ?? null,
                'note' => $row['note'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Buang baris kosong dan duplikat.
     *
     * Duplikat dibuang karena checklist tidak punya qty: dua baris "Tangga"
     * tidak berarti dua tangga, cuma bikin daftar yang dibaca teknisi jadi
     * berulang. Baris master menang atas baris manual bernama sama.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        $tools = WorkTool::whereIn('id', collect($rows)->pluck('work_tool_id')->filter())->get()->keyBy('id');
        $normalized = [];

        foreach ($rows as $row) {
            $toolId = ! empty($row['work_tool_id']) ? (int) $row['work_tool_id'] : null;
            $tool = $toolId ? ($tools[$toolId] ?? null) : null;

            // Nama di-snapshot dari master kalau alat terdaftar; kalau tidak
            // (alat di luar master), pakai isian manual teknisi.
            $name = trim((string) ($tool?->name ?? $row['tool_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $key = $tool ? 'tool:'.$tool->id : 'name:'.mb_strtolower($name);

            if (isset($normalized[$key])) {
                continue;
            }

            $normalized[$key] = [
                'work_tool_id' => $tool?->id,
                'tool_name' => $name,
                'note' => ! empty($row['note']) ? trim((string) $row['note']) : null,
            ];
        }

        return array_values($normalized);
    }

    /**
     * FopTask anchor untuk sebuah Task teknisi.
     *
     * Dicari lewat `fop_tasks.task_id` — bukan lewat customer+kategori seperti
     * TaskMaterialService::resolveTaskFor(). Untuk maintenance itu penting:
     * satu pelanggan bisa punya banyak task MTN sepanjang tahun, dan mencari
     * "MTN terakhir milik pelanggan ini" akan menempelkan checklist ke task
     * yang salah.
     */
    public function resolveTaskFor(Task $task): ?FopTask
    {
        return FopTask::where('task_id', $task->id)->latest('id')->first();
    }

    /**
     * FopTask anchor untuk alur berbasis pelanggan (Survey & Pemasangan), yang
     * memang cuma punya satu task aktif per kategori.
     */
    public function resolveTaskForCustomer(Customer $customer, TaskType $category): ?FopTask
    {
        return FopTask::where('customer_id', $customer->id)
            ->where('category', $category->value)
            ->whereNotIn('status', [TaskStatus::DIBATALKAN->value])
            ->latest('id')
            ->first();
    }

    /**
     * Checklist milik satu FopTask, siap dipakai form (prefill).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsFor(?FopTask $fopTask): array
    {
        if (! $fopTask) {
            return [];
        }

        return $fopTask->workTools()->orderBy('id')->get()->map(fn (TaskWorkTool $row) => [
            'work_tool_id' => $row->work_tool_id,
            'tool_name' => $row->tool_name,
            'note' => $row->note,
        ])->all();
    }

    /**
     * Checklist survey milik pelanggan — dipakai form Pemasangan buat prefill.
     *
     * Dicari lewat FopTask kategori SURVEY karena alat yang dinilai perlu itu
     * hasil pengamatan medan oleh surveyor; teknisi pemasangan tinggal membaca
     * dan menyesuaikan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function surveyRowsForCustomer(Customer $customer): array
    {
        return $this->rowsFor($this->resolveTaskForCustomer($customer, TaskType::SURVEY));
    }

    /**
     * Checklist untuk ditampilkan di halaman Task teknisi — inilah yang dibaca
     * teknisi sebelum berangkat, dan alasan utama fitur ini ada.
     *
     * @return Collection<int, TaskWorkTool>
     */
    public function displayRowsForTask(Task $task): Collection
    {
        $fopTask = $this->resolveTaskFor($task);

        if ($fopTask && $fopTask->workTools()->exists()) {
            return $fopTask->workTools()->orderBy('id')->get();
        }

        // Task PSB umumnya belum punya checklist sendiri sampai laporannya
        // diisi — yang berguna justru daftar dari survey. Tanpa fallback ini
        // teknisi pemasangan melihat kolom kosong tepat di saat dia paling
        // butuh tahu harus bawa apa.
        if ($task->customer_id) {
            $surveyTask = $this->resolveTaskForCustomer($task->customer, TaskType::SURVEY);

            if ($surveyTask) {
                return $surveyTask->workTools()->orderBy('id')->get();
            }
        }

        return collect();
    }
}
