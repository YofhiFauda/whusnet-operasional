<?php

namespace App\Services;

use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\TaskMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penulisan & pembacaan baris material task.
 *
 * Semua tulis-menulis material lewat sini — controller cuma menyerahkan array
 * baris hasil validasi. Alasannya sama dengan service lain di repo ini:
 * laporan survey & pemasangan dua-duanya menulis ke tabel yang sama, dan aturan
 * "hapus lalu tulis ulang dalam satu transaksi" harus persis sama di dua jalur.
 */
class TaskMaterialService
{
    /**
     * Ganti seluruh baris material milik satu task untuk satu fase.
     *
     * Hapus-dan-tulis-ulang, bukan upsert per baris: form-nya repeatable dan
     * teknisi bebas menambah/menghapus baris, jadi mencocokkan baris lama dengan
     * baru butuh identitas stabil yang gak ada di form. Fase lain (estimasi vs
     * terpakai) tidak ikut terhapus — filternya selalu menyertakan `kind`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function sync(FopTask $fopTask, MaterialKind $kind, array $rows, ?int $recordedBy = null): void
    {
        DB::transaction(function () use ($fopTask, $kind, $rows, $recordedBy) {
            $fopTask->materials()->where('kind', $kind->value)->delete();

            foreach ($rows as $row) {
                $normalized = $this->normalizeRow($row);

                if ($normalized === null) {
                    continue;
                }

                TaskMaterial::create([
                    'fop_task_id' => $fopTask->id,
                    'customer_id' => $fopTask->customer_id,
                    'kind' => $kind->value,
                    'item_id' => $normalized['item_id'],
                    'item_type' => $normalized['item_type'],
                    'item_category_id' => $normalized['item_category_id'],
                    'item_name' => $normalized['item_name'],
                    'qty' => $normalized['qty'],
                    'unit' => $normalized['unit'],
                    'note' => $normalized['note'],
                    'recorded_by' => $recordedBy,
                ]);
            }
        });
    }

    /**
     * Baris kosong dibuang diam-diam (form repeatable selalu menyisakan baris
     * kosong terakhir), baris tanpa qty valid juga — bukan error, karena
     * memaksa teknisi memperbaiki baris yang memang tidak dia isi cuma bikin
     * laporan gagal submit di lapangan.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $row): ?array
    {
        $itemId = ! empty($row['item_id']) ? (int) $row['item_id'] : null;
        $item = $itemId ? Item::with('category')->find($itemId) : null;

        // Nama & tipe di-snapshot dari master kalau item terdaftar; kalau tidak
        // (kasus "lainnya"), pakai isian manual teknisi.
        $name = trim((string) ($item?->name ?? $row['item_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $qty = (float) ($row['qty'] ?? 0);

        if ($qty <= 0) {
            return null;
        }

        // Kategori barang terdaftar SELALU menang atas kiriman form — form
        // mengunci kolomnya waktu item master dipilih, tapi POST yang dirakit
        // tangan tidak. Baris "lainnya" (tanpa item_id) baru boleh menentukan
        // kategorinya sendiri, dan itu pun harus code yang ada di master.
        $category = $item?->category
            ?? ItemCategory::where('code', (string) ($row['item_type'] ?? ''))->first()
            ?? ItemCategory::where('code', ItemCategory::CODE_LAINNYA)->first();

        // Barang terdaftar: satuan diambil dari master, bukan dari form. Form
        // memang mengunci kolom ini waktu item master dipilih, tapi kalau yang
        // dikirim tetap menang, satu POST yang dirakit tangan bisa bikin "120 pcs
        // dropcore" masuk dan agregasi pemakaian jadi ngawur.
        $unit = $item?->unit ?? trim((string) ($row['unit'] ?? ''));

        if ($unit === '') {
            $unit = $category?->default_unit ?? 'pcs';
        }

        return [
            'item_id' => $item?->id,
            // Snapshot code + FK sekaligus: code buat riwayat yang harus tetap
            // terbaca walau kategorinya dihapus, FK buat agregasi/join.
            'item_type' => $category?->code ?? ItemCategory::CODE_LAINNYA,
            'item_category_id' => $category?->id,
            'item_name' => $name,
            'qty' => $qty,
            'unit' => $unit,
            'note' => ! empty($row['note']) ? trim((string) $row['note']) : null,
        ];
    }

    /**
     * FopTask aktif milik pelanggan untuk kategori tertentu — anchor baris
     * material. Null kalau task-nya belum ada (mis. laporan disimpan sebelum
     * papan FOP sempat auto-sync); pemanggil yang memutuskan apa yang terjadi.
     */
    public function resolveTaskFor(Customer $customer, TaskType $category): ?FopTask
    {
        return FopTask::where('customer_id', $customer->id)
            ->where('category', $category->value)
            ->whereNotIn('status', [TaskStatus::DIBATALKAN->value])
            ->latest('id')
            ->first();
    }

    /**
     * Baris estimasi terakhir milik pelanggan — dipakai form Laporan Pemasangan
     * buat prefill. Dicari lewat customer_id (bukan fop_task_id) karena
     * estimasinya menempel di task SURVEY, sedangkan yang mengisi realisasi
     * adalah task PEMASANGAN.
     *
     * @return Collection<int, TaskMaterial>
     */
    public function estimatesForCustomer(Customer $customer): Collection
    {
        return TaskMaterial::where('customer_id', $customer->id)
            ->estimasi()
            ->orderBy('id')
            ->get();
    }

    /**
     * Perbandingan estimasi vs terpakai per barang, buat halaman verifikasi.
     *
     * Dikelompokkan pakai item_id kalau ada; kalau null (barang "lainnya"),
     * jatuh ke nama yang di-lowercase supaya "Tray" dan "tray" tetap ketemu.
     *
     * @return array<int, array{label: string, unit: string, estimasi: float, terpakai: float, selisih: float}>
     */
    public function varianceForCustomer(Customer $customer): array
    {
        $rows = TaskMaterial::where('customer_id', $customer->id)->orderBy('id')->get();

        $grouped = [];

        foreach ($rows as $row) {
            $key = $row->item_id ? 'item:'.$row->item_id : 'name:'.mb_strtolower($row->item_name);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'label' => $row->item_name,
                    'unit' => $row->unit,
                    'estimasi' => 0.0,
                    'terpakai' => 0.0,
                    'selisih' => 0.0,
                ];
            }

            $bucket = $row->kind === MaterialKind::ESTIMASI ? 'estimasi' : 'terpakai';
            $grouped[$key][$bucket] += (float) $row->qty;
        }

        foreach ($grouped as $key => $data) {
            $grouped[$key]['selisih'] = $data['terpakai'] - $data['estimasi'];
        }

        return array_values($grouped);
    }
}
